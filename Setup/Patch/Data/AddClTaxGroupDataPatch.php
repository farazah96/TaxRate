<?php
declare(strict_types=1);

/**
 * Copyright © Visionet Systems, Inc. All rights reserved.
 */

namespace TaxRates\Setup\Patch\Data;

use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use TaxRates\Api\TaxRateRepositoryInterface;

class AddClTaxGroupDataPatch implements DataPatchInterface
{
    private const ADD_TAX_COUNTRIES = [
        'UA' => 'UKR', 'BH' => 'BHR', 'MD' => 'MDA', 'OM' => 'OMN', 'KE' => 'KEN', 'KZ' => 'KAZ', 'GH' => 'GHA',
    ];

    private const TAX_RATES = [
        'UA' => 20, 'BH' => 10, 'MD' => 20, 'OM' => 5, 'KE' => 16, 'KZ' => 12, 'GH' => 12.5,
    ];

    private const TABLE_TAX_RATE = 'tax_calculation_rate';
    private const TABLE_TAX_RULE = 'tax_calculation_rule';
    private const TABLE_TAX_CALCULATION = 'tax_calculation';
    private const ZERO_PERCENT_TITLE = '0% Tax';
    private const NON_ZERO_PERCENT_TITLE = 'Belgium Customers';
    private const ADDITIONAL_DATA = [
        self::ZERO_PERCENT_TITLE => [
            'customer_tax_class_id' => 10,
            'product_tax_class_id' => 11,

        ],
        self::NON_ZERO_PERCENT_TITLE => [
            'customer_tax_class_id' => 3,
            'product_tax_class_id' => 2,
        ],
    ];

    private ModuleDataSetupInterface $moduleDataSetup;

    public function __construct(
        ModuleDataSetupInterface $moduleDataSetup
    ) {
        $this->moduleDataSetup = $moduleDataSetup;
    }

    public function apply(): AddClTaxGroupDataPatch
    {
        $connection = $this->moduleDataSetup->getConnection();
        $connection->startSetup();

        $taxCalculationRateIds = $this->getZeroTaxExistingTaxRateIds($connection);
        /** Add tax rate for other countries */
        $taxGroupsData = [];
        foreach (self::ADD_TAX_COUNTRIES as $twoCountry => $threeLetterCountry) {
            $vatGroup = 'C-' . $twoCountry . '-STA';
            $nonVatGroup = 'C-' . $twoCountry . '-RC';
            if (array_key_exists($twoCountry, $taxCalculationRateIds)) {
                $rateId = $taxCalculationRateIds[$twoCountry];
                $bind = [
                    TaxRateRepositoryInterface::TAX_GROUP_VAT => $vatGroup,
                    TaxRateRepositoryInterface::TAX_GROUP_NO_VAT => $nonVatGroup,
                ];
                $where = [
                    TaxRateRepositoryInterface::TAX_CALCULATION_RATE_ID . ' = ?' => $rateId,
                ];
                $connection->update(self::TABLE_TAX_RATE, $bind, $where);
                continue;
            }
            $taxGroupsData[] = [
                TaxRateRepositoryInterface::TAX_COUNTRY_ID => $twoCountry,
                TaxRateRepositoryInterface::TAX_REGION_ID => '0',
                TaxRateRepositoryInterface::TAX_POSTCODE => '*',
                TaxRateRepositoryInterface::CODE => $threeLetterCountry . '-*-0%',
                TaxRateRepositoryInterface::TAX_RATE => 0.0000,
                TaxRateRepositoryInterface::ZIP_IS_RANGE => null,
                TaxRateRepositoryInterface::ZIP_RANGE_FROM => null,
                TaxRateRepositoryInterface::ZIP_RANGE_TO => null,
                TaxRateRepositoryInterface::TAX_GROUP_VAT => $vatGroup,
                TaxRateRepositoryInterface::TAX_GROUP_NO_VAT => $nonVatGroup,
            ];
        }
        /** Insert new country to tax rates */
        $this->insertTaxRates($connection, $taxGroupsData);
        $taxCalculationRateIds = $this->getZeroTaxExistingTaxRateIds($connection);
        $this->associateTaxRateAndRule($connection, self::ZERO_PERCENT_TITLE, $taxCalculationRateIds);
        /** Add tax rates greater than 0 */
        $this->addTaxRatesForCountries($connection);
        $connection->endSetup();

        return $this;
    }

    public function getAliases(): array
    {
        return [];
    }

    public static function getDependencies(): array
    {
        return [];
    }

