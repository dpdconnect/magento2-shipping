<?php

namespace DpdConnect\Shipping\Services;

use DpdConnect\Shipping\Helper\DpdSettings;
use Magento\Framework\DB\TransactionFactory;
use Magento\Sales\Model\Convert\Order as OrderConvert;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Shipment;
use Magento\Sales\Model\Order\Shipment\TrackFactory;
use Magento\Store\Model\ScopeInterface;

class ShipmentManager
{
    /**
     * @var OrderConvert
     */
    private $orderConvert;
    /**
     * @var TransactionFactory
     */
    private $transactionFactory;
    /**
     * @var DpdSettings
     */
    private $dpdSettings;
    /**
     * @var TrackFactory
     */
    private $trackFactory;

    /**
     * ShipmentManager constructor.
     * @param OrderConvert $orderConvert
     * @param TransactionFactory $transactionFactory
     * @param DpdSettings $dpdSettings
     * @param TrackFactory $trackFactory
     */
    public function __construct(
        OrderConvert $orderConvert,
        TransactionFactory $transactionFactory,
        DpdSettings $dpdSettings,
        TrackFactory $trackFactory
    ) {
        $this->orderConvert = $orderConvert;
        $this->transactionFactory = $transactionFactory;
        $this->dpdSettings = $dpdSettings;
        $this->trackFactory = $trackFactory;
    }

    /**
     * Ships an entire order at once, used in mass actions
     * @param Order $order
     * @return Shipment
     */
    public function createShipment($order)
    {
        // If the order already has a shipment we return the first one
        // NOTE: This method is only called in mass actions for which we support 1 shipment per order
        if ($order->getShipmentsCollection()->count() > 0) {
            return $order->getShipmentsCollection()->getFirstItem();
        }

        $orderShipment = $this->orderConvert->toShipment($order);

        foreach ($order->getAllItems() as $orderItem) {
            $qtyShipped = $orderItem->getQtyOrdered();

            // Create shipment item with qty
            $shipmentItem = $this->orderConvert->itemToShipmentItem($orderItem);
            $shipmentItem->setQty($qtyShipped);

            // Add shipment item to shipment
            $orderShipment->addItem($shipmentItem);
        }

        $orderShipment->register();

        $shipmentTransaction = $this->transactionFactory->create()
            ->addObject($orderShipment)
            ->addObject($orderShipment->getOrder());
        $shipmentTransaction->save();

        return $orderShipment;
    }

    public function addTrackingNumbersToShipment(Shipment $shipment, array $parcelNumbers)
    {
        $carrierCode = $this->getCarrierCode($shipment->getOrder());

        $carrierTitle = $this->dpdSettings->getCarrierTitle($carrierCode, ScopeInterface::SCOPE_STORE, $shipment->getStoreId());

        foreach ($parcelNumbers as $parcelNumber) {
            $track = $this->trackFactory->create();
            $track->setShipment($shipment);
            $track->setTitle($carrierTitle);
            $track->setNumber($parcelNumber);
            $track->setCarrierCode($carrierCode);
            $track->setOrderId($shipment->getOrderId());
            $track->getResource()->save($track);
        }
    }

    public function getCarrierCode(Order $order)
    {
        $result = explode('_', $order->getShippingMethod());
        return $result[0] ?? '';
    }
}
