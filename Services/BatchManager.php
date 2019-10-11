<?php

namespace DpdConnect\Shipping\Services;

use DpdConnect\Shipping\Helper\Services\ShipmentLabelService;
use DpdConnect\Shipping\Model\Batch;
use DpdConnect\Shipping\Model\BatchFactory;
use DpdConnect\Shipping\Model\BatchJob;
use DpdConnect\Shipping\Model\BatchJobFactory;
use DpdConnect\Shipping\Model\ResourceModel\Batch\CollectionFactory as BatchCollectionFactory;
use DpdConnect\Shipping\Model\ResourceModel\BatchJob\CollectionFactory as BatchJobCollectionFactory;
use Magento\Sales\Api\Data\OrderInterface;

class BatchManager
{
    const STATUS_SUCCESS    = 'success';
    const STATUS_PARTIAL    = 'partial';
    const STATUS_FAILED     = 'failed';
    const STATUS_QUEUED     = 'queued';

    /**
     * @var BatchFactory
     */
    private $batchFactory;
    /**
     * @var BatchJobFactory
     */
    private $batchJobFactory;
    /**
     * @var ShipmentLabelService
     */
    private $shipmentLabelService;
    /**
     * @var BatchJobCollectionFactory
     */
    private $batchJobCollectionFactory;
    /**
     * @var BatchCollectionFactory
     */
    private $batchCollectionFactory;

    /**
     * BatchManager constructor.
     * @param BatchFactory $batchFactory
     * @param BatchJobFactory $batchJobFactory
     * @param ShipmentLabelService $shipmentLabelService
     * @param BatchJobCollectionFactory $batchJobCollectionFactory
     * @param BatchCollectionFactory $batchCollectionFactory
     */
    public function __construct(
        BatchFactory $batchFactory,
        BatchJobFactory $batchJobFactory,
        ShipmentLabelService $shipmentLabelService,
        BatchJobCollectionFactory $batchJobCollectionFactory,
        BatchCollectionFactory $batchCollectionFactory
    ) {
        $this->batchFactory = $batchFactory;
        $this->batchJobFactory = $batchJobFactory;
        $this->shipmentLabelService = $shipmentLabelService;
        $this->batchJobCollectionFactory = $batchJobCollectionFactory;
        $this->batchCollectionFactory = $batchCollectionFactory;
    }

    public function createNewBatch()
    {
        $batch = $this->batchFactory->create();
        $batch->setStatus(self::STATUS_QUEUED);
        $batch->getResource()->save($batch);
        return $batch;
    }

    public function createNewJob($batch, $jobId)
    {
        $job = $this->batchJobFactory->create();
        $job->setBatchId($batch->getEntityId());
        $job->setJobId($jobId);
        $job->setStatus(self::STATUS_QUEUED);
        $job->getResource()->save($job);
        return $job;
    }

    /**
     * @param $jobId
     * @return BatchJob
     */
    public function getJobById($jobId)
    {
        $collection = $this->batchJobCollectionFactory->create();
        $collection->addFieldToFilter('job_id', $jobId);
        $batchJob = $collection->getFirstItem();
        if ($batchJob->getEntityId() === 0) {
            return null;
        }
        return $batchJob;
    }

    /**
     * @param $batchId
     * @return Batch
     */
    public function getBatchById($batchId)
    {
        return $this->batchFactory->create()->load($batchId);
    }

    /**
     * @param OrderInterface[] $orders
     * @param bool $includeReturn
     * @return mixed
     */
    public function createBatchByOrders(array $orders, $includeReturn = false)
    {
        $result = $this->shipmentLabelService->generateLabelAsync($orders, $includeReturn);

//        $batch = $this->batchFactory->create();
//        $batch->setStatus(self::STATUS_QUEUED);
//        $batch->getResource()->save($batch);
//
//        foreach($result as $job) {
//
//            $job = $this->batchJobFactory->create();
//            $job->setBatchId($batch->getEntityId());
//            $job->setOrderId($order->getEntityId());
//            $job->setOrderIncrementId($order->getIncrementId());
//            $job->setShipmentId(1);
//            $job->setStatus(self::STATUS_QUEUED);
//            $job->getResource()->save($job);
//
//        }
//
//
//        return true;
    }
}