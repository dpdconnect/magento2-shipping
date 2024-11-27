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
namespace DpdConnect\Shipping\Helper\Services;

use DpdConnect\Shipping\Helper\Constants;
use DpdConnect\Shipping\Helper\DpdSettings;
use Magento\Catalog\Model\ProductFactory;
use Magento\Framework\App\Helper\AbstractHelper;
use DpdConnect\Shipping\Helper\DPDClient;
use Magento\Framework\App\Helper\Context;
use Magento\Sales\Api\Data\OrderAddressInterface;
use Magento\Sales\Model\Order;
use Magento\Store\Model\ScopeInterface;

class OrderConvertService extends AbstractHelper
{
    /**
     * @var DpdSettings
     */
    private $dpdSettings;
    /**
     * @var OrderService
     */
    private $orderService;

    /**
     * OrderConvertService constructor.
     * @param Context $context
     * @param DPDClient $DPDClient
     * @param OrderService $orderService
     * @param DpdSettings $dpdSettings
     * @param ProductFactory $productFactory
     */
    public function __construct(
        Context $context,
        DPDClient $DPDClient,
        OrderService $orderService,
        DpdSettings $dpdSettings,
        ProductFactory $productFactory
    ) {
        parent::__construct($context);
        $this->dpdSettings = $dpdSettings;
        $this->orderService = $orderService;
    }

    /**
     * @param Order $order
     * @param bool  $return
     *
     * @return string
     */
    private function getProductCode(Order $order, ?Order\Shipment $shipment = null, bool $return = false)
    {
        if ($return === true) {
            return 'RETURN';
        }

        // Backwards compatibility checks
        if (Constants::CARRIER_DPD !== $order->getShippingMethod() && false === $this->orderService->isDPDPickupOrder()) {
            switch($order->getShippingMethod()) {
                case 'dpdexpress10_dpdexpress10':
                    return 'E10';

                case 'dpdexpress12_dpdexpress12':
                    return 'E12';

                case 'dpdguarantee18_dpdguarantee18':
                    return 'E18';

                default:
                    return 'CL';
            }
        }

        // Fetch the code from the shipment, if any, else default to the order code
        if ($shipment && !empty($shipment->hasData(Constants::SHIPMENT_EXTRA_DATA)['code'])) {
            return $shipment->getData(Constants::SHIPMENT_EXTRA_DATA)['code'];
        }

        return $order->getDpdShippingProduct();
    }

    /**
     * @param Order $order
     *
     * @return array
     */
    public function getReceiverData(Order $order)
    {
        if ($this->orderService->isDPDPickupOrder()) {
            $billingAddress = $order->getBillingAddress();
            $recipient = $this->processAddress($billingAddress);
        } else {
            // Check if the order was changed from Parcelshop to a different method
            if (Constants::CARRIER_PARCELSHOP === $order->getShippingMethod()) {
                $billingAddress = $order->getBillingAddress();
                $recipient = $this->processAddress($billingAddress);
            } else {
                $shippingAddress = $order->getShippingAddress();
                $recipient = $this->processAddress($shippingAddress);
            }
        }
        $recipient['email'] = $order->getCustomerEmail();

        return $recipient;
    }

    /**
     * @param Order $order
     * @return float
     */
    public function getOrderWeight(Order $order)
    {
        $orderWeight = $order->getWeight();

        $weightUnit = $this->scopeConfig->getValue('general/locale/weight_unit');
        if ($weightUnit == '') {
            $weightUnit = 'lbs';
        }

        if ($weightUnit == 'lbs') {
            $orderWeight *=  0.45359237;
        }

        // Weight is in KG so multiply with 100
        $orderWeight *= 100;

        if ($orderWeight == 0) {
            $orderWeight = 600;
        }

        return round($orderWeight, 0);
    }

    /**
     * @param Order $order
     * @param Order\Shipment|null $shipment
     * @param bool $isReturn
     * @param int $parcelAmount
     * @return array
     */
    public function addParcels(Order $order, ?Order\Shipment $shipment = null, bool $isReturn = false, int $parcelAmount = 1)
    {
        $parcels = [];

        for ($x = 1; $x <= $parcelAmount; $x++) {
            $orderWeight = $this->getOrderWeight($order) / $parcelAmount;
            $parcel = [
                'customerReferences' => [
                    $order->getIncrementId() ?? '',
                    ($this->dpdSettings->isSetFlag(DpdSettings::ADVANCED_PRINT_ORDER_ID, ScopeInterface::SCOPE_STORE, $order->getStoreId()) ? $order->getEntityId() : ''),
                    $order->getDpdParcelshopId() ?? '',
                    $shipment->getEntityId() ?? '',
                ],
                'weight' => (int) $orderWeight,
                'returns' => $isReturn,
            ];

            if (null !== $shipment && $shipment->hasData(Constants::SHIPMENT_EXTRA_DATA)) {
                $extradata = $shipment->getData(Constants::SHIPMENT_EXTRA_DATA);
                if (isset($extradata['expirationDate']) && isset($extradata['description'])) {
                    $parcel['goodsExpirationDate'] = intval(str_replace('-', '', $extradata['expirationDate']));
                    $parcel['goodsDescription'] = $extradata['description'];
                }
            }

            $parcels[] = $parcel;
        }

        return $parcels;
    }

