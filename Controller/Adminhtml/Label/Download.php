<?php

namespace DpdConnect\Shipping\Controller\Adminhtml\Label;

use DpdConnect\Shipping\Model\ShipmentLabelFactory;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;

class Download extends Action
{
    /**
     * @var ShipmentLabelFactory
     */
    private $shipmentLabelFactory;
    /**
     * @var FileFactory
     */
    private $fileFactory;

    /**
     * Download constructor.
     * @param Context $context
     * @param ShipmentLabelFactory $shipmentLabelFactory
     * @param FileFactory $fileFactory
     */
    public function __construct(
        Context $context,
        ShipmentLabelFactory $shipmentLabelFactory,
        FileFactory $fileFactory
    ) {
        parent::__construct($context);
        $this->shipmentLabelFactory = $shipmentLabelFactory;
        $this->fileFactory = $fileFactory;
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
        $entityId = $this->getRequest()->getParam('entity_id');
        $shipmentLabel = $this->shipmentLabelFactory->create();
        $shipmentLabel->load($entityId);

        $labelName = sprintf('DPD %s-%s.pdf', $shipmentLabel->getOrderIncrementId(), $shipmentLabel->getOrderId());

        return $this->fileFactory->create(
            $labelName,
            $shipmentLabel->getLabel(),
            DirectoryList::VAR_DIR,
            'application/pdf'
        );
    }
}
