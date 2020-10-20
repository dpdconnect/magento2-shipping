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

use Magento\Framework\Event\ObserverInterface;
use Magento\Quote\Model\QuoteRepository;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\OrderRepository;

class SalesOrderSaveBefore implements ObserverInterface
{
    /**
     * @var QuoteRepository
     */
    private $quoteRepository;

    /**
     * @var \Magento\Framework\App\State
     */
    private $state;

    public function __construct(
        QuoteRepository $quoteRepository,
        \Magento\Framework\App\State $state
    ) {
        $this->quoteRepository = $quoteRepository;
        $this->state = $state;
    }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
        // Ignore adminhtml
        if ($this->state->getAreaCode() == \Magento\Framework\App\Area::AREA_ADMINHTML) {
            return;
        }

        $order = $observer->getEvent()->getOrder();
        /** @var Order $order */

        if ($order->getShippingMethod() != 'dpdpickup_dpdpickup') {
            return;
        }

        $quoteId = $order->getQuoteId();
        try {
            $quote = $this->quoteRepository->get($quoteId);
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            $quote = null;
        }

        // Happens when the order has already been placed in which caes this event has already
        // been called succesfully
        if ($quote == null) {
            return;
        }

        // Happens when editing old orders before 1.0.7
        if ($quote->getDpdParcelshopId() == '') {
            return;
        }

        $order->setDpdParcelshopId($quote->getDpdParcelshopId());
        $order->setDpdCompany($quote->getData('dpd_parcelshop_name'));
        $order->setDpdStreet($quote->getData('dpd_parcelshop_street'));
        $order->setDpdZipcode($quote->getData('dpd_parcelshop_zip_code'));
        $order->setDpdCity($quote->getData('dpd_parcelshop_city'));
        $order->setDpdCountry($quote->getData('dpd_parcelshop_country'));
    }
}
