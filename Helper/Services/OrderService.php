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

use Magento\Framework\App\Helper\AbstractHelper;
use DpdConnect\Shipping\Helper\DPDClient;
use Magento\Sales\Model\Order;

class OrderService extends AbstractHelper
{
    /**
     * @var Order
     */
    private $order;

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
     * @return bool
     */
    public function isDPDPredictOrder()
    {
        $shippingMethod = $this->order->getShippingMethod();

        return ($shippingMethod === 'dpdpredict_dpdpredict');
    }

    /**
     * @return bool
     */
    public function isDPDPickupOrder()
    {
        $shippingMethod = $this->order->getShippingMethod();

        return ($shippingMethod === 'dpdpickup_dpdpickup');
    }

    /**
     * @return bool
     */
    public function isDPDSaturdayOrder()
    {
        $shippingMethod = $this->order->getShippingMethod();

        return ($shippingMethod === 'dpdsaturday_dpdsaturday');
    }

    /**
     * @return bool
     */
    public function isDPDClassicSaturdayOrder()
    {
        $shippingMethod = $this->order->getShippingMethod();

        return ($shippingMethod === 'dpdclassicsaturday_dpdclassicsaturday');
    }


    /**
     * @return bool
     */
    public function isDPDGuarantee18Order()
    {
        $shippingMethod = $this->order->getShippingMethod();

        return ($shippingMethod === 'dpdguarantee18_dpdguarantee18');
    }

    /**
     * @return bool
     */
    public function isDPDExpress12Order()
    {
        $shippingMethod = $this->order->getShippingMethod();

        return ($shippingMethod === 'dpdexpress12_dpdexpress12');
    }

    /**
     * @return bool
     */
    public function isDPDExpress10Order()
    {
        $shippingMethod = $this->order->getShippingMethod();

        return ($shippingMethod === 'dpdexpress10_dpdexpress10');
    }

    /**
     * @return bool
     */
    public function isDPDClassicOrder()
    {
        $shippingMethod = $this->order->getShippingMethod();

        return ($shippingMethod === 'dpdclassic_dpdclassic');
    }
}
