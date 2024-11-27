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

use DpdConnect\Sdk\Exceptions\RequestException;
use DpdConnect\Shipping\Helper\Constants;
use DpdConnect\Shipping\Helper\DPDClient;
use DpdConnect\Shipping\Helper\DpdSettings;
use DpdConnect\Shipping\Model\ShipmentLabelFactory;
use DpdConnect\Shipping\Services\ShipmentManager;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\Filesystem\Io\File;
use Magento\Framework\Url;
use Magento\Sales\Model\Order;
use Magento\Store\Model\ScopeInterface;

class ShipmentLabelService extends AbstractHelper
{
    /**
     * @var \DpdConnect\Shipping\Model\ShipmentLabelFactory
     */
    private $shipmentLabelFactory;

    /**
     * @var DPDClient
     */
    private $dpdClient;

    /**
     * @var OrderService
     */
    private $orderService;
    /**
     * @var OrderConvertService
     */
    private $orderConvertService;
    /**
     * @var DpdSettings
     */
    private $dpdSettings;
    /**
     * @var DirectoryList
     */
    private $directoryList;
    /**
     * @var File
     */
    private $filesystem;
    /**
     * @var Url
     */
    private $urlHelper;

    /**
     * @var ShipmentManager
     */
    private $shipmentManager;

    /**
     * ShipmentLabelService constructor.
     * @param Context $context
     * @param DPDClient $DPDClient
     * @param OrderService $orderService
     * @param OrderConvertService $orderConvertService
     * @param DpdSettings $dpdSettings
     * @param ShipmentLabelFactory $shipmentLabelFactory
     * @param ShipmentManager $shipmentManager
     * @param DirectoryList $directoryList
     * @param File $filesystem
     * @param Url $urlHelper
     */
    public function __construct(
        Context $context,
        DPDClient $DPDClient,
        OrderService $orderService,
        OrderConvertService $orderConvertService,
        DpdSettings $dpdSettings,
        ShipmentLabelFactory $shipmentLabelFactory,
        ShipmentManager $shipmentManager,
        DirectoryList $directoryList,
        File $filesystem,
        Url $urlHelper
    ) {
        $this->shipmentLabelFactory = $shipmentLabelFactory;
        $this->dpdClient = $DPDClient;
        $this->orderService = $orderService;
        $this->orderConvertService = $orderConvertService;
        $this->dpdSettings = $dpdSettings;
        $this->directoryList = $directoryList;
        $this->filesystem = $filesystem;
        parent::__construct($context);
        $this->urlHelper = $urlHelper;
        $this->shipmentManager = $shipmentManager;
    }

    public function saveLabel($order, $shipment, $shipmentIdentifier, $parcelNumbers, $labelData, $isReturn)
    {
        $saveLabelAsFile = $this->dpdSettings->isSetFlag(DpdSettings::ADVANCED_SAVE_LABEL_FILE);
        $labelPath = $this->dpdSettings->getValue(DpdSettings::ADVANCED_LABEL_PATH);

        if (empty($labelPath)) {
            $labelPath = $this->directoryList->getRoot() . '/var/dpd_labels/';
        }

        $labelPath = rtrim($labelPath, '/') . '/';

        $labelName = sprintf('%s-%s.pdf', $order->getIncrementId(), $order->getEntityId());

        $carrierCode = explode("_", $order->getShippingMethod())[0];

        // Save the label to the database
        $shipmentLabel = $this->shipmentLabelFactory->create();
        $shipmentLabel->setOrderId($order->getId());
        $shipmentLabel->setOrderIncrementId($order->getIncrementId());
        $shipmentLabel->setShipmentId($shipment->getId());
        $shipmentLabel->setShipmentIncrementId($shipment->getIncrementId());
        $shipmentLabel->setCarrierCode($order->getShippingMethod());
        $shipmentLabel->setLabelNumbers(serialize($parcelNumbers));
        $shipmentLabel->setMpsId($shipmentIdentifier);

        if (!$saveLabelAsFile) {
            $shipmentLabel->setLabel($labelData);
        } else {
            $shipmentLabel->setLabelPath($labelPath . $labelName);
        }

        $shipmentLabel->setIsReturn($isReturn);
        $shipmentLabel->save();

        // Write file to a directory
        if ($saveLabelAsFile) {
            if (!file_exists($labelPath)) {
                mkdir($labelPath, 0755, true);
            }
            $this->filesystem->write($labelPath . $labelName, $labelData);
        }
    }

    /**
     * @param Order $order
     * @param bool $isReturn
     * @param Order\Shipment $shipment
     * @param int $parcels
     * @param bool $includeReturn
     * @return array
     * @throws RequestException
     */
    public function generateLabelMultiPackage(Order $order, $isReturn = false, Order\Shipment $shipment = null, $parcels = [], bool $includeReturn = false)
    {
        $this->orderService->setOrder($order);

        $shipmentData[] = $this->orderConvertService->convert($order, $shipment, $isReturn, $parcels);
        if ($includeReturn) {
            $shipmentData[] = $this->orderConvertService->convert($order, $shipment, $includeReturn, $parcels);
        }

        $result = $this->createShipment($shipmentData, $order);
        $labels = $result->getContent()['labelResponses'];

        foreach ($labels as $label) {
            $parcelNumbers = [];

            $labelData = base64_decode($label['label']);
            $shipmentIdentifier = $label['shipmentIdentifier'];

            // Format the label numbers before saving it
            foreach($label['parcelNumbers'] as $number) {
                $parcelNumbers[] = [
                    'parcel_number' => $number,
                    'weight' => 0
                ];
            }

            // Add the weigh tto the parcel numbers data used in shipping lists
            for($i = 0; $i < count($shipmentData[0]['parcels']); $i++) {
                if(count($parcelNumbers) <= $i) {
                    continue;
                }

                $parcelNumbers[$i]['weight'] = $shipmentData[0]['parcels'][$i]['weight'];
            }

            $this->saveLabel($order, $shipment, $shipmentIdentifier, $parcelNumbers, $labelData, $isReturn);

        }
        return $labels;
    }

