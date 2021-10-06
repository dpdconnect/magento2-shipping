<?php

namespace DpdConnect\Shipping\Setup;

use Magento\Catalog\Model\Product;
use Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\UpgradeDataInterface;
use Magento\Quote\Setup\QuoteSetupFactory;
use Magento\Sales\Model\Order;
use Magento\Sales\Setup\SalesSetupFactory;

/**
 * @codeCoverageIgnore
 */
class UpgradeData implements UpgradeDataInterface
{
    /**
     * @var SalesSetupFactory
     */
    protected $salesSetupFactory;

    /**
     * @var QuoteSetupFactory
     */
    protected $quoteSetupFactory;
    /**
     * @var EavSetupFactory
     */
    private $eavSetupFactory;

    /**
     * @param SalesSetupFactory $salesSetupFactory
     * @param QuoteSetupFactory $quoteSetupFactory
     * @param EavSetupFactory $eavSetupFactory
     */
    public function __construct(
        SalesSetupFactory $salesSetupFactory,
        QuoteSetupFactory $quoteSetupFactory,
        EavSetupFactory $eavSetupFactory
    ) {
        $this->salesSetupFactory = $salesSetupFactory;
        $this->quoteSetupFactory = $quoteSetupFactory;
        $this->eavSetupFactory = $eavSetupFactory;
    }

    /**
     * {@inheritdoc}
     */
    public function upgrade(ModuleDataSetupInterface $setup, ModuleContextInterface $context)
    {
        /**
         * Prepare database for install
         */
        $setup->startSetup();

        if (version_compare($context->getVersion(), '1.0.0') < 0) {
            $this->createCustomsProductAttributes($setup);
        }

        if (version_compare($context->getVersion(), '1.0.1') < 0) {
            // This can not be done in UpgradeSchema, you have to use SalesSetup otherwise
            // things break with flat orders
            $this->createParcelShopFieldsInOrder($setup);
        }

        if (version_compare($context->getVersion(), '1.0.6') < 0) {
            $this->createAgeCheckProductAttributes($setup);
        }

        if (version_compare($context->getVersion(), '1.0.7') < 0) {
            $this->createDpdFreshProductAttributes($setup);
        }

        if (version_compare($context->getVersion(), '1.0.8') < 0) {
            $this->createDpdCarrierFieldsInQuote($setup);
        }

        /**
         * Prepare database after install
         */
        $setup->endSetup();
    }

    private function createDpdCarrierFieldsInQuote(ModuleDataSetupInterface $setup)
    {
        $salesInstaller = $this->salesSetupFactory->create(['resourceName' => 'sales_setup', 'setup' => $setup]);

        // Same columns as with UpgradeSchema in the Quote table
        $parcelshopColumns = [
            'dpd_shipping_product' => [
                'type' => 'varchar',
                'visible' => false,
                'default' => '',
            ],
        ];

        foreach ($parcelshopColumns as $columnName => $options) {
            $salesInstaller->addAttribute(
                Order::ENTITY,
                $columnName,
                $options
            );
        }
    }

    private function createDpdFreshProductAttributes(ModuleDataSetupInterface $setup)
    {
        $eavSetup = $this->eavSetupFactory->create(['setup' => $setup]);

        $eavSetup->addAttribute(
            Product::ENTITY,
            'dpd_shipping_type',
            [
                'type' => 'varchar',
                'backend' => '',
                'frontend' => '',
                'label' => 'DPD Shipping Product',
                'input' => 'select',
                'class' => '',
                "source"   => 'DpdConnect\Shipping\Model\Attribute\Source\ShippingType',
                'global' => ScopedAttributeInterface::SCOPE_GLOBAL,
                'visible' => true,
                'required' => false,
                'user_defined' => true,
                'default' => 'default',
                'searchable' => false,
                'filterable' => false,
                'comparable' => false,
                'visible_on_front' => false,
                'used_in_product_listing' => false,
                'unique' => false,
                'apply_to' => '',
                'group' => 'DPD Product Attributes',
            ]
        );

        $eavSetup->addAttribute(
            Product::ENTITY,
            'dpd_fresh_description',
            [
                'type' => 'varchar',
                'backend' => 'DpdConnect\Shipping\Model\Attribute\Backend\ShippingDescription',
                'frontend' => '',
                'label' => 'DPD Fresh Shipping Description',
                'input' => 'text',
                'class' => '',
                "source"   => '',
                'global' => ScopedAttributeInterface::SCOPE_GLOBAL,
                'visible' => true,
                'required' => false,
                'user_defined' => true,
                'default' => '',
                'searchable' => false,
                'filterable' => false,
                'comparable' => false,
                'visible_on_front' => false,
                'used_in_product_listing' => false,
                'unique' => false,
                'apply_to' => '',
                'group' => 'DPD Product Attributes',
            ]
        );
    }

