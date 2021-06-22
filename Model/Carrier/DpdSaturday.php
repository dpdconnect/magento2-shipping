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

use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Shipping\Model\Rate\Result;

class DpdSaturday extends AbstractCarrier implements
    \Magento\Shipping\Model\Carrier\CarrierInterface
{
    /**
     * @var string
     */
    protected $_code = 'dpdsaturday';

    /**
     * @var string
     */
    protected $_defaultConditionName = 'package_weight';

    /**
     * @var array
     */
    private $weekNames = [
        1 => 'monday',
        2 => 'tuesday',
        3 => 'wednesday',
        4 => 'thursday',
        5 => 'friday',
        6 => 'saturday',
        7 => 'sunday'
    ];

    /**
     * Needed for shipping and tracking information
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
        return ['dpdsaturday' => $this->getConfigData('name')];
    }

    /**
     * @param $trackings
     * @return \Magento\Shipping\Model\Tracking\Result
     */
    public function getTrackingInfo($trackings)
    {
        $result = $this->_trackStatusFactory->create();

        $carrierTitle = $this->_scopeConfig->getValue(
            'carriers/' . $this->_code  . '/title',
            \Magento\Store\Model\ScopeInterface::SCOPE_STORE
        );

        $result->setCarrier($this->_code);
        $result->setCarrierTitle($carrierTitle);
        $result->setTracking($trackings);
        $result->setUrl("https://tracking.dpd.de/status/{$this->_localeResolver->getLocale()}/parcel/" . $trackings);

        return $result;
    }

    /**
     * @param \Magento\Framework\DataObject $request
     * @return \Magento\Framework\DataObject|void
     */
    protected function _doShipmentRequest(\Magento\Framework\DataObject $request)
    {
        return $this;
    }

    /**
     * @param \Magento\Quote\Model\Quote\Address\RateRequest $request
     * @return array|bool
     */
    public function getRate(\Magento\Quote\Model\Quote\Address\RateRequest $request)
    {
        return $this->_tablerateFactory->create()->getRate($request);
    }

    /**
     * @param RateRequest $request
     * @return bool|Result
     */
    public function collectRates(RateRequest $request)
    {
        if (!$this->getConfigFlag('active')) {
            return false;
        }

        $shownFromDay = $this->getConfigData('shown_from_day');
        $shownFromTime = $this->getConfigData('shown_from_day_time');
        $shownTillDay = $this->getConfigData('shown_till_day');
        $shownTillTime = $this->getConfigData('shown_till_day_time');


        $showfromtime = explode(':', $shownFromTime);
        $firstDate = new \DateTime($this->weekNames[$shownFromDay] . ' this week');
        $firstDate->setTime($showfromtime[0], $showfromtime[1], 0);
        $firstDate = $firstDate->getTimestamp();

        $showtilltime = explode(':', $shownTillTime);
        $lastDate = new \DateTime($this->weekNames[$shownTillDay] . ' this week');
        $lastDate->setTime($showtilltime[0], $showtilltime[1], 0);
        $lastDate = $lastDate->getTimestamp();

        $today = $this->_timezoneInterface->scopeTimeStamp();

        if (!($today >= $firstDate && $today <= $lastDate)) {
            return false;
        }

        /** @var \Magento\Shipping\Model\Rate\Result $result */
        $result = $this->_rateResultFactory->create();

        /** @var \Magento\Quote\Model\Quote\Address\RateResult\Method $method */
        $method = $this->_rateMethodFactory->create();

        $method->setCarrier('dpdsaturday');
        $method->setCarrierTitle($this->getConfigData('title'));

        $method->setMethod('dpdsaturday');
        $method->setMethodTitle($this->getConfigData('name'));

        if ($this->getConfigData('rate_type') == 'table') {
            // Possible bug in Magento, new sessions post no data when fetching the shipping methods, only country_id: US
            // This prevents the tablerates from showing a 0,00 shipping price
            if (!$request->getDestPostcode() && $request->getDestCountryId() == 'US') {
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

            $conditionName = $this->_scopeConfig->getValue(
                'carriers/dpdsaturday/condition_name',
                \Magento\Store\Model\ScopeInterface::SCOPE_WEBSITE,
                $request->getWebsiteId()
            );
            $request->setConditionName($conditionName ? $conditionName : $this->_defaultConditionName);

            // Package weight and qty free shipping
            $oldWeight = $request->getPackageWeight();
            $oldQty = $request->getPackageQty();

            $request->setPackageWeight($request->getFreeMethodWeight());
            $request->setPackageQty($oldQty - $freeQty);

            $request->setShippingMethod('dpdsaturday');
            $rate = $this->getRate($request);

            $method->setPrice($rate['price']);
            $method->setCost($rate['cost']);
        } else {
            /*you can fetch shipping price from different sources over some APIs, we used price from config.xml - xml node price*/
            $amount = $this->getConfigData('price');

            $method->setPrice($amount);
            $method->setCost($amount);
        }

        $result->append($method);

        return $result;
    }
}
