<?php

namespace DpdConnect\Shipping\Services;

use DpdConnect\Shipping\Helper\Constants;
use DpdConnect\Shipping\Helper\DpdSettings;
use Magento\Framework\DB\TransactionFactory;
use Magento\Sales\Model\Convert\Order as OrderConvert;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\Order\Shipment;
use Magento\Sales\Model\Order\Shipment\TrackFactory;
use Magento\Shipping\Model\ShipmentNotifier;
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
     * @var ShipmentNotifier
     */
    private $shipmentNotifier;

    /**
     * ShipmentManager constructor.
     * @param OrderConvert $orderConvert
     * @param TransactionFactory $transactionFactory
     * @param DpdSettings $dpdSettings
     * @param TrackFactory $trackFactory
     * @param ShipmentNotifier $shipmentNotifier
     */
    public function __construct(
        OrderConvert $orderConvert,
        TransactionFactory $transactionFactory,
        DpdSettings $dpdSettings,
        TrackFactory $trackFactory,
        ShipmentNotifier $shipmentNotifier
    ) {
        $this->orderConvert = $orderConvert;
        $this->transactionFactory = $transactionFactory;
        $this->dpdSettings = $dpdSettings;
        $this->trackFactory = $trackFactory;
        $this->shipmentNotifier = $shipmentNotifier;
    }

    /**
     * @param $order
     * @param $currentRow
     * @return Shipment|mixed
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    public function createShipment($order, $currentRow)
    {
        // If the order already has a shipment we return the first one
        // NOTE: This method is only called in mass actions for which we support 1 shipment per order
        if ($order->getShipmentsCollection()->count() > 0) {
            return $order->getShipmentsCollection()->getFirstItem();
        }

        $orderShipment = $this->orderConvert->toShipment($order);
        $orderShipment->setData(Constants::SHIPMENT_EXTRA_DATA, $currentRow);

        foreach ($order->getAllVisibleItems() as $orderItem) {
            $shippingType = $orderItem->getProduct()->getData('dpd_shipping_type');
            if (null === $shippingType) {
                $shippingType = 'default';
            }

            if ($currentRow && ($shippingType !== $currentRow['productType'])) {
                continue;
            }

            $qtyShipped = $orderItem->getQtyOrdered();

            // Create shipment item with qty
            $shipmentItem = $this->orderConvert->itemToShipmentItem($orderItem);
            $shipmentItem->setQty($qtyShipped);

            // Add shipment item to shipment
            $orderShipment->addItem($shipmentItem);
        }

        // Create the packages if necessary
        if (isset($currentRow['packageData']) && is_array($currentRow['packageData'])) {
            $orderShipment->setPackages($currentRow['packageData']);
        }

        // Add the shipment data if necessary
        $data = isset($currentRow['shipmentGeneralData']) ? $currentRow['shipmentGeneralData'] : [];
        if (isset($data['comment_text']) && !empty($data['comment_text'])) {
            $orderShipment->addComment(
                $data['comment_text'],
                isset($data['comment_customer_notify']),
                isset($data['is_visible_on_front'])
            );

            $orderShipment->setCustomerNote($data['comment_text']);
            $orderShipment->setCustomerNoteNotify(isset($data['comment_customer_notify']));
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

            // Shipment doesn't exist yet when creating a label with packages you define yourself
            // So we simply add the tracking data to the shipment which gets saved down the road
            if($shipment->getEntityId() === null) {
                $track = $this->trackFactory->create()
                    ->setNumber($parcelNumber)
                    ->setCarrierCode($carrierCode)
                    ->setTitle($carrierTitle)
                    ->setOrderId($shipment->getOrderId());
                $shipment->addTrack($track);
            } else {
                $track = $this->trackFactory->create();
                $track->setShipment($shipment);
                $track->setTitle($carrierTitle);
                $track->setNumber($parcelNumber);
                $track->setCarrierCode($carrierCode);
                $track->setOrderId($shipment->getOrderId());
                $track->getResource()->save($track);
            }
        }
        $sendConfirmEmail = $this->dpdSettings->isSetFlag(DpdSettings::ADVANCED_SEND_CONFIRM_EMAIL);
        if ($sendConfirmEmail) {
            $this->shipmentNotifier->notify($shipment);
        }
    }

    public function getCarrierCode(Order $order)
    {
        $result = explode('_', $order->getShippingMethod());
        return $result[0] ?? '';
    }
}
