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
namespace DpdConnect\Shipping\Controller\Adminhtml\Shipping;

use DpdConnect\Shipping\Helper\Data;
use Exception;
use Magento\Backend\App\Action;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Filesystem\Io\File;
use Magento\Sales\Api\Data\ShipmentInterface;
use Magento\Backend\App\Action\Context;
use Magento\Ui\Component\MassAction\Filter;
use Magento\Sales\Model\ResourceModel\Order\Shipment\CollectionFactory as ShipmentCollectionFactory;
use DpdConnect\Shipping\Model\ResourceModel\ShipmentLabel\CollectionFactory as ShipmentLabelCollectionFactory;

class MassDownload extends Action
{
    /**
     * @var Filter
     */
    protected $filter;

    /**
     * @var object
     */
    protected $collectionFactory;

    /**
     * @var Data
     */
    protected $dataHelper;

    /**
     * @var FileFactory
     */
    protected $fileFactory;

    /**
     * @var ShipmentLabelCollectionFactory
     */
    protected $shipmentLabelCollectionFactory;

    /**
     * @var File
     */
    private $filesystem;

    /**
     * @param Context $context
     * @param Filter $filter
     * @param ShipmentCollectionFactory $collectionFactory
     * @param Data $dataHelper
     * @param FileFactory $fileFactory
     * @param ShipmentLabelCollectionFactory $shipmentLabelCollectionFactory
     * @param File $filesystem
     */
    public function __construct(
        Context $context,
        Filter $filter,
        ShipmentCollectionFactory $collectionFactory,
        Data $dataHelper,
        FileFactory $fileFactory,
        ShipmentLabelCollectionFactory $shipmentLabelCollectionFactory,
        File $filesystem
    ) {
        $this->filter = $filter;
        $this->dataHelper = $dataHelper;
        $this->fileFactory = $fileFactory;
        $this->collectionFactory = $collectionFactory;
        $this->shipmentLabelCollectionFactory = $shipmentLabelCollectionFactory;
        $this->filesystem = $filesystem;
        parent::__construct($context);
    }



    /**
     * @inheritDoc
     */
    public function execute()
    {
        try {
            $collection = $this->collectionFactory->create();
            $collection = $this->filter->getCollection($collection);

            $labelPDFs = [];
            /** @var ShipmentInterface[] $collection */
            foreach ($collection as $shipment) {
                $labelCollection = $this->shipmentLabelCollectionFactory->create();
                $labelCollection->addFilter('shipment_id', $shipment->getEntityId());
                $label = $labelCollection->getFirstItem();
                $labelCollection->resetData();
                if ($label) {
                    if ($label->getLabel() == '') {
                        $content = $this->filesystem->read($label->getLabelPath());
                    } else {
                        $content = $label->getLabel();
                    }

                    $labelPDFs[] = $content;
                }
            }

            $resultPDF = $this->dataHelper->combinePDFFiles($labelPDFs);

            return $this->fileFactory->create(
                'DPD-shippinglabels.pdf',
                $resultPDF,
                DirectoryList::VAR_DIR,
                'application/pdf'
            );
        } catch (Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            return $this->_redirect($this->_redirect->getRefererUrl());
        }
    }

    /**
     * @return Redirect
     */
    private function redirect()
    {
        $redirectPath = 'sales/order/index';

        $resultRedirect = $this->resultRedirectFactory->create();

        $resultRedirect->setPath($redirectPath);

        return $resultRedirect;
    }
}
