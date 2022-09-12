<?php
/**
 * Copyright © Visionet Systems, Inc. All rights reserved.
 */
namespace TaxRates\Setup;

use Magento\Framework\Setup\InstallSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;

/**
 * Class InstallSchema
 * Schema Installer Class for tax_calculation_rate
 */
class InstallSchema implements InstallSchemaInterface
{
    /**
     * @param SchemaSetupInterface $setup
     * @param ModuleContextInterface $context
     */
    public function install(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();

        if ($setup->getConnection()->isTableExists($setup->getTable('tax_calculation_rate')) == true) {
            $setup->getConnection()->addColumn(
                $setup->getTable('tax_calculation_rate'),
                'cl_tax_group',
                [
                    'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT, // Or any other type
                    'nullable' => true, // Or false
                    'comment' => 'CL Tax Group'
                ]
            );
        }

        if ($setup->getConnection()->isTableExists($setup->getTable('quote')) == true) {
            $setup->getConnection()->addColumn(
                $setup->getTable('quote'),
                'cl_tax_group',
                [
                    'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT, // Or any other type
                    'nullable' => true, // Or false
                    'comment' => 'CL Tax Group'
                ]
            );
        }

        if ($setup->getConnection()->isTableExists($setup->getTable('sales_order')) == true) {
            $setup->getConnection()->addColumn(
                $setup->getTable('sales_order'),
                'cl_tax_group',
                [
                    'type' => \Magento\Framework\DB\Ddl\Table::TYPE_TEXT, // Or any other type
                    'nullable' => true, // Or false
                    'comment' => 'CL Tax Group'
                ]
            );
        }
    }
}
