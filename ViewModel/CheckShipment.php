<?php

namespace DpdConnect\Shipping\ViewModel;

use DpdConnect\Sdk\Exceptions\DpdException;
use DpdConnect\Shipping\Helper\Data;
use DpdConnect\Shipping\Helper\DPDClient;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory as OrderCollectionFactory;
use Magento\Ui\Component\MassAction\Filter;

/**
 * Class CheckShipment
 *
 * @package DpdConnect\Shipping\ViewModel
 */
class CheckShipment implements ArgumentInterface
{
    /**
     * @var Filter
     */
    private $filter;

    /**
     * @var OrderCollectionFactory
     */
    private $orderCollectionFactory;

    /**
     * @var DPDClient
     */
    private $client;

    /**
     * @var Data
     */
    private $dataHelper;

    /**
     * @var \Magento\Backend\Model\UrlInterface
     */
    private $urlBuilder;

    /**
     * CheckShipment constructor.
     *
     * @param Data                                 $dataHelper
     * @param DPDClient                            $client
     * @param Filter                               $filter
     * @param OrderCollectionFactory               $orderCollectionFactory
     * @param \Magento\Backend\Model\UrlInterface  $urlBuilder
     */
    public function __construct(
        Data $dataHelper,
        DpdClient $client,
        Filter $filter,
        OrderCollectionFactory $orderCollectionFactory,
        \Magento\Backend\Model\UrlInterface $urlBuilder
    ) {
        $this->filter = $filter;
        $this->orderCollectionFactory = $orderCollectionFactory;
        $this->client = $client;
        $this->dataHelper = $dataHelper;
        $this->urlBuilder = $urlBuilder;
    }

    /**
     * @return OrderInterface[]
     *
     * @throws LocalizedException
     */
    public function getOrders()
    {
        $collection = $this->orderCollectionFactory->create();
        $collection = $this->filter->getCollection($collection);

        $orders = [];
        /** @var OrderInterface[] $order */
        foreach ($collection as $order) {
            if (!$this->dataHelper->isDPDOrder($order)) {
                continue;
            }

            if ($order->getShipmentsCollection()->count() > 1) {
                continue;
            }

            $orders[] = $order;
        }

        return $orders;
    }

    /**
     * @retrun string
     */
    public function getPostUrl()
    {
        return $this->urlBuilder->getRouteUrl('dpd_shipping/order/createShipment', ['key' => $this->urlBuilder->getSecretKey('dpd_shipping','order','createShipment')]);
    }

    /**
     * @return string
     */
    public function getOrderOverviewUrl()
    {
        return $this->urlBuilder->getRouteUrl('sales/order/index', ['key' => $this->urlBuilder->getSecretKey('sales','order','index')]);
    }

    /**
     * @param Order $order
     *
     * @return bool
     */
    public function isParcelshopOrder(Order $order)
    {
        return 'dpdpickup_dpdpickup' === $order->getShippingMethod();
    }

    /**
     * @return string
     * @throws DpdException
     */
    public function getParcelshopCode()
    {
        $availableProducts = $this->client->authenticate()->getProduct()->getList();
        foreach($availableProducts as $product) {
            if ('parcelshop' === $product['type']) {
                return $product['code'];
            }
        }

        throw new \Exception('ParcelShop is not enabled');
    }

    /**
     * @param Order $order
     *
     * @return array
     * @throws DpdException
     */
    public function getLabelTypeOptions(Order $order)
    {
        $availableProducts = [];
        $shippingProducts = $this->client->authenticate()->getProduct()->getList();
        foreach($shippingProducts as $shippingProduct) {
            if ('fresh' === $shippingProduct['type']
                || ('parcelshop' === $shippingProduct['type'] && !$this->isParcelshopOrder($order))
            ) {
                continue;
            }

            $availableProducts[] = $shippingProduct;
        }

        return $availableProducts;
    }

    /**
     * @param Order $order
     * @param array $shippingProduct
     * @param array $row
     *
     * @return bool
     */
    public function isSelected(Order $order, array $shippingProduct, array $row): bool
    {
        if ($this->isParcelshopOrder($order) && 'parcelshop' === $shippingProduct['type']) {
            return true;
        }

        if ($this->hasFreshFreezeProducts($order) && strtolower($shippingProduct['code']) === $row['productType']) {
            return true;
        }

        if ($order->getDpdShippingProduct() === $shippingProduct['code']) {
            return true;
        }

        return false;
    }

    /**
     * @param Order $order
     * @return bool
     */
    public function hasFreshFreezeProducts(Order $order): bool
    {
        return $this->dataHelper->hasDpdFreshProducts($order);
    }

    /**
     * @param Order $order
     *
     * @return array
     */
    public function getRows(Order $order): array
    {
        $rows = [];
        $defaultProducts = [];

        $orderItems = $order->getAllVisibleItems();
        foreach($orderItems as $orderItem) {
            $productType = $orderItem->getProduct()->getData('dpd_shipping_type') ?: 'default';

            switch($productType) {
                case 'fresh':
                    $rows[] = [
                        'productType' => 'fresh',
                        'code' => 'FRESH',
                        'expirationDate' => date('Y-m-d', strtotime('+5 weekdays')),
                        'description' => $orderItem->getProduct()->getData('dpd_fresh_description'),
                        'products' => [$orderItem->getProduct()],
                    ];
                    break;

                case 'freeze':
                    $rows[] = [
                        'productType' => 'freeze',
                        'code' => 'FREEZE',
                        'expirationDate' => date('Y-m-d', strtotime('+5 weekdays')),
                        'description' => $orderItem->getProduct()->getData('dpd_fresh_description'),
                        'products' => [$orderItem->getProduct()],
                    ];
                    break;

                case 'default':
                default:
                    $defaultProducts[] = $orderItem->getProduct();
                    break;
            }
        }

        if (0 < count($defaultProducts)) {
            $rows[] = [
                'productType' => 'default',
                'code' => $order->getDpdShippingProduct(),
                'products' => $defaultProducts,
            ];
        }

        return $rows;
    }
}
