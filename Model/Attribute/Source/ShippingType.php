<?php

namespace DpdConnect\Shipping\Model\Attribute\Source;

use DpdConnect\Shipping\Helper\DPDClient;

class ShippingType extends \Magento\Eav\Model\Entity\Attribute\Source\AbstractSource
{
    private $client;

    /**
     * ShippingType constructor.
     *
     * @param DPDClient $client
     */
    public function __construct(DPDClient $client)
    {
        $this->client = $client;
    }

    /**
     * Get all options
     * @return array
     */
    public function getAllOptions()
    {
        if (!$this->_options) {
            $availableProducts = $this->client->authenticate()->getProduct()->getList();
            $availableCodes = array_map(function($product) {
                return $product['code'];
            }, $availableProducts);

            $this->_options = [
                ['label' => __('Default'), 'value' => 'default'],
            ];

            if (true === in_array('FRESH', $availableCodes)) {
                $this->_options[] = ['label' => __('Fresh'), 'value' => 'fresh'];
            }

            if (true === in_array('FREEZE', $availableCodes)) {
                $this->_options[] = ['label' => __('Freeze'), 'value' => 'freeze'];
            }
        }

        return $this->_options;
    }
}
