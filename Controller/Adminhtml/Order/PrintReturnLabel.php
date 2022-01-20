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
use DpdConnect\Shipping\Model\Mail\Template\TransportBuilder;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\Response\Http\FileFactory;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\ResultInterface;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Magento\Backend\App\Action\Context;
use Magento\Ui\Component\MassAction\Filter;
use Magento\Sales\Model\Order;
use DpdConnect\Shipping\Model\ShipmentLabelFactory;

class PrintReturnLabel extends \Magento\Backend\App\Action
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
     * @var FileFactory
     */
    protected $fileFactory;

    /**
     * @var \DpdConnect\Shipping\Model\ShipmentLabelFactory
     */
    private $shipmentLabelFactory;
    /**
     * @var Context
     */
    private $context;
    /**
     * @var \Magento\Framework\Translate\Inline\StateInterface
     */
    private $inlineTranslation;
    /**
     * @var \DpdConnect\Shipping\Model\Mail\Template\TransportBuilder
     */
    private $transportBuilder;
    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    private $scopeConfig;
    /**
     * @var \Psr\Log\LoggerInterface
     */
    private $loggerInterface;
    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var \Magento\Framework\Message\ManagerInterface
     */
    protected $messageManager;

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
     * @param FileFactory $fileFactory
     */
    public function __construct(
        Context $context,
        Filter $filter,
        OrderCollectionFactory $collectionFactory,
        \DpdConnect\Shipping\Helper\Data $dataHelper,
        ShipmentLabelFactory $shipmentLabelFactory,
        FileFactory $fileFactory,
        \Magento\Framework\Translate\Inline\StateInterface $inlineTranslation,
        TransportBuilder $transportBuilder,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Psr\Log\LoggerInterface $loggerInterface,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        DpdSettings $dpdSettings
    ) {
        parent::__construct($context);
        $this->filter = $filter;
        $this->collectionFactory = $collectionFactory;
        $this->dataHelper = $dataHelper;
        $this->fileFactory = $fileFactory;
        $this->shipmentLabelFactory = $shipmentLabelFactory;
        $this->inlineTranslation = $inlineTranslation;
        $this->transportBuilder = $transportBuilder;
        $this->scopeConfig = $scopeConfig;
        $this->loggerInterface = $loggerInterface;
        $this->storeManager = $storeManager;
        $this->messageManager = $context->getMessageManager();
        $this->dpdSettings = $dpdSettings;
    }

    /**
     * Execute action
     *
     * @return \Magento\Framework\Controller\Result\|\Magento\Framework\App\ResponseInterface
     * @throws \Magento\Framework\Exception\LocalizedException|\Exception
     */
    public function execute()
    {
        try {
            $collection = $this->collectionFactory->create();
            $collection = $this->filter->getCollection($collection);
            $emailReturnLabel = $this->dpdSettings->isSetFlag(DpdSettings::ADVANCED_EMAIL_RETURN_LABEL);

            $labelPDFs = array();

            foreach ($collection as $order) {
                if ($this->dataHelper->isDPDOrder($order)) {
                    $labels = $this->dataHelper->generateShippingLabel($order, null, 1, true);
                    if ($emailReturnLabel) {
                        $this->sendEmail($order, $this->dataHelper->combinePDFFiles($labels));
                    }

                    $labelPDFs = array_merge($labelPDFs, $labels);
                }
            }

            if (count($labelPDFs) == 0) {
                $this->messageManager->addErrorMessage(
                    __('DPD - There are no return labels generated.')
                );

                return $this->_redirect($this->_redirect->getRefererUrl());
            }

            $resultPDF = $this->dataHelper->combinePDFFiles($labelPDFs);

            return $this->fileFactory->create(
                'DPD-returnlabels.pdf',
                $resultPDF,
                DirectoryList::VAR_DIR,
                'application/pdf'
            );
        } catch (\Exception $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
            return $this->_redirect($this->_redirect->getRefererUrl());
        }
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

    private function sendEmail(Order $order, $pdfData)
    {
        try
        {
            // Send Mail
            $this->inlineTranslation->suspend();

            // Reset the transport builder to make sure no previous email data is used
            $this->transportBuilder->reset();
            $transport = $this->transportBuilder
                ->setTemplateIdentifier($this->dpdSettings->getValue(DpdSettings::ADVANCED_EMAIL_RETURN_LABEL_TEMPLATE))
                ->setTemplateOptions([
                    'area' => 'adminhtml',
                    'store' => $this->storeManager->getStore()->getId()
                ])
                ->setTemplateVars([
                    'order' => $order,
                ])
                ->setFromByScope('general', $order->getStoreId())
                ->addTo($order->getCustomerEmail(), $order->getCustomerName())
                ->addAttachment(
                    $pdfData,
                    'application/pdf',
                    TransportBuilder::DISPOSITION_ATTACHMENT,
                    TransportBuilder::ENCODING_BASE64,
                    'labels.pdf'
                )
                ->getTransport();

            $transport->sendMessage();

            $this->inlineTranslation->resume();

        } catch(\Exception $e){
            $this->loggerInterface->debug($e->getMessage());
        }
    }
}
