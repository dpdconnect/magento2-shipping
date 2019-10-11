<?php

namespace DpdConnect\Shipping\Controller\Adminhtml\Label;

use DpdConnect\Shipping\Helper\Data;
use DpdConnect\Shipping\Model\ResourceModel\ShipmentLabel\CollectionFactory as ShipmentLabelCollectionFactory;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Shipping\Model\Shipping\LabelGenerator;
use Magento\Ui\Component\MassAction\Filter;

class MassDownload extends Action
{
    /**
     * @var ShipmentLabelCollectionFactory
     */
    private $shipmentLabelCollectionFactory;
    /**
     * @var Filter
     */
    private $filter;
    /**
     * @var FileFactory
     */
    private $fileFactory;
    /**
     * @var Data
     */
    private $dataHelper;

    /**
     * MassDownload constructor.
     * @param Context $context
     * @param Filter $filter
     * @param FileFactory $fileFactory
     * @param Data $dataHelper
     * @param ShipmentLabelCollectionFactory $shipmentLabelCollectionFactory
     */
    public function __construct(
        Context $context,
        Filter $filter,
        FileFactory $fileFactory,
        Data $dataHelper,
        ShipmentLabelCollectionFactory $shipmentLabelCollectionFactory
    ) {
        parent::__construct($context);
        $this->shipmentLabelCollectionFactory = $shipmentLabelCollectionFactory;
        $this->filter = $filter;
        $this->fileFactory = $fileFactory;
        $this->dataHelper = $dataHelper;
    }

    /**
     * Execute action based on request and return result
     *
     * Note: Request will be added as operation argument in future
     *
     * @return ResultInterface|ResponseInterface|void
     */
    public function execute()
    {
        $collection = $this->shipmentLabelCollectionFactory->create();
        $collection = $this->filter->getCollection($collection);

        $labelPDFs = [];

        foreach ($collection as $shipmentLabel) {

            $labelPDFs[] = $shipmentLabel->getLabel();
        }

        $resultPDF = $this->dataHelper->combinePDFFiles($labelPDFs);

        return $this->fileFactory->create(
            'DPD Shipping Labels.pdf',
            $resultPDF->render(),
            DirectoryList::VAR_DIR,
            'application/pdf'
        );
    }
}
