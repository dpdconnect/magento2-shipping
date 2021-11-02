<?php
/**
 * This file is part of the Magento 2 Shipping module of DPD Nederland B.V.
 *
 * Copyright (C) 2019  DPD Nederland B.V.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */
namespace DpdConnect\Shipping\Model;

use DpdConnect\Sdk\Client;
use DpdConnect\Shipping\Config\Constants;
use DpdConnect\Shipping\Helper\DPDClient;
use DpdConnect\Shipping\Helper\DpdSettings;
use Magento\Framework\Encryption\Encryptor;
use Magento\Framework\UrlInterface;

class CheckoutConfigProvider implements \Magento\Checkout\Model\ConfigProviderInterface
{
    /**
     * @var UrlInterface
     */
    private $urlBuilder;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var DPDClient
     */
    private $client;

    /**
     * @var DpdSettings
     */
    private $dpdSettings;

    /**
     * @var Encryptor
     */
    private $crypt;

    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $checkoutSession;

    /**
     * CheckoutConfigProvider constructor.
     *
     * @param UrlInterface $urlBuilder
     * @param Encryptor $crypt
     * @param DPDClient $client
     * @param DpdSettings $dpdSettings
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Checkout\Model\Session $checkoutSession
     */
    public function __construct(
        UrlInterface $urlBuilder,
        Encryptor $crypt,
        DPDClient $client,
        DpdSettings $dpdSettings,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Checkout\Model\Session $checkoutSession
    ) {
        $this->urlBuilder = $urlBuilder;
        $this->scopeConfig = $scopeConfig;
        $this->client = $client;
        $this->dpdSettings = $dpdSettings;
        $this->crypt = $crypt;
        $this->checkoutSession = $checkoutSession;
    }

    /**
     * @return array
     *
     * @throws \DpdConnect\Sdk\Exceptions\AuthenticateException
     * @throws \DpdConnect\Sdk\Exceptions\HttpException
     */
    public function getConfig()
    {
        $output['dpd_parcelshop_url'] = $this->urlBuilder->getUrl('dpd/parcelshops', ['_secure' => true]);
        $output['dpd_parcelshop_save_url'] = $this->urlBuilder->getUrl('dpd/parcelshops/save', ['_secure' => true]);
        $output['dpd_googlemaps_width'] = $this->scopeConfig->getValue(DpdSettings::PARCELSHOP_MAPS_WIDTH);
        $output['dpd_googlemaps_height'] = $this->scopeConfig->getValue(DpdSettings::PARCELSHOP_MAPS_HEIGHT);
        $output['dpd_parcelshop_js_url'] = sprintf('%s/parcelshop/map/js', Client::ENDPOINT);
        $output['dpd_parcelshop_token'] = $this->client->authenticate()->getToken()->getPublicJWTToken(
            $this->dpdSettings->getValue(DpdSettings::ACCOUNT_USERNAME),
            $this->crypt->decrypt($this->dpdSettings->getValue(DpdSettings::ACCOUNT_PASSWORD))
        );
        $output['dpd_parcelshop_use_dpd_key'] = $this->dpdSettings->getValue(DpdSettings::PARCELSHOP_MAPS_USE_DPD_KEY) === 1;
        $output['dpd_parcelshop_google_key'] = $this->dpdSettings->getValue(DpdSettings::PARCELSHOP_MAPS_CLIENT_KEY);

        $quote = $this->checkoutSession->getQuote();
        $output['dpd_carrier_shipping_selected_product'] = $quote->getDpdShippingProduct();

        // Add the available shipping products
        $dayOfWeek = date('N');
        $availableShippingProducts = [];
        $shippingProductsConfig = $this->dpdSettings->getDpdCarrierCustomerProductSettings();
        $shippingProducts = $this->client->authenticate()->getProduct()->getList();
        foreach($shippingProducts as $product) {
            // Handle no selected days as all selected
            if (!isset($shippingProductsConfig[$product['code']]['days'])) {
                $shippingProductsConfig[$product['code']]['days'] = [0, 1, 2, 3, 4, 5, 6, 7];
            }

            if (false === isset($shippingProductsConfig[$product['code']])
                || false === isset($shippingProductsConfig[$product['code']]['enabled'])
                || '0' === $shippingProductsConfig[$product['code']]['enabled']
                || false === in_array($dayOfWeek, $shippingProductsConfig[$product['code']]['days'], false)
                || false === $this->productIsAllowedByTime($shippingProductsConfig[$product['code']]['timeFrom'], $shippingProductsConfig[$product['code']]['timeTill'])
            ) {
                continue;
            }

            $title = $shippingProductsConfig[$product['code']]['title'];
            $availableShippingProducts[] = [
                'code' => $product['code'],
                'title' => $title,
                'price' => $shippingProductsConfig[$product['code']]['price'],
                'onlySpecificCountries' => $shippingProductsConfig[$product['code']]['onlySpecificCountries'],
                'allowedCountries' => isset($shippingProductsConfig[$product['code']]['allowedCountries']) ? $shippingProductsConfig[$product['code']]['allowedCountries'] : [],
            ];
        }

        $output['dpd_carrier_save_url'] = $this->urlBuilder->getUrl('dpd/carrier/save', ['_secure' => true]);
        $output['dpd_carrier_available_shipping_products'] = $availableShippingProducts;

        // Make sure a default value is set
        if (0 < count($availableShippingProducts) && (null === $output['dpd_carrier_shipping_selected_product'] || '' === $output['dpd_carrier_shipping_selected_product'])) {
            // Find the cheapest option
            $cheapest = $availableShippingProducts[0];
            foreach($availableShippingProducts as $availableShippingProduct) {
                if($availableShippingProduct['price'] < $cheapest['price']) {
                    $cheapest = $availableShippingProduct;
                }
            }

            $quote->setDpdShippingProduct($cheapest['code']);
            $quote->save();

            $output['dpd_carrier_shipping_selected_product'] = $cheapest['code'];
        }

        return $output;
    }

    /**
     * @return bool
     */
    private function productIsAllowedByTime(string $fromTime, string $tillTime)
    {
        if (('' === $fromTime && '' === $tillTime) || ('00:00' === $fromTime && '00:00' === $tillTime)) {
            return true;
        }

        $fromTimeNumber = (int)preg_replace('/\D/', '', $fromTime);
        $tillTimeNumber = (int)preg_replace('/\D/', '', $tillTime);
        $currentTimeNumber = (int)date('Hi');
        if ($currentTimeNumber >= $fromTimeNumber && $currentTimeNumber <= $tillTimeNumber) {
            return true;
        }

        return false;
    }
}
