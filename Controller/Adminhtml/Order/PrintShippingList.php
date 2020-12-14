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

use DpdConnect\Shipping\Helper\DpdSettings;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Magento\Backend\App\Action\Context;
use Magento\Ui\Component\MassAction\Filter;
use Magento\Sales\Model\Order;
use Magento\Framework\View\Result\PageFactory;
use \DpdConnect\Shipping\Model\ShipmentLabelFactory;
use DpdConnect\Shipping\Helper\Services\ShipmentLabelService;

class PrintShippingList extends \Magento\Backend\App\Action
{
    /**
     * @var \Magento\Ui\Component\MassAction\Filter
     */
    protected $filter;

    /**
     * @var object
     */
    protected $collectionFactory;

    /**
     * @var \DpdConnect\Shipping\Helper\Data
     */
    protected $dataHelper;

    /**
     * @var PageFactory
     */
    protected $resultPageFactory;

    /**
     * @var ShipmentLabelService
     */
    protected $predictService;

    /**
     * @var \DpdConnect\Shipping\Model\ShipmentLabelFactory
     */
    private $shipmentLabelFactory;
    /**
     * @var DpdSettings
     */
    private $dpdSettings;

    /**
     * @param Context $context
     * @param Filter $filter
     * @param OrderCollectionFactory $collectionFactory
     * @param \DpdConnect\Shipping\Helper\Data $dataHelper
     * @param ShipmentLabelFactory $shipmentLabelFactory
     * @param PageFactory $pageFactory
     * @param ShipmentLabelService $predictService
     * @param DpdSettings $dpdSettings
     */
    public function __construct(
        Context $context,
        Filter $filter,
        OrderCollectionFactory $collectionFactory,
        \DpdConnect\Shipping\Helper\Data $dataHelper,
        ShipmentLabelFactory $shipmentLabelFactory,
        PageFactory $pageFactory,
        ShipmentLabelService $predictService,
        DpdSettings $dpdSettings
    ) {
        $this->filter = $filter;
        $this->collectionFactory = $collectionFactory;
        $this->dataHelper = $dataHelper;
        $this->resultPageFactory = $pageFactory;
        $this->shipmentLabelFactory = $shipmentLabelFactory;
        $this->predictService = $predictService;

        parent::__construct($context);
        $this->dpdSettings = $dpdSettings;
    }

    public function execute()
    {
        try {
            $collection = $this->collectionFactory->create();
            $collection = $this->filter->getCollection($collection);

            $orders = [];
            $orders["list"] = [];

            $count = 0;
            foreach ($collection as $order) {
                if (!$this->dataHelper->isDPDOrder($order)) {
                    continue;
                }

                $shipmentLabels = $this->shipmentLabelFactory->create()
                    ->getCollection()
                    ->addFieldToFilter('order_id', ["eq", $order->getId()])
                    ->addFieldToFilter('is_return', ["eq", '0']);

                foreach ($shipmentLabels as $shipmentLabel) {
                    $parcelNumbers = [];
                    if($this->isSerialized($shipmentLabel->getLabelNumbers())) {
                        $parcelNumbers = unserialize($shipmentLabel->getLabelNumbers());
                    }
                    foreach ($parcelNumbers as $parcelData) {

                        $count++;
                        $orders["list"][] = [
                            "count" => $count,
                            "parcelLabelNumber" => $parcelData['parcel_number'],
                            // Weight is stored in decagram
                            "weight" => round($parcelData['weight'] * 10, 2)."g",
                            "carrierName" => $order->getShippingDescription(),
                            "customerName" => $order->getShippingAddress()->getName(),
                            "address" => implode(' ', $order->getShippingAddress()->getStreet()),
                            "zipCode" => $order->getShippingAddress()->getPostcode(),
                            "city" => $order->getShippingAddress()->getCity(),
                            "referenceNumber" => $order->getIncrementId(),
                            "referenceNumber2" => $shipmentLabel->getShipmentIncrementId()
                        ];
                    }
                }
            }

            $resultPage = $this->resultPageFactory->create();
            $blockInstance = $resultPage->getLayout()->createBlock(\DpdConnect\Shipping\Block\Adminhtml\Order\PrintShippingList::class);
            $blockInstance->setTemplate("DpdConnect_Shipping::printshippinglist.phtml");

            $blockInstance->assign([
                'sender' => [
                    'name1' => $this->dpdSettings->getValue(DpdSettings::SHIPPING_ORIGIN_NAME1),
                    'street' => $this->dpdSettings->getValue(DpdSettings::SHIPPING_ORIGIN_STREET),
                    'housenumber' => $this->dpdSettings->getValue(DpdSettings::SHIPPING_ORIGIN_HOUSE_NUMBER),
                    'country' => $this->dpdSettings->getValue(DpdSettings::SHIPPING_ORIGIN_COUNTRY),
                    'zipCode' => $this->dpdSettings->getValue(DpdSettings::SHIPPING_ORIGIN_ZIP_CODE),
                    'city' => $this->dpdSettings->getValue(DpdSettings::SHIPPING_ORIGIN_CITY),
                    'phone' => $this->dpdSettings->getValue(DpdSettings::STORE_INFORMATION_PHONE),
                    'email' => $this->dpdSettings->getValue(DpdSettings::STORE_INFORMATION_EMAIL),
                    'commercialAddress' => true,
                    'vatnumber' => $this->dpdSettings->getValue(DpdSettings::STORE_INFORMATION_VAT_NUMBER),
                    'eorinumber' => $this->dpdSettings->getValue(DpdSettings::STORE_INFORMATION_EORI),
                    'sprn' => $this->dpdSettings->getValue(DpdSettings::STORE_INFORMATION_SPRN),
                ],
                'orders' => $orders
            ]);

            echo $blockInstance->toHtml();
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            return $this->_redirect($this->_redirect->getRefererUrl());
        }
    }

    /**
     * Check if a string is serialized
     * @param string $string
     */
    private function isSerialized($string) {
        return (@unserialize($string) !== false);
    }

    /**
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    private function redirect()
    {
        $redirectPath = 'sales/order/index';

        $resultRedirect = $this->resultRedirectFactory->create();

        $resultRedirect->setPath($redirectPath);

        return $resultRedirect;
    }
}