    private function addTaxRatesForCountries(AdapterInterface $connection): void
    {
        /** Add tax rate for other countries */
        $taxGroupsData = [];
        foreach (self::ADD_TAX_COUNTRIES as $twoCountry => $threeLetterCountry) {
            $vatGroup = 'C-' . $twoCountry . '-STA';
            $nonVatGroup = 'C-' . $twoCountry . '-RC';
            $taxPercentage = self::TAX_RATES[$twoCountry];
            $taxGroupsData[] = [
                TaxRateRepositoryInterface::TAX_COUNTRY_ID => $twoCountry,
                TaxRateRepositoryInterface::TAX_REGION_ID => '0',
                TaxRateRepositoryInterface::TAX_POSTCODE => '*',
                TaxRateRepositoryInterface::CODE => $threeLetterCountry . '-*-' . floor($taxPercentage) . '%',
                TaxRateRepositoryInterface::TAX_RATE => $taxPercentage,
                TaxRateRepositoryInterface::ZIP_IS_RANGE => null,
                TaxRateRepositoryInterface::ZIP_RANGE_FROM => null,
                TaxRateRepositoryInterface::ZIP_RANGE_TO => null,
                TaxRateRepositoryInterface::TAX_GROUP_VAT => $vatGroup,
                TaxRateRepositoryInterface::TAX_GROUP_NO_VAT => $nonVatGroup,
            ];
        }

        /** Insert new country to tax rates */
        $this->insertTaxRates($connection, $taxGroupsData);
        $taxCalculationRateIds = $this->getNonZeroTaxExistingTaxRateIds($connection);
        $this->associateTaxRateAndRule($connection, self::NON_ZERO_PERCENT_TITLE, $taxCalculationRateIds);
    }

    private function getZeroTaxExistingTaxRateIds(AdapterInterface $connection): array
    {
        $rateIds = [];
        $select = $connection->select()
            ->from(
                $connection->getTableName(self::TABLE_TAX_RATE),
                ['tax_calculation_rate_id', 'tax_country_id']
            )
            ->where(
                TaxRateRepositoryInterface::TAX_COUNTRY_ID . ' IN(?)',
                array_keys(self::ADD_TAX_COUNTRIES)
            )->where(
                TaxRateRepositoryInterface::TAX_RATE . ' < ?',
                1
            );
        $results = $connection->fetchAll($select);
        if (count($results) === 0) {
            return $rateIds;
        }

        foreach ($results as $result) {
            $rateIds[$result['tax_country_id']] = $result['tax_calculation_rate_id'];
        }

        return $rateIds;
    }

    private function getNonZeroTaxExistingTaxRateIds(AdapterInterface $connection): array
    {
        $rateIds = [];
        $select = $connection->select()
            ->from(
                $connection->getTableName(self::TABLE_TAX_RATE),
                ['tax_calculation_rate_id', 'tax_country_id']
            )
            ->where(
                TaxRateRepositoryInterface::TAX_COUNTRY_ID . ' IN(?)',
                array_keys(self::ADD_TAX_COUNTRIES)
            )->where(
                TaxRateRepositoryInterface::TAX_RATE . ' > ?',
                0
            );
        $results = $connection->fetchAll($select);
        if (count($results) === 0) {
            return $rateIds;
        }

        foreach ($results as $result) {
            $rateIds[$result['tax_country_id']] = $result['tax_calculation_rate_id'];
        }

        return $rateIds;
    }

    private function insertTaxRates(AdapterInterface $connection, array $taxGroupsData): void
    {
        if (count($taxGroupsData) === 0) {
            return;
        }
        $connection->insertArray(
            $connection->getTableName(self::TABLE_TAX_RATE),
            [
                TaxRateRepositoryInterface::TAX_COUNTRY_ID,
                TaxRateRepositoryInterface::TAX_REGION_ID,
                TaxRateRepositoryInterface::TAX_POSTCODE,
                TaxRateRepositoryInterface::CODE,
                TaxRateRepositoryInterface::TAX_RATE,
                TaxRateRepositoryInterface::ZIP_IS_RANGE,
                TaxRateRepositoryInterface::ZIP_RANGE_FROM,
                TaxRateRepositoryInterface::ZIP_RANGE_TO,
                TaxRateRepositoryInterface::TAX_GROUP_VAT,
                TaxRateRepositoryInterface::TAX_GROUP_NO_VAT,
            ],
            $taxGroupsData
        );
    }

    private function getPercentTaxRuleId(AdapterInterface $connection, string $code): ?int
    {
        $select = $connection->select()
            ->from(
                $connection->getTableName(self::TABLE_TAX_RULE),
                ['tax_calculation_rule_id']
            )
            ->where(
                TaxRateRepositoryInterface::CODE . ' =?',
                $code
            );
        $taxCalculationRuleId =  $connection->fetchOne($select);
        if (!$taxCalculationRuleId) {
            return null;
        }

        return (int)$taxCalculationRuleId;
    }

    private function associateTaxRateAndRule(AdapterInterface $connection, string $code, array $taxRateIds): void
    {
        $ruleId = $this->getPercentTaxRuleId($connection, $code);
        if (!$ruleId) {
            return;
        }
        $taxCalculationData = [];
        foreach ($taxRateIds as $taxRateId) {
            $taxCalculationData[] = [
                'tax_calculation_rate_id' => $taxRateId,
                'tax_calculation_rule_id' => $ruleId,
                'customer_tax_class_id' => self::ADDITIONAL_DATA[$code]['customer_tax_class_id'],
                'product_tax_class_id' => self::ADDITIONAL_DATA[$code]['product_tax_class_id'],
            ];
        }

        $connection->insertArray(
            $connection->getTableName(self::TABLE_TAX_CALCULATION),
            [
                'tax_calculation_rate_id',
                'tax_calculation_rule_id',
                'customer_tax_class_id',
                'product_tax_class_id',
            ],
            $taxCalculationData
        );
    }
}
