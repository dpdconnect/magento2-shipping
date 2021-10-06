<?php

namespace DpdConnect\Shipping\Block\Adminhtml\Order;

use DpdConnect\Sdk\Exceptions\DpdException;
use DpdConnect\Shipping\Helper\Data;
use DpdConnect\Shipping\Helper\DPDClient;
use Magento\Sales\Model\Order;
use Magento\Shipping\Helper\Carrier;

/**
 * Override the default Magento 2 package management screen
 */
class Packaging extends \Magento\Shipping\Block\Adminhtml\Order\Packaging
{
    /**
     * @var DPDClient
     */
    private $client;

    /**
     * @var Data
     */
    private $dataHelper;

    /**
     * @param DPDClient $client
     * @param \Magento\Backend\Block\Template\Context $context
     * @param \Magento\Framework\Json\EncoderInterface $jsonEncoder
     * @param \Magento\Shipping\Model\Carrier\Source\GenericInterface $sourceSizeModel
     * @param \Magento\Framework\Registry $coreRegistry
     * @param \Magento\Shipping\Model\CarrierFactory $carrierFactory
     * @param array $data
     * @param Carrier|null $carrierHelper
     */
    public function __construct(Data $dataHelper, DpdClient $client, \Magento\Backend\Block\Template\Context $context, \Magento\Framework\Json\EncoderInterface $jsonEncoder, \Magento\Shipping\Model\Carrier\Source\GenericInterface $sourceSizeModel, \Magento\Framework\Registry $coreRegistry, \Magento\Shipping\Model\CarrierFactory $carrierFactory, array $data = [], ?Carrier $carrierHelper = null)
    {
        parent::__construct($context, $jsonEncoder, $sourceSizeModel, $coreRegistry, $carrierFactory, $data);
        $this->client = $client;
        $this->dataHelper = $dataHelper;
    }

    /**
     * @override
     *
     * @return string|void
     */
    public function getConfigDataJson()
    {
        $jsonString = parent::getConfigDataJson();
        $config = json_decode($jsonString, true);

        $urlParams['order_id'] = $this->getOrder()->getId();
        $config['createLabelUrl'] = $this->getUrl('dpd_shipping/order_shipment/save', $urlParams);
        $config['itemsGridUrl'] = $this->getUrl('dpd_shipping/order_shipment/getShippingItemsGrid', $urlParams);

        return json_encode($config);
    }

    /**
     * @return Order
     */
    public function getOrder()
    {
        return $this->getShipment()->getOrder();
    }

    /**
     * @param array $shippingProduct
     *
     * @return bool
     */
    public function isSelected(array $shippingProduct): bool
    {
        $order = $this->getOrder();
        if ($this->isParcelshopOrder($order) && 'parcelshop' === $shippingProduct['type']) {
            return true;
        }

        if ($order->getDpdShippingProduct() === $shippingProduct['code']) {
            return true;
        }

        return false;
    }

    /**
     * @return bool
     */
    public function isParcelshopOrder()
    {
        $order = $this->getOrder();

        return 'dpdpickup_dpdpickup' === $order->getShippingMethod();
    }

    /**
     * @return array
     * @throws DpdException
     */
    public function getLabelTypeOptions()
    {
        $order = $this->getOrder();

        $availableProducts = [];
        $shippingProducts = $this->client->authenticate()->getProduct()->getList();
        foreach ($shippingProducts as $shippingProduct) {
            if ('parcelshop' === $shippingProduct['type'] && !$this->isParcelshopOrder($order)) {
                continue;
            }

            $availableProducts[] = $shippingProduct;
        }

        return $availableProducts;
    }

    /**
     * @return bool
     */
    public function hasFreshFreezeProducts(): bool
    {
        $order = $this->getOrder();

        return $this->dataHelper->hasDpdFreshProducts($order);
    }
}
