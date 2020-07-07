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
namespace DpdConnect\Shipping\Helper\Services;

use DpdConnect\Shipping\Helper\DpdSettings;
use Magento\Catalog\Model\ProductFactory;
use Magento\Framework\App\Helper\AbstractHelper;
use DpdConnect\Shipping\Helper\DPDClient;
use Magento\Framework\App\Helper\Context;
use Magento\Sales\Model\Order;

class OrderConvertService extends AbstractHelper
{
    /**
     * @var DPDClient
     */
    private $DPDClient;
    /**
     * @var DpdSettings
     */
    private $dpdSettings;
    /**
     * @var OrderService
     */
    private $orderService;
    /**
     * @var ProductFactory
     */
    private $productFactory;

    /**
     * OrderConvertService constructor.
     * @param Context $context
     * @param DPDClient $DPDClient
     * @param OrderService $orderService
     * @param DpdSettings $dpdSettings
     * @param ProductFactory $productFactory
     */
    public function __construct(
        Context $context,
        DPDClient $DPDClient,
        OrderService $orderService,
        DpdSettings $dpdSettings,
        ProductFactory $productFactory
    ) {
        parent::__construct($context);
        $this->DPDClient = $DPDClient;
        $this->dpdSettings = $dpdSettings;
        $this->orderService = $orderService;
        $this->productFactory = $productFactory;
    }

    /**
     * @param bool $return
     * @return string
     */
    private function getProductCode(bool $return = false)
    {
        if ($return === true && !$this->orderService->isDPDSaturdayOrder()) {
            return 'RETURN';
        }

        if ($this->orderService->isDPDExpress10Order()) {
            return 'E10';
        }

        if ($this->orderService->isDPDExpress12Order()) {
            return 'E12';
        }

        if ($this->orderService->isDPDGuarantee18Order()) {
            return 'E18';
        }

        return 'CL';
    }


    public function getReceiverData(Order $order)
    {
        if ($this->orderService->isDPDPickupOrder()) {
            $billingAddress = $order->getBillingAddress();

            $street = $billingAddress->getStreet();
            $fullStreet = implode(' ', $street);

            $recipient = array(
                'name1'             => $billingAddress->getFirstname() . ' ' . $billingAddress->getLastname(),
                'name2'             => $billingAddress->getCompany(),
                'street'            => $fullStreet,
                'houseNo'           => '',
                'postalcode'        => strtoupper(str_replace(' ', '', $billingAddress->getPostcode())),
                'city'              => $billingAddress->getCity(),
                'country'           => $billingAddress->getCountryId(),
                'phoneNumber'       => $billingAddress->getTelephone(),
                'email'             => $order->getCustomerEmail(),
                'commercialAddress' => false
            );
        } else {
            $shippingAddress = $order->getShippingAddress();

            $street = $shippingAddress->getStreet();
            $fullStreet = implode(' ', $street);

            $recipient = array(
                'name1'             => $shippingAddress->getFirstname() . ' ' . $shippingAddress->getLastname(),
                'name2'             => $shippingAddress->getCompany(),
                'street'            => $fullStreet,
                'houseNo'           => '',
                'postalcode'        => strtoupper(str_replace(' ', '', $shippingAddress->getPostcode())),
                'city'              => $shippingAddress->getCity(),
                'country'           => $shippingAddress->getCountryId(),
                'phoneNumber'       => $shippingAddress->getTelephone(),
                'email'             => $order->getCustomerEmail(),
                'commercialAddress' => false
            );
        }

        return $recipient;
    }

    /**
     * @param Order $order
     * @return float
     */
    public function getOrderWeight(Order $order)
    {
        $orderWeight = $order->getWeight();

        $weightUnit = $this->scopeConfig->getValue('general/locale/weight_unit');
        if ($weightUnit == '') {
            $weightUnit = 'lbs';
        }

        if ($weightUnit == 'lbs') {
            $orderWeight *=  0.45359237;
        }

        // Weight is in KG so multiply with 100
        $orderWeight *= 100;

        if ($orderWeight == 0) {
            $orderWeight = 600;
        }

        return round($orderWeight, 0);
    }

    /**
     * @param Order $order
     * @param bool $isReturn
     * @param int $parcelAmount
     * @return array
     */
    private function addParcels(Order $order, bool $isReturn = false, int $parcelAmount = 1)
    {
        $parcels = [];

        for ($x = 1; $x <= $parcelAmount; $x++) {
            $orderWeight = $this->getOrderWeight($order) / $parcelAmount;
            $parcels[] = [
                'customerReferences' => [
                    $order->getIncrementId() ?? '',
                    ($this->dpdSettings->isSetFlag(DpdSettings::ADVANCED_PRINT_ORDER_ID) ? $order->getEntityId() : ''),
                    $order->getDpdParcelshopId() ?? ''
                ],
                'weight' => (int) $orderWeight,
                'returns' => $isReturn,
            ];
        }

        return $parcels;
    }

    private function addParcelsFromPackages(Order $order, $packages)
    {
        $parcels = [];

        foreach ($packages as $package) {

            $weight = floatval($package['weight'] ?? $package['params']['weight'] ?? 0);
            $unit = $package['weight_units'] ?? $package['params']['weight_units'] ?? \Zend_Measure_Weight::KILOGRAM;

            $unit = new \Zend_Measure_Weight($weight, $unit);
            $unit->convertTo(\Zend_Measure_Weight::KILOGRAM, 2);
            $weight = round(floatval($unit->getValue(2)) * 100, 0);
            $parcels[] = [
                'customerReferences' => [
                    ($this->dpdSettings->isSetFlag(DpdSettings::ADVANCED_PRINT_ORDER_ID) ? $order->getIncrementId() : ''),
                    $order->getDpdParcelshopId() ?? ''
                ],
                'weight' => (int)$weight,
            ];
        }
        return $parcels;
    }

