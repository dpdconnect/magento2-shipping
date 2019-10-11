<?php

namespace DpdConnect\Shipping\Config\Source\Settings;

use Magento\Catalog\Api\Data\EavAttributeInterface;
use Magento\Catalog\Api\Data\ProductAttributeInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Eav\Api\Data\AttributeInterface;
use Magento\Framework\Option\ArrayInterface;
use Magento\Catalog\Model\ResourceModel\Product\Attribute\CollectionFactory;
use Magento\Eav\Model\Entity\Type;

class ProductAttribute implements ArrayInterface
{
    /**
     * @var Type
     */
    private $entityType;

    /**
     * @var CollectionFactory
     */
    private $productAttributeCollection;

    /**
     * ProductAttribute constructor.
     * @param Type $entityType
     * @param CollectionFactory $productAttributeCollection
     */
    public function __construct(
        Type $entityType,
        CollectionFactory $productAttributeCollection
    ) {
        $this->entityType = $entityType;
        $this->productAttributeCollection = $productAttributeCollection;
    }

    /**
     * Returns code => code pairs of attributes for all product attributes
     *
     * @return array
     */
    public function toOptionArray()
    {
        $entityType = $this->entityType->loadByCode(ProductAttributeInterface::ENTITY_TYPE_CODE);

        $attributes = $this->productAttributeCollection->create();

        $attributes = $attributes
            ->addFieldToSelect('attribute_code')
            ->setEntityTypeFilter($entityType->getId())
            ->setOrder('attribute_code', 'ASC')
            ->getItems();

        $attributeArray[] = [
            'label' => ' -- Please Select -- ',
            'value' => ''
        ];

        foreach ($attributes as $attribute) {
            $attributeArray[] = [
                'label' => $attribute->getAttributeCode(),
                'value' => $attribute->getAttributeCode()
            ];
        }

        return $attributeArray;
    }
}
