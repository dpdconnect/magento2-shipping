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
namespace DpdConnect\Shipping\Observer;

use DpdConnect\Shipping\Helper\Data;
use Magento\Framework\Event\ObserverInterface;

class SalesOrderShipmentSaveBefore implements ObserverInterface
{
    /**
     * @var \Magento\Framework\App\State
     */
    private $state;

    /**
     * @var Data
     */
    private $dataHelper;

    /**
     * @param Data $dataHelper
     * @param \Magento\Framework\App\State $state
     */
    public function __construct(
        Data $dataHelper,
        \Magento\Framework\App\State $state
    ) {
        $this->state = $state;
        $this->dataHelper = $dataHelper;
    }

    /**
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Exception
     */
    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        // Ignore frontend
        if ($this->state->getAreaCode() === \Magento\Framework\App\Area::AREA_FRONTEND) {
            return;
        }

        $shipment = $observer->getShipment();
        if (false === $this->dataHelper->isDPDOrder($shipment->getOrder())) {
            return;
        }

        /** @var \Magento\Framework\UrlInterface $urlInterface */
        $urlInterface = \Magento\Framework\App\ObjectManager::getInstance()->get('Magento\Framework\UrlInterface');
        if (false !== stripos($urlInterface->getCurrentUrl(), 'dpd_shipping')) {
            return;
        }

        if (true === $this->dataHelper->hasDpdFreshProducts($shipment->getOrder())) {
            throw new \Exception('This order has DPD Fresh/Freeze products, shipments can only be made through the order overview or the packages screen.');
        }
    }
}