    /**
     * @param Order $order
     * @param bool $isReturn
     * @param int $parcels
     * @return array
     */
    public function convert(Order $order, bool $isReturn = false, $packages = [], $useCustoms = false)
    {
        $this->orderService->setOrder($order);

        $shipment = [
            'orderId' => $order->getIncrementId(),
            'sendingDepot' => $this->dpdSettings->getValue(DpdSettings::ACCOUNT_DEPOT),
            'sender' => [
                'name1' => $this->dpdSettings->getValue(DpdSettings::SHIPPING_ORIGIN_NAME1),
                'street' => $this->dpdSettings->getValue(DpdSettings::SHIPPING_ORIGIN_STREET),
                'housenumber' => $this->dpdSettings->getValue(DpdSettings::SHIPPING_ORIGIN_HOUSE_NUMBER),
                'country' => $this->dpdSettings->getValue(DpdSettings::SHIPPING_ORIGIN_COUNTRY),
                'postalcode' => $this->dpdSettings->getValue(DpdSettings::SHIPPING_ORIGIN_ZIP_CODE),
                'city' => $this->dpdSettings->getValue(DpdSettings::SHIPPING_ORIGIN_CITY),
                'phoneNumber' => $this->dpdSettings->getValue(DpdSettings::STORE_INFORMATION_PHONE),
                'email' => $this->dpdSettings->getValue(DpdSettings::STORE_INFORMATION_EMAIL),
                'commercialAddress' => true,
                'vat_number' => $this->dpdSettings->getValue(DpdSettings::STORE_INFORMATION_VAT_NUMBER),
                'eori_number' => $this->dpdSettings->getValue(DpdSettings::STORE_INFORMATION_EORI),
                'sprn_number' => $this->dpdSettings->getValue(DpdSettings::STORE_INFORMATION_SPRN),
            ],
            'receiver' => $this->getReceiverData($order),
            'product' => [
                'productCode' => $this->getProductCode($isReturn),
                'saturdayDelivery' => ($this->orderService->isDPDSaturdayOrder() && !$isReturn),
                'homeDelivery' => $this->orderService->isDPDPredictOrder() || $this->orderService->isDPDSaturdayOrder()
            ]
        ];

        $shipment['customs'] = [
            'terms' => 'DAP',
            'totalCurrency' => $order->getOrderCurrencyCode(),
            'totalAmount' => (float) $order->getBaseGrandTotal(),
            'customsLines' => $this->addCustomsLines($order),
            'consignee' => [
                'name1' => $this->dpdSettings->getValue(DpdSettings::STORE_INFORMATION_NAME),
                'street' => $this->dpdSettings->getValue(DpdSettings::STORE_INFORMATION_STREET),
                'housenumber' => $this->dpdSettings->getValue(DpdSettings::STORE_INFORMATION_HOUSE_NUMBER),
                'postalcode' => $this->dpdSettings->getValue(DpdSettings::STORE_INFORMATION_ZIP_CODE),
                'city' => $this->dpdSettings->getValue(DpdSettings::STORE_INFORMATION_CITY),
                'country' => $this->dpdSettings->getValue(DpdSettings::STORE_INFORMATION_COUNTRY),
                'commercialAddress' => true,
            ],
            'consignor' => $this->getReceiverData($order),
        ];

        // The customs/packages popup when creating a new shipment is the only way to have multiple parcels for a single
        // shipment

        if (is_array($packages)) {
            $shipment['parcels'] = $this->addParcelsFromPackages($order, $packages);
        } else {
            $shipment['parcels'] = $this->addParcels($order, $isReturn, 1);
        }

        if (!$isReturn) {
            if ($this->orderService->isDPDPredictOrder() || $this->orderService->isDPDSaturdayOrder()) {
                $shipment['notifications'][] = [
                    'subject' => 'predict',
                    'channel' => 'EMAIL',
                    'value' => $order->getCustomerEmail(),
                ];
            }

            if ($this->orderService->isDPDPickupOrder()) {
                $parcelShopId = $order->getDpdParcelshopId();
                $shipment['product']['parcelshopId'] = $parcelShopId;
                $shipment['notifications'][] = [
                    'subject' => 'parcelshop',
                    'channel' => 'EMAIL',
                    'value' => $order->getCustomerEmail(),
                ];
            }
        }
        return $shipment;
    }

    /**
     * @param Order $order
     * @param bool $isReturn
     * @param int $parcelAmount
     * @return array
     */
    private function addCustomsLines(Order $order)
    {
        $customsLines = [];

        foreach ($order->getItems() as $item) {
            /** @var \Magento\Catalog\Model\Product $product */
            $product = $item->getProduct();
            $originCountry = $this->dpdSettings->getValue(DpdSettings::SHIPPING_ORIGIN_COUNTRY);
            $hsCode = '';

            if ($product !== null) {
                $originCountry = $product->getCountryOfManufacture() ?? $originCountry;
                $hsCode = $product->getHsCode() ?? '';
            }
            $customsLines[] = [
                'description' => mb_strcut($item['name'], 0, 35),
                'harmonizedSystemCode' => $hsCode,
                'originCountry' => $originCountry,
                'quantity' => (int) $item->getQtyOrdered(),
                'netWeight' => (int) $item->getWeight(),
                'grossWeight' => (int) $item->getWeight(),
                'totalAmount' => (float) ($item->getPriceInclTax()),
            ];
        }

        return $customsLines;
    }
}
