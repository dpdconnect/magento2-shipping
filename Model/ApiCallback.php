<?php

namespace DpdConnect\Shipping\Model;

use DpdConnect\Shipping\Api\ApiCallbackInterface;
use DpdConnect\Shipping\Helper\Services\ShipmentLabelService;
use DpdConnect\Shipping\Services\BatchManager;
use DpdConnect\Shipping\Services\ShipmentManager;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;

class ApiCallback implements ApiCallbackInterface
{
    /**
     * @var BatchManager
     */
    private $batchManager;

    /**
     * @var OrderCollectionFactory
     */
    private $orderCollectionFactory;
    /**
     * @var ShipmentLabelService
     */
    private $shipmentLabelService;
    /**
     * @var ShipmentManager
     */
    private $shipmentManager;

    /**
     * ApiCallback constructor.
     * @param BatchManager $batchManager
     * @param OrderCollectionFactory $orderCollectionFactory
     * @param ShipmentLabelService $shipmentLabelService
     * @param ShipmentManager $shipmentManager
     */
    public function __construct(
        BatchManager $batchManager,
        OrderCollectionFactory $orderCollectionFactory,
        ShipmentLabelService $shipmentLabelService,
        ShipmentManager $shipmentManager
    ) {
        $this->batchManager = $batchManager;
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->shipmentLabelService = $shipmentLabelService;
        $this->shipmentManager = $shipmentManager;
    }

    /**
     * @return mixed
     */
    public function sendCallback()
    {
        $body = file_get_contents('php://input');
        $data = json_decode($body, true);

        $orderId = $data['shipment']['orderId'];
        $jobId = $data['jobid'];
        $parcelNumber = $data['shipment']['trackingInfo']['parcelNumbers'][0];
        $shipmentIdentifier = $data['shipment']['trackingInfo']['shipmentIdentifier'];
        $isReturn = $data['shipment']['product']['productCode'] === 'RETURN';
        $errors = $data['error'];

        $orderCollection = $this->orderCollectionFactory->create();
        /** @var Order $order */
        $order = $orderCollection->addFieldToFilter('increment_id', $orderId)->getFirstItem();
        $job = $this->batchManager->getJobById($jobId);
        $batch = $this->batchManager->getBatchById($job->getBatchId());

        // Get the correct shipment
        $shipment = null;
        if (isset($data['shipment']['parcels'][0]['customerReferences'][3])) {
            $shipmentId = $data['shipment']['parcels'][0]['customerReferences'][3];
            if (is_numeric($shipmentId)) {
                $shipment = $order->getShipmentsCollection()->getItemById($shipmentId);
            }
        }

        // Default to the first shipment
        if (null === $shipment) {
            $shipment = $order->getShipmentsCollection()->getFirstItem();
        }

        $job->setOrderId($order->getEntityId());
        $job->setOrderIncrementId($order->getIncrementId());
        $job->setShipmentId($shipment->getEntityId());
        $job->setShipmentIncrementId($shipment->getIncrementId());

        if (count($errors) > 0) {
            $batch->setStatus(BatchManager::STATUS_FAILED);
            $job->setStatus(BatchManager::STATUS_FAILED);
            $batch->getResource()->save($batch);
            $job->getResource()->save($job);
            return;
        }

        $label = $this->shipmentLabelService->getLabel($parcelNumber);
        $this->shipmentLabelService->saveLabel($order, $shipment, $shipmentIdentifier, [$parcelNumber], $label, $isReturn);

        $this->shipmentManager->addTrackingNumbersToShipment($shipment, [$parcelNumber]);

        $job->setStatus(BatchManager::STATUS_SUCCESS);
        $batch->setStatus(BatchManager::STATUS_SUCCESS);
        $batch->getResource()->save($batch);
        $job->getResource()->save($job);
        return;
    }
}
