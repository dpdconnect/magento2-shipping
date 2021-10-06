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

use DpdConnect\Shipping\Helper\Constants;
use Magento\Framework\App\Helper\AbstractHelper;
use DpdConnect\Shipping\Helper\DPDClient;
use Magento\Framework\App\Helper\Context;
use Magento\Sales\Model\Order;

/**
 * Class OrderService
 *
 * @package DpdConnect\Shipping\Helper\Services
 */
class OrderService extends AbstractHelper
{
    /**
     * @var Order
     */
    private $order;

    /**
     * @var null|Order\Shipment
     */
    private $shipment = null;

    /**
     * @var DPDClient
     */
    private $DPDClient;

    /**
     * OrderService constructor.
     *
     * @param Context   $context
     * @param DPDClient $DPDClient
     */
    public function __construct(Context $context, DPDClient $DPDClient)
    {
        parent::__construct($context);
        $this->DPDClient = $DPDClient;
    }

    /**
     * @param Order $order
     * @return OrderService
     */
    public function setOrder(Order $order)
    {
        $this->order = $order;

        return $this;
    }

    /**
     * @param Order\Shipment $shipment
     * @return $this
     */
    public function setShipment(Order\Shipment $shipment)
    {
        $this->shipment = $shipment;

        return $this;
    }

    /**
     * @return bool
     */
    public function isAgeCheckOrder()
    {
        $orderItems = $this->order->getAllVisibleItems();
        foreach($orderItems as $orderItem) {
            if($orderItem->getProduct()->getAgeCheck()) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return bool
     */
    public function isDPDPredictOrder()
    {
        // Added for backwards compatibility
        if (Constants::CARRIER_DPD !== $this->order->getShippingMethod()) {
            return 'dpdpredict_dpdpredict' === $this->order->getShippingMethod();
        }

        return in_array($this->getSelectedCode(), ['B2B MSG option', 'B2C', 'B2C6']);
    }

    /**
     * @return bool
     */
    public function isDPDPickupOrder()
    {
        // Added for backwards compatibility
        if (Constants::CARRIER_DPD !== $this->order->getShippingMethod() && (!$this->shipment && $this->shipment->hasData(Constants::SHIPMENT_EXTRA_DATA))) {
            return Constants::CARRIER_PARCELSHOP === $this->order->getShippingMethod();
        }

        $availableProducts = $this->DPDClient->authenticate()->getProduct()->getList();
        $selectedCode = $this->getSelectedCode();

        $selectedProduct = null;
        foreach($availableProducts as $availableProduct) {
            if ($availableProduct['code'] === $selectedCode) {
                $selectedProduct = $availableProduct;
                break;
            }
        }

        return ('parcelshop' === $selectedProduct['type']);
    }

    /**
     * @return bool
     */
    public function isDPDSaturdayOrder()
    {
        // Added for backwards compatibility
        if (Constants::CARRIER_DPD !== $this->order->getShippingMethod()) {
            return 'dpdsaturday_dpdsaturday' === $this->order->getShippingMethod();
        }

        return in_array($this->getSelectedCode(), ['B2C6', '6']);
    }

    /**
     * @return bool
     */
    public function isDPDClassicSaturdayOrder()
    {
        // Added for backwards compatibility
        if (Constants::CARRIER_DPD !== $this->order->getShippingMethod()) {
            return 'dpdclassicsaturday_dpdclassicsaturday' === $this->order->getShippingMethod();
        }

        return in_array($this->getSelectedCode(), ['6']);
    }


    /**
     * @return bool
     */
    public function isDPDGuarantee18Order()
    {
        // Added for backwards compatibility
        if (Constants::CARRIER_DPD !== $this->order->getShippingMethod()) {
            return 'dpdguarantee18_dpdguarantee18' === $this->order->getShippingMethod();
        }

        return in_array($this->getSelectedCode(), ['PM2']);
    }

    /**
     * @return bool
     */
    public function isDPDExpress12Order()
    {
        // Added for backwards compatibility
        if (Constants::CARRIER_DPD !== $this->order->getShippingMethod()) {
            return 'dpdexpress12_dpdexpress12' === $this->order->getShippingMethod();
        }

        return in_array($this->getSelectedCode(), ['AM2']);
    }

    /**
     * @return bool
     */
    public function isDPDExpress10Order()
    {
        // Added for backwards compatibility
        if (Constants::CARRIER_DPD !== $this->order->getShippingMethod()) {
            return 'dpdexpress10_dpdexpress10' === $this->order->getShippingMethod();
        }

        return in_array($this->getSelectedCode(), ['AM1']);
    }

    /**
     * @return bool
     */
    public function isDPDClassicOrder()
    {
        // Added for backwards compatibility
        if (Constants::CARRIER_DPD !== $this->order->getShippingMethod()) {
            return 'dpdclassic_dpdclassic' === $this->order->getShippingMethod();
        }

        return in_array($this->getSelectedCode(), ['B2B']);
    }

    /**
     * @return mixed
     */
    private function getSelectedCode()
    {
        if ($this->shipment && $this->shipment->hasData(Constants::SHIPMENT_EXTRA_DATA)) {
            return $this->shipment->getData(Constants::SHIPMENT_EXTRA_DATA)['code'];
        }

        return $this->order->getDpdShippingProduct();
    }
}