    /**
     * @param Order $order
     * @param Order\Shipment|null $shipment
     * @param $packages
     * @return array
     * @throws \Zend_Measure_Exception
     */
    public function addParcelsFromPackages(Order $order, ?Order\Shipment $shipment = null, $packages = [])
    {
        $parcels = [];

        foreach ($packages as $package) {
            $weight = floatval($package['weight'] ?? $package['params']['weight'] ?? 0);
            $unit = $package['weight_units'] ?? $package['params']['weight_units'] ?? 'KILOGRAM';

            if ($unit === 'POUND') {
                $weight = $weight * 0.45359237;
            }

            $parcel = [
                'customerReferences' => [
                    $order->getIncrementId() ?? '',
                    ($this->dpdSettings->isSetFlag(DpdSettings::ADVANCED_PRINT_ORDER_ID, ScopeInterface::SCOPE_STORE, $order->getStoreId()) ? $order->getIncrementId() : ''),
                    $order->getDpdParcelshopId() ?? '',
                    $shipment->getEntityId(),
                ],
                'weight' => (int)$weight,
            ];
            if (null !== $shipment && $shipment->hasData(Constants::SHIPMENT_EXTRA_DATA)) {
                $extradata = $shipment->getData(Constants::SHIPMENT_EXTRA_DATA);
                if (isset($extradata['expirationDate']) && isset($extradata['description'])) {
                    $parcel['goodsExpirationDate'] = intval(str_replace('-', '', $extradata['expirationDate']));
                    $parcel['goodsDescription'] = $extradata['description'];
                }
            }

            $parcels[] = $parcel;
        }
        return $parcels;
    }

    /**
     * @param Order $order
     * @param Order\Shipment|null $orderShipment
     * @param bool $isReturn
     * @param array $packages
     * @param bool $useCustoms
     * @return array
     */
    public function convert(Order $order, ?Order\Shipment $orderShipment = null, bool $isReturn = false, $packages = [], $useCustoms = false)
    {
        $this->orderService->setOrder($order);
        $this->orderService->setShipment($orderShipment);

        $shipment = [
            'orderId' => $order->getIncrementId(),
            'sendingDepot' => $this->dpdSettings->getValue(DpdSettings::ACCOUNT_DEPOT, ScopeInterface::SCOPE_STORE, $order->getStoreId()),
            'sender' => [
                'name1' => $this->dpdSettings->getValue(DpdSettings::SHIPPING_ORIGIN_NAME1, ScopeInterface::SCOPE_STORE, $order->getStoreId()),
                'street' => $this->dpdSettings->getValue(DpdSettings::SHIPPING_ORIGIN_STREET, ScopeInterface::SCOPE_STORE, $order->getStoreId()),
                'housenumber' => $this->dpdSettings->getValue(DpdSettings::SHIPPING_ORIGIN_HOUSE_NUMBER, ScopeInterface::SCOPE_STORE, $order->getStoreId()),
                'country' => $this->dpdSettings->getValue(DpdSettings::SHIPPING_ORIGIN_COUNTRY, ScopeInterface::SCOPE_STORE, $order->getStoreId()),
                'postalcode' => $this->dpdSettings->getValue(DpdSettings::SHIPPING_ORIGIN_ZIP_CODE, ScopeInterface::SCOPE_STORE, $order->getStoreId()),
                'city' => $this->dpdSettings->getValue(DpdSettings::SHIPPING_ORIGIN_CITY, ScopeInterface::SCOPE_STORE, $order->getStoreId()),
                'phoneNumber' => $this->dpdSettings->getValue(DpdSettings::STORE_INFORMATION_PHONE, ScopeInterface::SCOPE_STORE, $order->getStoreId()),
                'email' => $this->dpdSettings->getValue(DpdSettings::STORE_INFORMATION_EMAIL, ScopeInterface::SCOPE_STORE, $order->getStoreId()),
                'commercialAddress' => true,
                'vatnumber' => $this->dpdSettings->getValue(DpdSettings::STORE_INFORMATION_VAT_NUMBER, ScopeInterface::SCOPE_STORE, $order->getStoreId()),
                'eorinumber' => $this->dpdSettings->getValue(DpdSettings::STORE_INFORMATION_EORI, ScopeInterface::SCOPE_STORE, $order->getStoreId()),
                'sprn' => $this->dpdSettings->getValue(DpdSettings::STORE_INFORMATION_SPRN, ScopeInterface::SCOPE_STORE, $order->getStoreId()),
            ],
            'receiver' => $this->getReceiverData($order),
            'product' => [
                'productCode' => $this->getProductCode($order, $orderShipment, $isReturn),
                'saturdayDelivery' => ($this->orderService->isDPDSaturdayOrder() && !$isReturn),
                'homeDelivery' => $this->orderService->isDPDPredictOrder() || $this->orderService->isDPDSaturdayOrder(),
                'ageCheck' => $this->orderService->isAgeCheckOrder()
            ]
        ];

        $shipment['customs'] = [
            'terms' => $this->dpdSettings->getValue(DpdSettings::STORE_INFORMATION_CUSTOMS_TERMS),
            'totalCurrency' => $order->getOrderCurrencyCode(),
            'totalAmount' => (float) $order->getBaseGrandTotal(),
            'customsLines' => $this->addCustomsLines($order),
            'consignor' => [
                'name1' => $this->dpdSettings->getValue(DpdSettings::STORE_INFORMATION_NAME, ScopeInterface::SCOPE_STORE, $order->getStoreId()),
                'street' => $this->dpdSettings->getValue(DpdSettings::STORE_INFORMATION_STREET, ScopeInterface::SCOPE_STORE, $order->getStoreId()),
                'housenumber' => $this->dpdSettings->getValue(DpdSettings::STORE_INFORMATION_HOUSE_NUMBER, ScopeInterface::SCOPE_STORE, $order->getStoreId()),
                'postalcode' => $this->dpdSettings->getValue(DpdSettings::STORE_INFORMATION_ZIP_CODE, ScopeInterface::SCOPE_STORE, $order->getStoreId()),
                'city' => $this->dpdSettings->getValue(DpdSettings::STORE_INFORMATION_CITY, ScopeInterface::SCOPE_STORE, $order->getStoreId()),
                'country' => $this->dpdSettings->getValue(DpdSettings::STORE_INFORMATION_COUNTRY, ScopeInterface::SCOPE_STORE, $order->getStoreId()),
                'commercialAddress' => true,
                'vatnumber' => $this->dpdSettings->getValue(DpdSettings::STORE_INFORMATION_VAT_NUMBER, ScopeInterface::SCOPE_STORE, $order->getStoreId()),
                'eorinumber' => $this->dpdSettings->getValue(DpdSettings::STORE_INFORMATION_EORI, ScopeInterface::SCOPE_STORE, $order->getStoreId()),
                'sprn' => $this->dpdSettings->getValue(DpdSettings::STORE_INFORMATION_SPRN, ScopeInterface::SCOPE_STORE, $order->getStoreId()),
            ],
            'consignee' => $this->getReceiverData($order)
        ];

        // The customs/packages popup when creating a new shipment is the only way to have multiple parcels for a single
        // shipment

        if (is_array($packages)) {
            $shipment['parcels'] = $this->addParcelsFromPackages($order, $orderShipment, $packages);
        } else {
            $shipment['parcels'] = $this->addParcels($order, $orderShipment, $isReturn, 1);
        }

        if (!$isReturn) {
            if ($this->orderService->isDPDPredictOrder() || $this->orderService->isDPDSaturdayOrder()) {
                $shipment['notifications'][] = [
                    'subject' => 'predict',
                    'channel' => 'EMAIL',
                    'value' => $order->getCustomerEmail(),
                ];
            }

            if ($this->orderService->isDPDPickupOrder()) {
                $parcelShopId = $order->getDpdParcelshopId();
                $shipment['product']['parcelshopId'] = $parcelShopId;
                $shipment['notifications'][] = [
                    'subject' => 'parcelshop',
                    'channel' => 'EMAIL',
                    'value' => $order->getCustomerEmail(),
                ];
            }
        }

        return $shipment;
    }

