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

use DpdConnect\Shipping\Helper\Data;
use DpdConnect\Shipping\Helper\DpdSettings;
use DpdConnect\Shipping\Helper\Services\ShipmentLabelService;
use DpdConnect\Shipping\Model\ShipmentLabelFactory;
use DpdConnect\Shipping\Services\BatchManager;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\Controller\Result\Redirect;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Registry;
use Magento\Framework\View\Result\PageFactory;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Magento\Ui\Component\MassAction\Filter;

class CheckShipment extends Action
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
     * @param Context $context
     * @param Filter $filter
     * @param OrderCollectionFactory $collectionFactory
     * @param Data $dataHelper
     * @param FileFactory $fileFactory
     * @param DpdSettings $dpdSettings
     * @param BatchManager $batchManager
     * @param ShipmentLabelService $shipmentLabelService
     */
    public function __construct(
        Context $context,
        Filter $filter,
        OrderCollectionFactory $collectionFactory,
        Data $dataHelper,
        FileFactory $fileFactory
    ) {
        $this->filter = $filter;
        $this->collectionFactory = $collectionFactory;
        $this->dataHelper = $dataHelper;
        $this->fileFactory = $fileFactory;
        parent::__construct($context);
    }

    public function execute()
    {
        try {
            $collection = $this->collectionFactory->create();
            $collection = $this->filter->getCollection($collection);

            $orders = [];
            /** @var OrderInterface[] $order */
            foreach ($collection as $order) {
                if (!$this->dataHelper->isDPDOrder($order)) {
                    $this->messageManager->addErrorMessage(
                        sprintf(__('Order %s is not a DPD order.'), $order->getIncrementId())
                    );

                    continue;
                }

                if ($order->getShipmentsCollection()->count() > 1) {
                    $this->messageManager->addErrorMessage(
                        sprintf(__('Order %s has more than 1 shipment, go the the shipment overview to generate a label.'), $order->getIncrementId())
                    );
                    continue;
                }

                $orders[] = $order;
            }

            if (0 === count($orders)) {
                $this->messageManager->addErrorMessage(
                    __('DPD - None of the selected orders are eligible for generating DPD shipment labels this way.')
                );

                return $this->_redirect('sales/order/index');
            }

            // Show template with orders overview and label type select boxes for customer
            return $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage('DPD Shipment error: ' . $e->getMessage());
            return $this->_redirect('sales/order/index');
        }
    }
}
