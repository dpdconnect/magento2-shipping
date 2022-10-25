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
namespace DpdConnect\Shipping\Controller\Adminhtml\Order;

use DpdConnect\Shipping\Helper\Constants;
use DpdConnect\Shipping\Helper\Data;
use DpdConnect\Shipping\Helper\DpdSettings;
use DpdConnect\Shipping\Helper\Services\ShipmentLabelService;
use DpdConnect\Shipping\Model\ShipmentLabelFactory;
use DpdConnect\Shipping\Services\BatchManager;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Request\Http;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Magento\Ui\Component\MassAction\Filter;

class CreateShipment extends Action
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
     * @var ShipmentLabelFactory
     */
    private $shipmentLabelFactory;
    /**
     * @var DpdSettings
     */
    private $dpdSettings;
    /**
     * @var BatchManager
     */
    private $batchManager;

    /**
     * @var \Magento\Sales\Api\OrderRepositoryInterface
     */
    private $orderRepository;

    /**
     * @param Context                                     $context
     * @param Filter                                      $filter
     * @param OrderCollectionFactory                      $collectionFactory
     * @param Data                                        $dataHelper
     * @param ShipmentLabelFactory                        $shipmentLabelFactory
     * @param FileFactory                                 $fileFactory
     * @param DpdSettings                                 $dpdSettings
     * @param BatchManager                                $batchManager
     * @param ShipmentLabelService                        $shipmentLabelService
     * @param \Magento\Sales\Api\OrderRepositoryInterface $orderRepository
     */
    public function __construct(
        Context $context,
        Filter $filter,
        OrderCollectionFactory $collectionFactory,
        Data $dataHelper,
        ShipmentLabelFactory $shipmentLabelFactory,
        FileFactory $fileFactory,
        DpdSettings $dpdSettings,
        BatchManager $batchManager,
        ShipmentLabelService $shipmentLabelService,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository
    ) {
        $this->filter = $filter;
        $this->collectionFactory = $collectionFactory;
        $this->dataHelper = $dataHelper;
        $this->fileFactory = $fileFactory;
        $this->shipmentLabelFactory = $shipmentLabelFactory;
        parent::__construct($context);
        $this->dpdSettings = $dpdSettings;
        $this->batchManager = $batchManager;
        $this->orderRepository = $orderRepository;
    }

    public function execute()
    {
        try {
            $totalLabels = 0;
            $orders = [];
            foreach($this->getRequest()->getPostValue('order') as $orderId => $shippingProductData) {
                $rows = [];
                foreach($shippingProductData as $row) {
                    if ('' === $row['code']) {
                        continue;
                    }

                    $rows[] = $row;
                    $totalLabels++;
                }

                $order = $this->orderRepository->get($orderId);
                $order->setData(Constants::ORDER_EXTRA_SHIPPING_DATA, $rows);

                $orders[] = $order;
            }

            $isAsyncEnabled = $this->dpdSettings->isSetFlag(DpdSettings::API_ASYNC_ENABLED);
            $asyncThreshold = $this->dpdSettings->getValue(DpdSettings::API_ASYNC_THRESHOLD);

            // If the async requests is enabled and the selected order count is bigger than the threshold
            // we create a new batch and simply redirect to the sales order grid with a message
            if ($isAsyncEnabled && $totalLabels > $asyncThreshold) {
                $jobs = $this->dataHelper->generateShippingLabelAsync($orders);
                $this->messageManager->addSuccessMessage(sprintf(__('A batch with a total of %s orders is created.'), count($jobs)));
                return $this->_redirect($this->_redirect->getRefererUrl());
            }

            $labelPDFs = [];
            $generatedOrderLabel = [];
            foreach ($orders as $order) {
                try {
                    $label = $this->dataHelper->generateShippingLabel($order);
                    $labelPDFs = array_merge($labelPDFs, $label);
                    $generatedOrderLabel[] = $order->getIncrementId();
                } catch (\Exception $e) {
                    $this->messageManager->addErrorMessage(
                        sprintf(__('DPD - Label for order %s could not be created: %s'), $order->getIncrementId(), $e->getMessage())
                    );
                    continue;
                }
            }

            if (count($labelPDFs) == 0) {
                $this->messageManager->addErrorMessage(
                    __('DPD - There are no shipping labels generated.')
                );

                return $this->_redirect($this->_redirect->getRefererUrl());
            }


            $this->messageManager->addSuccessMessage(
                sprintf(__('DPD - Labels for the following orders have been created: %s'), implode(',', $generatedOrderLabel))
            );

            $resultPDF = $this->dataHelper->combinePDFFiles($labelPDFs);

            return $this->fileFactory->create(
                'DPD-shippinglabels.pdf',
                $resultPDF,
                DirectoryList::VAR_DIR,
                'application/pdf'
            );
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage('DPD Error (!): ' . $e->getMessage());
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
