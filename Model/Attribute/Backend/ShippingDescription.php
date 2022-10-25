<?php

namespace DpdConnect\Shipping\Model\Attribute\Backend;

class ShippingDescription extends \Magento\Eav\Model\Entity\Attribute\Backend\AbstractBackend
{
    /**
     * Validate
     * @param \Magento\Catalog\Model\Product $object
     * @throws \Magento\Framework\Exception\LocalizedException
     * @return bool
     */
    public function validate($object)
    {
        parent::validate($object);

        $selectedDpdShippingProduct = $object->getData('dpd_shipping_type');
        if (true === in_array($selectedDpdShippingProduct, ['fresh', 'freeze'])) {
            $value = $object->getData($this->getAttribute()->getAttributeCode());

            if ('' === $value || null === $value) {
                return false;
            }
        }

        return true;
    }
}
