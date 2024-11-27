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
namespace DpdConnect\Shipping\Helper;

use DpdConnect\Shipping\Helper\Services\ShipmentLabelService;
use DpdConnect\Shipping\Services\BatchManager;
use DpdConnect\Shipping\Services\ShipmentManager;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Shipment\TrackFactory;
use Magento\Store\Model\ScopeInterface;
use Zend_Pdf;
use Zend_Pdf_Exception;
use Magento\Shipping\Model\ShipmentNotifier;

class Data extends AbstractHelper
{
    const DPD_GOOGLE_MAPS_API = 'carriers/dpdpickup/google_maps_api';

    /**
     * @var ShipmentLabelService
     */
    private $shipmentLabel;

    /**
     * @var TrackFactory
     */
    private $trackFactory;

    /**
     * @var DpdSettings
     */
    private $dpdSettings;
    /**
     * @var ShipmentManager
     */
    private $shipmentManager;
    /**
     * @var BatchManager
     */
    private $batchManager;
    /**
     * @var ShipmentNotifier
     */
    private $shipmentNotifier;

    /**
     * Data constructor.
     * @param Context $context
     * @param ShipmentLabelService $shipmentLabel
     * @param DpdSettings $dpdSettings
     * @param TrackFactory $trackFactory
     * @param ShipmentManager $shipmentManager
     * @param BatchManager $batchManager
     * @param ShipmentNotifier $shipmentNotifier
     */
    public function __construct(
        Context $context,
        ShipmentLabelService $shipmentLabel,
        DpdSettings $dpdSettings,
        TrackFactory $trackFactory,
        ShipmentManager $shipmentManager,
        BatchManager $batchManager,
        ShipmentNotifier $shipmentNotifier
    ) {
        $this->shipmentLabel = $shipmentLabel;
        $this->trackFactory = $trackFactory;
        $this->dpdSettings = $dpdSettings;
        $this->shipmentManager = $shipmentManager;
        $this->shipmentNotifier = $shipmentNotifier;

        parent::__construct($context);
        $this->batchManager = $batchManager;
    }

    public function getGoogleServerApiKey()
    {
        return $this->dpdSettings->getValue(DpdSettings::PARCELSHOP_MAPS_SERVER_KEY, ScopeInterface::SCOPE_STORE);
    }

    /**
     * @param Order $order
     * @param Order\Shipment|null $shipment
     * @param int $parcels
     * @param bool $isReturn
     * @return array
     * @throws AlreadyExistsException
     * @throws LocalizedException
     * @throws \DpdConnect\Sdk\Exceptions\RequestException
     */
    public function generateShippingLabel(Order $order, Order\Shipment $shipment = null, $parcels = 1, $isReturn = false)
    {
        $includeReturnLabel = $this->dpdSettings->isSetFlag(DpdSettings::ADVANCED_INCLUDE_RETURN_LABEL, ScopeInterface::SCOPE_STORE, $order->getStoreId());

        // New way of processing batch requests
        if($order->hasData(Constants::ORDER_EXTRA_SHIPPING_DATA) && !is_array($parcels)) {
            $pdfResult = [];
            $shipmentRows = $order->getData(Constants::ORDER_EXTRA_SHIPPING_DATA);
            foreach ($shipmentRows as $shipmentRow) {
                // Shipment is always NULL when shipment rows is set (ONLY used when using the batch method of creating labels)
                $shipment = $this->shipmentManager->createShipment($order, $shipmentRow);
                $shipment->setData(Constants::SHIPMENT_EXTRA_DATA, $shipmentRow); // Use this to move data around more efficiently

                // Create label for each row
                $resultLabels = $this->shipmentLabel->generateLabel(
                    $order,
                    $isReturn,
                    $shipment,
                    $parcels,
                    $includeReturnLabel
                );

                // Add the tracking numbers
                $parcelNumbers = [];
                foreach ($resultLabels as $resultLabel) {
                    foreach ($resultLabel['parcelNumbers'] as $parcelNumber) {
                        $parcelNumbers[] = $parcelNumber;
                    }
                }

                $this->shipmentManager->addTrackingNumbersToShipment($shipment, $parcelNumbers);

                // Add the labels to the result
                foreach ($resultLabels as $label) {
                    $pdfResult[] = base64_decode($label['label']);
                }
            }
        }

        // Default to the old method of making shipments
        else {
            // If no shipment is provided we create one (or if it exists we fetch the first one)
            if ($shipment === null) {
                $shipment = $this->shipmentManager->createShipment($order, null);
            }

            if (count($shipment->getPackages()) > 0) {

                $resultLabels = $this->shipmentLabel->generateLabelMultiPackage(
                    $order,
                    $isReturn,
                    $shipment,
                    $shipment->getPackages(),
                    $includeReturnLabel
                );
            } else {

                $resultLabels = $this->shipmentLabel->generateLabel(
                    $order,
                    $isReturn,
                    $shipment,
                    $parcels,
                    $includeReturnLabel
                );
            }
            //        $shipment->getResource()->save($shipment);

            $parcelNumbers = [];
            foreach ($resultLabels as $resultLabel) {
                foreach ($resultLabel['parcelNumbers'] as $parcelNumber) {
                    $parcelNumbers[] = $parcelNumber;
                }
            }


            $this->shipmentManager->addTrackingNumbersToShipment($shipment, $parcelNumbers);

            // Merge the pdf request if a return label was found
            $pdfResult = [];
            foreach ($resultLabels as $label) {
                $pdfResult[] = base64_decode($label['label']);
            }
        }

        $sendConfirmEmail = $this->dpdSettings->isSetFlag(DpdSettings::ADVANCED_SEND_CONFIRM_EMAIL, 'store', $order->getStoreId());
        if ($sendConfirmEmail) {
            $this->shipmentNotifier->notify($shipment);
        }

        return $pdfResult;
    }

    /**
     * @param array $orders
     * @return mixed
     */
    public function generateShippingLabelAsync(array $orders)
    {
        $includeReturnLabel = $this->dpdSettings->isSetFlag(DpdSettings::ADVANCED_INCLUDE_RETURN_LABEL);
        $jobs = $this->shipmentLabel->generateLabelAsync($orders, $includeReturnLabel);

        $batch = $this->batchManager->createNewBatch();

        foreach ($jobs as $job) {
            $this->batchManager->createNewJob($batch, $job['jobid']);
        }

        return $jobs;
    }


    /**
     * @param Order $order
     *
     * @return bool
     */
    public function isDPDOrder(Order $order)
    {
        $shippingMethod = $order->getShippingMethod();

        return (strpos($shippingMethod, 'dpd') === 0);
    }

    /**
     * @param Order $order
     *
     * @return bool
     */
    public function hasDpdFreshProducts(Order $order)
    {
        $orderItems = $order->getAllVisibleItems();
        foreach($orderItems as $orderItem) {
            if (in_array($orderItem->getProduct()->getData('dpd_shipping_type'), ['fresh', 'freeze'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Supply and array of binary PDF file and it'll combine them into one
     * @param array $pdfFiles
     * @return string
     * @throws Zend_Pdf_Exception
     */
    public function combinePDFFiles(array $pdfFiles)
    {
        error_reporting(E_ALL & ~E_DEPRECATED);
        $outputPdf = new Zend_Pdf();
        foreach ($pdfFiles as $content) {
            if (stripos($content, '%PDF-') !== false) {
                $pdfLabel = Zend_Pdf::parse($content);
                foreach ($pdfLabel->pages as $page) {
                    $outputPdf->pages[] = clone $page;
                }
            }
        }
        return $outputPdf->render();
    }
}
