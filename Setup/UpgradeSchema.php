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

namespace DpdConnect\Shipping\Setup;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\Setup\UpgradeSchemaInterface;

class UpgradeSchema implements UpgradeSchemaInterface
{
    private $scopeConfig;
    private $configWriter;

    /**
     * UpgradeSchema constructor.
     * @param ScopeConfigInterface $scopeConfig
     * @param WriterInterface $configWriter
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        WriterInterface $configWriter
    ) {
        $this->scopeConfig = $scopeConfig;
        $this->configWriter = $configWriter;
    }

    public function upgrade(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();

        if (version_compare($context->getVersion(), '1.0.1') < 0) {
            $this->createParcelShopFieldsInQuote($setup);
        }

        if (version_compare($context->getVersion(), '1.0.2') < 0) {
            $this->createDpdTableRate($setup);
        }

        if (version_compare($context->getVersion(), '1.0.3') < 0) {
            $this->createDpdLabelTable($setup);
        }

        if (version_compare($context->getVersion(), '1.0.4') < 0) {
            $this->createDpdBatchTable($setup);
            $this->createDpdBatchItemTable($setup);
        }

        if (version_compare($context->getVersion(), '1.0.5') < 0) {
            $setup->getConnection()->modifyColumn(
                $setup->getTable('dpdconnect_shipping_tablerate'),
                'condition_name',
                ['length' => 30, 'type' => Table::TYPE_TEXT, 'nullable' => false]
            );
        }

        if (version_compare($context->getVersion(), '1.0.8') < 0) {
            $this->createDpdCarrierFieldsInQuote($setup);
        }

        $setup->endSetup();
    }

    private function createDpdCarrierFieldsInQuote(SchemaSetupInterface $setup)
    {
        $parcelshopColumns = [
            'dpd_shipping_product' => [
                'type' => Table::TYPE_TEXT,
                'nullable' => true,
                'comment' => 'DPD ShippingProduct Code',
            ],
        ];

        $quoteTable = $setup->getTable('quote');
        $connection = $setup->getConnection();

        foreach ($parcelshopColumns as $columnName => $options) {
            if ($connection->tableColumnExists($quoteTable, $columnName) === false) {
                $connection->addColumn($quoteTable, $columnName, $options);
            }
        }
    }

    private function createParcelShopFieldsInQuote(SchemaSetupInterface $setup)
    {
        $parcelshopColumns = [
            'dpd_parcelshop_id' => [
                'type' => Table::TYPE_TEXT,
                'nullable' => true,
                'comment' => 'DPD Parcelshop ID',
            ],
            'dpd_parcelshop_name' => [
                'type' => Table::TYPE_TEXT,
                'nullable' => true,
                'comment' => 'DPD Parcelshop Name',
            ],
            'dpd_parcelshop_street' => [
                'type' => Table::TYPE_TEXT,
                'nullable' => true,
                'comment' => 'DPD Parcelshop Street',
            ],
            'dpd_parcelshop_house_number' => [
                'type' => Table::TYPE_TEXT,
                'nullable' => true,
                'comment' => 'DPD Parcelshop House number',
            ],
            'dpd_parcelshop_zip_code' => [
                'type' => Table::TYPE_TEXT,
                'nullable' => true,
                'comment' => 'DPD Parcelshop Zip Code',
            ],
            'dpd_parcelshop_city' => [
                'type' => Table::TYPE_TEXT,
                'nullable' => true,
                'comment' => 'DPD Parcelshop City',
            ],
            'dpd_parcelshop_country' => [
                'type' => Table::TYPE_TEXT,
                'nullable' => true,
                'comment' => 'DPD Parcelshop Country',
            ],
        ];

        $quoteTable = $setup->getTable('quote');
        $connection = $setup->getConnection();

        foreach ($parcelshopColumns as $columnName => $options) {
            if ($connection->tableColumnExists($quoteTable, $columnName) === false) {
                $connection->addColumn($quoteTable, $columnName, $options);
            }
        }
    }

    private function createDpdTableRate(SchemaSetupInterface $setup)
    {
        if ($setup->tableExists($setup->getTable('dpdconnect_shipping_tablerate'))) {
            return;
        }

        /**
         * Create table 'dpdconnect_shipping_tablerate'
         */
        $table = $setup->getConnection()->newTable(
            $setup->getTable('dpdconnect_shipping_tablerate')
        )->addColumn(
            'pk',
            Table::TYPE_INTEGER,
            null,
            ['identity' => true, 'unsigned' => true, 'nullable' => false, 'primary' => true],
            'Primary key'
        )->addColumn(
            'shipping_method',
            Table::TYPE_TEXT,
            150,
            ['nullable' => false, 'default' => ''],
            'DPD shipping method name'
        )->addColumn(
            'website_id',
            Table::TYPE_INTEGER,
            null,
            ['nullable' => false, 'default' => '0'],
            'Website Id'
        )->addColumn(
            'dest_country_id',
            Table::TYPE_TEXT,
            4,
            ['nullable' => false, 'default' => '0'],
            'Destination coutry ISO/2 or ISO/3 code'
        )->addColumn(
            'dest_region_id',
            Table::TYPE_INTEGER,
            null,
            ['nullable' => false, 'default' => '0'],
            'Destination Region Id'
        )->addColumn(
            'dest_zip',
            Table::TYPE_TEXT,
            10,
            ['nullable' => false, 'default' => '*'],
            'Destination Post Code (Zip)'
        )->addColumn(
            'condition_name',
            Table::TYPE_TEXT,
            30,
            ['nullable' => false],
            'Rate Condition name'
        )->addColumn(
            'condition_value',
            Table::TYPE_DECIMAL,
            '12,4',
            ['nullable' => false, 'default' => '0.0000'],
            'Rate condition value'
        )->addColumn(
            'price',
            Table::TYPE_DECIMAL,
            '12,4',
            ['nullable' => false, 'default' => '0.0000'],
            'Price'
        )->addColumn(
            'cost',
            Table::TYPE_DECIMAL,
            '12,4',
            ['nullable' => false, 'default' => '0.0000'],
            'Cost'
        )->addIndex(
            $setup->getIdxName(
                'dpdconnect_shipping_tablerate',
                [
                    'shipping_method',
                    'website_id',
                    'dest_country_id',
                    'dest_region_id',
                    'dest_zip',
                    'condition_name',
                    'condition_value'
                ],
                AdapterInterface::INDEX_TYPE_UNIQUE
            ),
            [
                'shipping_method',
                'website_id',
                'dest_country_id',
                'dest_region_id',
                'dest_zip',
                'condition_name',
                'condition_value'
            ],
            ['type' => AdapterInterface::INDEX_TYPE_UNIQUE]
        )->setComment(
            'DPD Shipping Tablerate'
        );
        $setup->getConnection()->createTable($table);
    }

    private function createDpdLabelTable(SchemaSetupInterface $setup)
    {
        if ($setup->tableExists($setup->getTable('dpdconnect_shipping_label'))) {
            return;
        }
        /**
         * Create table 'dpdconnect_shipping_label'
         */

        $table = $setup->getConnection()->newTable(
            $setup->getTable('dpdconnect_shipping_label')
        )->addColumn(
            'entity_id',
            Table::TYPE_INTEGER,
            null,
            ['identity' => true, 'unsigned' => true, 'nullable' => false, 'primary' => true]
        )->addColumn(
            'created_at',
            Table::TYPE_TIMESTAMP,
            '',
            ['nullable' => false, 'default' => Table::TIMESTAMP_INIT],
            'Creation Time'
        )->addColumn(
            'updated_at',
            Table::TYPE_TIMESTAMP,
            null,
            ['nullable' => false, 'default' => Table::TIMESTAMP_INIT_UPDATE],
            'Modification Time'
        )->addColumn(
            'order_id',
            Table::TYPE_INTEGER,
            null,
            ['unsigned' => true, 'nullable' => false]
        )->addColumn(
            'order_increment_id',
            Table::TYPE_TEXT,
            32,
            ['nullable' => false]
        )->addColumn(
            'shipment_id',
            Table::TYPE_INTEGER,
            null,
            ['unsigned' => true, 'nullable' => false]
        )->addColumn(
            'shipment_increment_id',
            Table::TYPE_TEXT,
            32,
            ['nullable' => false]
        )->addColumn(
            'carrier_code',
            Table::TYPE_TEXT,
            32,
            ['nullable' => false]
        )->addColumn(
            'mps_id',
            Table::TYPE_TEXT,
            255,
            ['nullable' => false]
        )->addColumn(
            'label_numbers',
            Table::TYPE_TEXT,
            null,
            ['nullable' => false]
        )->addColumn(
            'label',
            Table::TYPE_BLOB,
            '32M',
            ['nullable' => false]
        )->addColumn(
            'label_path',
            Table::TYPE_TEXT,
            65536,
            ['nullable' => true]
        )->addColumn(
            'is_return',
            Table::TYPE_INTEGER,
            1,
            ['nullable' => false]
        )->addIndex(
            $setup->getIdxName(
                'dpdconnect_shipping_label',
                ['entity_id'],
                AdapterInterface::INDEX_TYPE_UNIQUE
            ),
            ['entity_id'],
            ['type' => AdapterInterface::INDEX_TYPE_UNIQUE]
        )->setComment(
            'DPD Shipping Labels'
        );
        $setup->getConnection()->createTable($table);
    }

    private function createDpdBatchTable(SchemaSetupInterface $setup)
    {
        $table = $setup->getConnection()->newTable(
            $setup->getTable('dpdconnect_shipping_batch')
        )->addColumn(
            'entity_id',
            Table::TYPE_INTEGER,
            null,
            ['identity' => true, 'nullable' => false, 'primary' => true, 'unsigned' => true],
            'Entity ID'
        )->addColumn(
            'created_at',
            Table::TYPE_TIMESTAMP,
            null,
            ['nullable' => false, 'default' => Table::TIMESTAMP_INIT],
            'Creation Time'
        )->addColumn(
            'updated_at',
            Table::TYPE_TIMESTAMP,
            null,
            ['nullable' => false, 'default' => Table::TIMESTAMP_INIT_UPDATE],
            'Modification Time'
        )->addColumn(
            'status',
            Table::TYPE_TEXT,
            50,
            ['nullable' => true, 'default' => ''],
            'Status'
        );

        $setup->getConnection()->createTable($table);
    }

    private function createDpdBatchItemTable(SchemaSetupInterface $setup)
    {
        $table = $setup->getConnection()->newTable(
            $setup->getTable('dpdconnect_shipping_batch_job')
        )->addColumn(
            'entity_id',
            Table::TYPE_INTEGER,
            null,
            ['identity' => true, 'nullable' => false, 'primary' => true, 'unsigned' => true],
            'Entity ID'
        )->addColumn(
            'batch_id',
            Table::TYPE_INTEGER,
            null,
            ['unsigned' => true, 'nullable' => false],
            'Batch Id'
        )->addColumn(
            'created_at',
            Table::TYPE_TIMESTAMP,
            null,
            ['nullable' => false, 'default' => Table::TIMESTAMP_INIT],
            'Creation Time'
        )->addColumn(
            'updated_at',
            Table::TYPE_TIMESTAMP,
            null,
            ['nullable' => false, 'default' => Table::TIMESTAMP_INIT_UPDATE],
            'Modification Time'
        )->addColumn(
            'order_id',
            Table::TYPE_INTEGER,
            null,
            ['unsigned' => true, 'nullable' => true]
        )->addColumn(
            'order_increment_id',
            Table::TYPE_TEXT,
            32,
            ['nullable' => false]
        )->addColumn(
            'shipment_id',
            Table::TYPE_INTEGER,
            null,
            ['unsigned' => true, 'nullable' => true]
        )->addColumn(
            'shipment_increment_id',
            Table::TYPE_TEXT,
            32,
            ['nullable' => false]
        )->addColumn(
            'job_id',
            Table::TYPE_TEXT,
            100,
            ['unsigned' => true, 'nullable' => true],
            'Job Id'
        )->addColumn(
            'error_message',
            Table::TYPE_TEXT,
            null,
            ['nullable' => true],
            'Status'
        )->addColumn(
            'type',
            Table::TYPE_TEXT,
            50,
            ['nullable' => true, 'default' => 'regular'],
            'Status'
        )->addColumn(
            'status',
            Table::TYPE_TEXT,
            50,
            ['nullable' => true, 'default' => 'queued'],
            'Status'
        );

        $table->addForeignKey(
            $setup->getFkName(
                $setup->getTable('dpdconnect_shipping_batch_job'),
                'batch_id',
                $setup->getTable('dpdconnect_shipping_batch'),
                'entity_id'
            ),
            'batch_id',
            $setup->getTable('dpdconnect_shipping_batch'),
            'entity_id',
            Table::ACTION_CASCADE
        );

        $table->addForeignKey(
            $setup->getFkName(
                $setup->getTable('dpdconnect_shipping_batch_job'),
                'shipment_id',
                $setup->getTable('sales_shipment'),
                'entity_id'
            ),
            'shipment_id',
            $setup->getTable('sales_shipment'),
            'entity_id',
            Table::ACTION_CASCADE
        );

        $table->addForeignKey(
            $setup->getFkName(
                $setup->getTable('dpdconnect_shipping_batch_job'),
                'order_id',
                $setup->getTable('sales_order'),
                'entity_id'
            ),
            'order_id',
            $setup->getTable('sales_order'),
            'entity_id',
            Table::ACTION_CASCADE
        );

        $setup->getConnection()->createTable($table);
    }
}