    /**
     * @param Order $order
     * @param bool $isReturn
     * @param Order\Shipment $shipment
     * @param int $parcels
     * @param bool $includeReturn
     * @return array
     * @throws RequestException
     */
    public function generateLabel(Order $order, $isReturn = false, Order\Shipment $shipment = null, $parcels = 1, bool $includeReturn = false)
    {
        $this->orderService->setOrder($order);

        $shipmentData[] = $this->orderConvertService->convert($order, $shipment, $isReturn, $parcels);
        if ($includeReturn) {
            $shipmentData[] = $this->orderConvertService->convert($order, $shipment, $includeReturn, $parcels);
        }

        $weight = $this->orderConvertService->getOrderWeight($order);

        $result = $this->createShipment($shipmentData, $order);
        $labels = $result->getContent()['labelResponses'];


        foreach ($labels as $label) {
            $labelData = base64_decode($label['label']);
            $shipmentIdentifier = $label['shipmentIdentifier'];
            $parcelNumbers = [];

            foreach($label['parcelNumbers'] as $number) {
                $parcelNumbers[] = [
                    'parcel_number' => $number,
                    'weight' => $weight
                ];
            }

            $this->saveLabel($order, $shipment, $shipmentIdentifier, $parcelNumbers, $labelData, $isReturn);
        }
        return $labels;
    }

    public function generateLabelAsync(array $orders, $includeReturn)
    {
        $shipmentData = [];
        foreach ($orders as $order) {
            $shipmentRows = $order->getData(Constants::ORDER_EXTRA_SHIPPING_DATA);
            foreach ($shipmentRows as $shipmentRow) {
                // Gets the first shipment OR creates a new shipment
                $shipment = $this->shipmentManager->createShipment($order, $shipmentRow);

                $shipmentData[] = $this->orderConvertService->convert($order, $shipment, false, 1);
                if ($includeReturn) {
                    $shipmentData[] = $this->orderConvertService->convert($order, $shipment, $includeReturn, 1);
                }
            }
        }

        $result = $this->createShipmentAsync($shipmentData);
        $responseData = $result->getContent();

        return $responseData;
    }

    public function getLabel($parcelNumber)
    {
        $dpdClient = $this->dpdClient->authenticate();
        return $dpdClient->getParcel()->getLabel($parcelNumber);
    }

    private function createShipmentAsync(array $shipmentData)
    {
        $dpdClient = $this->dpdClient->authenticate();

        $callbackUrl = $this->urlHelper->getUrl('rest/default/V1/dpd-shipping/callback', ['_nosid' => true]);

        $request = [
            'callbackURI' => $callbackUrl,
            'label' => [
                'printOptions' => [
                    'printerLanguage' => 'PDF',
                    'paperFormat' => $this->dpdSettings->getValue(DpdSettings::ACCOUNT_PRINT_FORMAT),
                    'verticalOffset' => 0,
                    'horizontalOffset' => 0,
                ],
                'createLabel' => true,
                'shipments' => $shipmentData
            ]
        ];

        try {
            return $dpdClient->getShipment()->createAsync($request);
        } catch (RequestException $e) {
            foreach ($e->getErrorDetails()->errors as $detail) {
                if (isset($detail['_embedded']['errors'][0]['message'])) {
                    $errorMessage = $detail['_embedded']['errors'][0]['message'];
                    throw new \Exception($errorMessage);
                }
            }
            throw $e;
        }
    }

    /**
     * @param array $shipmentData
     * @param Order $order
     * @return \DpdConnect\Sdk\Common\ResourceClient|int
     * @throws RequestException
     */
    private function createShipment(array $shipmentData, Order $order)
    {
        $dpdClient = $this->dpdClient->authenticate($order->getStoreId());

        $request = [
            'printOptions' => [
                'printerLanguage' => 'PDF',
                'paperFormat' => $this->dpdSettings->getValue(DpdSettings::ACCOUNT_PRINT_FORMAT, ScopeInterface::SCOPE_STORE, $order->getStoreId()),
                'verticalOffset' => 0,
                'horizontalOffset' => 0,
            ],
            'createLabel' => true,
            'shipments' => $shipmentData
        ];

        try {
            return $dpdClient->getShipment()->create($request);
        } catch (RequestException $e) {
            foreach ($e->getErrorDetails()->errors as $detail) {
                if (isset($detail['_embedded']['errors'][0]['message'])) {
                    $errorMessage = $detail['_embedded']['errors'][0]['message'];
                    throw new \Exception($errorMessage);
                }
            }
            throw $e;
        }
    }
}