    /**
     * @param Order $order
     * @param bool $isReturn
     * @param int $parcelAmount
     * @return array
     */
    private function addCustomsLines(Order $order)
    {
        $customsLines = [];

        foreach ($order->getItems() as $item) {
            /** @var \Magento\Catalog\Model\Product $product */
            $product = $item->getProduct();
            $originCountry = $this->dpdSettings->getValue(DpdSettings::SHIPPING_ORIGIN_COUNTRY, ScopeInterface::SCOPE_STORE, $order->getStoreId());
            $hsCode = '';

            if ($product !== null) {
                $originCountry = $product->getCountryOfManufacture() ?? $originCountry;
                $hsCode = $product->getHsCode() ?? '';
            }
            $customsLines[] = [
                'description' => mb_strcut($item['name'], 0, 35),
                'harmonizedSystemCode' => $hsCode,
                'originCountry' => $originCountry,
                'quantity' => (int) $item->getQtyOrdered(),
                'netWeight' => (int) $item->getWeight(),
                'grossWeight' => (int) $item->getWeight(),
                'totalAmount' => (float) ($item->getPriceInclTax()),
            ];
        }

        return $customsLines;
    }

    /**
     * @param OrderAddressInterface $address
     *
     * @return array
     */
    private function processAddress(OrderAddressInterface $address): array
    {
        $street = $address->getStreet();
        $fullStreet = implode(' ', $street);

        return array(
            'name1'             => $address->getFirstname() . ' ' . $address->getLastname(),
            'name2'             => $address->getCompany(),
            'street'            => $fullStreet,
            'houseNo'           => '',
            'postalcode'        => strtoupper(str_replace(' ', '', $address->getPostcode())),
            'city'              => $address->getCity(),
            'country'           => $address->getCountryId(),
            'phoneNumber'       => $address->getTelephone(),
            'email'             => '',
            'commercialAddress' => false
        );
    }
}
