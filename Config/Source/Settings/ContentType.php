<?php

namespace DpdConnect\Shipping\Config\Source\Settings;

use Magento\Framework\Option\ArrayInterface;

class ContentType implements ArrayInterface
{
    const CONTENT_TYPE_DOC = 'D';
    const CONTENT_TYPE_NON_DOC = 'N';

    /**
     * {@inheritdoc}
     */
    public function toOptionArray()
    {
        return [
            ['label' => __('Documents'), 'value' => self::CONTENT_TYPE_DOC],
            ['label' => __('Non documents'), 'value' => self::CONTENT_TYPE_NON_DOC]
        ];
    }
}