    private function createAgeCheckProductAttributes(ModuleDataSetupInterface $setup)
    {
        $eavSetup = $this->eavSetupFactory->create(['setup' => $setup]);

        $eavSetup->addAttribute(
            Product::ENTITY,
            'age_check',
            [
                'type' => 'int',
                'backend' => '',
                'frontend' => '',
                'label' => 'Age check',
                'input' => 'boolean',
                'class' => '',
                "source"   => 'Magento\Eav\Model\Entity\Attribute\Source\Boolean',
                'global' => ScopedAttributeInterface::SCOPE_GLOBAL,
                'visible' => true,
                'required' => false,
                'user_defined' => true,
                'default' => null,
                'searchable' => false,
                'filterable' => false,
                'comparable' => false,
                'visible_on_front' => false,
                'used_in_product_listing' => false,
                'unique' => false,
                'apply_to' => '',
                'group' => 'DPD Product Attributes',
            ]
        );
    }

    private function createParcelShopFieldsInOrder(ModuleDataSetupInterface $setup)
    {
        $salesInstaller = $this->salesSetupFactory->create(['resourceName' => 'sales_setup', 'setup' => $setup]);

        // Same columns as with UpgradeSchema in the Quote table
        $parcelshopColumns = [
            'dpd_parcelshop_id' => [
                'type' => 'varchar',
                'visible' => false,
                'default' => '',
            ],
            'dpd_parcelshop_name' => [
                'type' => 'varchar',
                'visible' => false,
                'default' => '',
            ],
            'dpd_parcelshop_street' => [
                'type' => 'varchar',
                'visible' => false,
                'default' => '',
            ],
            'dpd_parcelshop_house_number' => [
                'type' => 'varchar',
                'visible' => false,
                'default' => '',
            ],
            'dpd_parcelshop_zip_code' => [
                'type' => 'varchar',
                'visible' => false,
                'default' => '',
            ],
            'dpd_parcelshop_city' => [
                'type' => 'varchar',
                'visible' => false,
                'default' => '',
            ],
            'dpd_parcelshop_country' => [
                'type' => 'varchar',
                'visible' => false,
                'default' => '',
            ],
        ];

        foreach ($parcelshopColumns as $columnName => $options) {
            $salesInstaller->addAttribute(
                Order::ENTITY,
                $columnName,
                $options
            );
        }
    }

    private function createCustomsProductAttributes(ModuleDataSetupInterface $setup)
    {
        $eavSetup = $this->eavSetupFactory->create(['setup' => $setup]);

        $eavSetup->addAttribute(
            Product::ENTITY,
            'hs_code',
            [
                'type' => 'varchar',
                'backend' => '',
                'frontend' => '',
                'label' => 'Harmonized System Code',
                'input' => 'text',
                'class' => '',
                'source' => '',
                'global' => ScopedAttributeInterface::SCOPE_GLOBAL,
                'visible' => true,
                'required' => false,
                'user_defined' => true,
                'default' => null,
                'searchable' => false,
                'filterable' => false,
                'comparable' => false,
                'visible_on_front' => false,
                'used_in_product_listing' => false,
                'unique' => false,
                'apply_to' => '',
                'group' => 'DPD Product Attributes',
            ]
        );

        $eavSetup->addAttribute(
            Product::ENTITY,
            'export_description',
            [
                'type' => 'varchar',
                'backend' => '',
                'frontend' => '',
                'label' => 'Export description',
                'input' => 'text',
                'class' => '',
                'source' => '',
                'global' => ScopedAttributeInterface::SCOPE_GLOBAL,
                'visible' => true,
                'required' => false,
                'user_defined' => true,
                'default' => null,
                'searchable' => false,
                'filterable' => false,
                'comparable' => false,
                'visible_on_front' => false,
                'used_in_product_listing' => false,
                'unique' => false,
                'apply_to' => '',
                'group' => 'DPD Product Attributes',
            ]
        );
    }
}
