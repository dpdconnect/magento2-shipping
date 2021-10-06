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

namespace DpdConnect\Shipping\Model\Carrier;

use DpdConnect\Shipping\Helper\DPDClient;
use DpdConnect\Shipping\Helper\DpdSettings;
use DpdConnect\Shipping\Helper\Services\OrderConvertService;
use DpdConnect\Shipping\Helper\Services\ShipmentLabelService;
use DpdConnect\Shipping\Model\ResourceModel\TablerateFactory;
use DpdConnect\Shipping\Services\ShipmentManager;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Locale\Resolver;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory;
use Magento\Quote\Model\Quote\Address\RateResult\MethodFactory;
use Magento\Shipping\Model\Rate\ResultFactory;
use Magento\Shipping\Model\Tracking\Result\StatusFactory;
use Psr\Log\LoggerInterface;

class Dpd extends AbstractCarrier implements
    \Magento\Shipping\Model\Carrier\CarrierInterface
{
    /**
     * @var string
     */
    protected $_code = 'dpd';

    /**
     * @var string
     */
    protected $_defaultConditionName = 'package_weight';

    private $checkoutSession;

    /**
     * Dpd constructor.
     *
     * @param ScopeConfigInterface                           $scopeConfig
     * @param Resolver                                       $localeResolver
     * @param ErrorFactory                                   $rateErrorFactory
     * @param LoggerInterface                                $logger
     * @param ResultFactory                                  $rateResultFactory
     * @param MethodFactory                                  $rateMethodFactory
     * @param TablerateFactory                               $tablerateFactory
     * @param \Magento\Shipping\Model\Tracking\ResultFactory $trackFactory
     * @param StatusFactory                                  $trackStatusFactory
     * @param ResultFactory                                  $rateFactory
     * @param DpdSettings                                    $dpdSettings
     * @param DPDClient                                      $dpdClient
     * @param OrderConvertService                            $orderConvertService
     * @param TimezoneInterface                              $timezoneInterface
     * @param ShipmentLabelService                           $shipmentLabelService
     * @param ShipmentManager                                $shipmentManager
     * @param \Magento\Checkout\Model\Session                $checkoutSession
     * @param array                                          $data
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        Resolver $localeResolver,
        ErrorFactory $rateErrorFactory,
        LoggerInterface $logger,
        ResultFactory $rateResultFactory,
        MethodFactory $rateMethodFactory,
        TablerateFactory $tablerateFactory,
        \Magento\Shipping\Model\Tracking\ResultFactory $trackFactory,
        StatusFactory $trackStatusFactory,
        ResultFactory $rateFactory,
        DpdSettings $dpdSettings,
        DPDClient $dpdClient,
        OrderConvertService $orderConvertService,
        TimezoneInterface $timezoneInterface,
        ShipmentLabelService $shipmentLabelService,
        ShipmentManager $shipmentManager,
        \Magento\Checkout\Model\Session $checkoutSession,
        array $data = []
    ) {
        parent::__construct(
            $scopeConfig,
            $localeResolver,
            $rateErrorFactory,
            $logger,
            $rateResultFactory,
            $rateMethodFactory,
            $tablerateFactory,
            $trackFactory,
            $trackStatusFactory,
            $rateFactory,
            $dpdSettings,
            $dpdClient,
            $orderConvertService,
            $timezoneInterface,
            $shipmentLabelService,
            $shipmentManager,
            $data
        );
        $this->checkoutSession = $checkoutSession;
    }


    /**
     * Needed for shipping and tracking information
     *
     * @return bool
     */
    public function isTrackingAvailable()
    {
        return true;
    }

    /**
     * @return array
     */
    public function getAllowedMethods()
    {
        return ['dpd' => $this->getConfigData('name')];
    }

    /**
     * @param $trackings
     *
     * @return \Magento\Shipping\Model\Tracking\Result
     */
    public function getTrackingInfo($trackings)
    {
        $result = $this->_trackStatusFactory->create();

        $carrierTitle = $this->_scopeConfig->getValue(
            'carriers/'.$this->_code.'/title',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );

        $result->setCarrier($this->_code);
        $result->setCarrierTitle($carrierTitle);
        $result->setTracking($trackings);
        $result->setUrl("https://tracking.dpd.de/status/{$this->_localeResolver->getLocale()}/parcel/".$trackings);

        return $result;
    }

    /**
     * @param \Magento\Framework\DataObject $request
     *
     * @return \Magento\Framework\DataObject|void
     */
    protected function _doShipmentRequest(\Magento\Framework\DataObject $request)
    {
        return $this;
    }

    /**
     * @param \Magento\Quote\Model\Quote\Address\RateRequest $request
     *
     * @return array|bool
     */
    public function getRate(\Magento\Quote\Model\Quote\Address\RateRequest $request)
    {
        return $this->_tablerateFactory->create()->getRate($request);
    }

    /**
     * @param RateRequest $request
     *
     * @return bool|Result
     */
    public function collectRates(RateRequest $request)
    {
        if (!$this->getConfigFlag('active')) {
            return false;
        }

        /** @var \Magento\Shipping\Model\Rate\Result $result */
        $result = $this->_rateResultFactory->create();

        /** @var \Magento\Quote\Model\Quote\Address\RateResult\Method $method */
        $method = $this->_rateMethodFactory->create();

        $method->setCarrier('dpd');
        $method->setCarrierTitle($this->getConfigData('title'));

        $method->setMethod('dpd');
        $method->setMethodTitle($this->getConfigData('name'));

        // Get the selected DPD Shipping Product to check its settings
        $quote = $this->checkoutSession->getQuote();
        $dpdShippingProduct = $quote->getDpdShippingProduct();

        $dpdProductSettings = $this->dpdSettings->getDpdCarrierCustomerProductSettings();

        // Check if atleast one customer product is enabled
        $atleastOneEnabled = false;
        foreach($dpdProductSettings as $key => $data) {
            if (isset($data['enabled']) && '1' === $data['enabled']) {
                $atleastOneEnabled = true;
                break;
            }
        }

        // Disable this shipping method when no customer products are enabled
        if (false === $atleastOneEnabled) {
            return false;
        }

        $selectedDpdProductSettings = [];
        if (isset($dpdProductSettings[$dpdShippingProduct])) {
            $selectedDpdProductSettings = $dpdProductSettings[$dpdShippingProduct];

            if (isset($selectedDpdProductSettings['title'])) {
                $method->setCarrierTitle($selectedDpdProductSettings['title']);
            }
        }

        if (isset($selectedDpdProductSettings['rateType']) && 'table' === $selectedDpdProductSettings['rateType']) {
            // Possible bug in Magento, new sessions post no data when fetching the shipping methods, only country_id: US
            // This prevents the tablerates from showing a 0,00 shipping price
            if (!$request->getDestPostcode() && 'US' === $request->getDestCountryId()) {
                return false;
            }

            // Free shipping by qty
            $freeQty = 0;
            $freePackageValue = 0;
            if ($request->getAllItems()) {
                foreach ($request->getAllItems() as $item) {
                    if ($item->getProduct()->isVirtual() || $item->getParentItem()) {
                        continue;
                    }
                    if ($item->getHasChildren() && $item->isShipSeparately()) {
                        foreach ($item->getChildren() as $child) {
                            if ($child->getFreeShipping() && !$child->getProduct()->isVirtual()) {
                                $freeShipping = is_numeric($child->getFreeShipping()) ? $child->getFreeShipping() : 0;
                                $freeQty += $item->getQty() * ($child->getQty() - $freeShipping);
                            }
                        }
                    } elseif ($item->getFreeShipping()) {
                        $freeShipping = is_numeric($item->getFreeShipping()) ? $item->getFreeShipping() : 0;
                        $freeQty += $item->getQty() - $freeShipping;
                        $freePackageValue += $item->getBaseRowTotal();
                    }
                }
                $oldValue = $request->getPackageValue();
                $request->setPackageValue($oldValue - $freePackageValue);
            }

            $request->setConditionName(isset($selectedDpdProductSettings['conditionName']) ? $selectedDpdProductSettings['conditionName'] : $this->_defaultConditionName);
            $oldQty = $request->getPackageQty();

            $request->setPackageWeight($request->getFreeMethodWeight());
            $request->setPackageQty($oldQty - $freeQty);
            $request->setShippingMethod('dpd_'.$dpdShippingProduct);

            $rate = $this->getRate($request);

            $shippingPrice = $rate['price'];
            if (true === $request->getFreeShipping()) {
                $shippingPrice = 0;
            }
            $method->setPrice($shippingPrice);
            $method->setCost($rate['cost']);
        } else {
            $shippingProductSettings = $this->dpdSettings->getDpdCarrierCustomerProductSettings();
            $quote = $this->checkoutSession->getQuote();

            if ($quote->getDpdShippingProduct() && isset($shippingProductSettings[$quote->getDpdShippingProduct()])) {
                $amount = $shippingProductSettings[$quote->getDpdShippingProduct()]['price'];
            } else {
                // Default to the config price
                $amount = $this->getConfigData('price');
            }

            if (true === $request->getFreeShipping()) {
                $amount = 0;
            }

            $method->setPrice($amount);
            $method->setCost($amount);
        }

        $result->append($method);

        return $result;
    }
}
