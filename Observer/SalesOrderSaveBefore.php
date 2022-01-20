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
            $this->processAdminHtml($observer->getEvent()->getOrder());
            return;
        }

        $this->processFrontend($observer->getEvent()->getOrder());
    }

    /**
     * @param Order $order
     */
    private function processAdminHtml(Order $order)
    {
        $shippingMethod = $order->getShippingMethod();
        if (false === strpos($shippingMethod, 'dpd_')) {
            return;
        }

        if ('dpd_dpd' === $shippingMethod) {
            return;
        }

        $order->setShippingMethod('dpd_dpd');

        $shippingMethodParts = explode('_', $shippingMethod);
        $order->setDpdShippingProduct($shippingMethodParts[1]);
    }

    /**
     * @param Order $order
     */
    private function processFrontend(Order $order)
    {
        if (false === in_array($order->getShippingMethod(), ['dpd_dpd', 'dpdpickup_dpdpickup'])) {
            return;
        }

        $quoteId = $order->getQuoteId();

        try {
            $quote = $this->quoteRepository->get($quoteId);
        } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
            // Happens when the order has already been placed in which case this event has already
            // been called succesfully
            return;
        }

        if ('dpdpickup_dpdpickup' === $order->getShippingMethod()) {
            // Happens when editing old orders before 1.0.7
            if ($quote->getData('dpd_parcelshop_id') == '') {
                return;
            }

            $order->setDpdParcelshopId($quote->getData('dpd_parcelshop_id'));
            $order->setDpdParcelshopName($quote->getData('dpd_parcelshop_name'));
            $order->setDpdParcelshopStreet($quote->getData('dpd_parcelshop_street'));
            $order->setDpdParcelshopHouseNumber($quote->getData('dpd_parcelshop_house_number'));
            $order->setDpdParcelshopZipCode($quote->getData('dpd_parcelshop_zip_code'));
            $order->setDpdParcelshopCity($quote->getData('dpd_parcelshop_city'));
            $order->setDpdParcelshopCountry($quote->getData('dpd_parcelshop_country'));
        }

        if ('dpd_dpd' === $order->getShippingMethod()) {
            $order->setDpdShippingProduct($quote->getData('dpd_shipping_product'));
        }
    }
}
