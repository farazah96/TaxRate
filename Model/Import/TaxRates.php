<?php
/**
 * Copyright © Visionet Systems, Inc. All rights reserved.
 */
declare(strict_types=1);

namespace TaxRates\Model\Import;

use Magento\Directory\Model\CountryFactory;
use Magento\Eav\Model\Config;
use Magento\Framework\App\ObjectManager;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Json\Helper\Data as jsonHelper;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Stdlib\StringUtils;
use Magento\ImportExport\Helper\Data as importExportData;
use Magento\ImportExport\Model\Import;
use Magento\ImportExport\Model\Import\Entity\AbstractEntity;
use Magento\ImportExport\Model\Import\ErrorProcessing\ProcessingErrorAggregatorInterface;
use Magento\ImportExport\Model\ResourceModel\Helper as resourceHelper;
use Magento\ImportExport\Model\ResourceModel\Import\Data as importData;

class TaxRates extends AbstractEntity
{
    private const TAX_CALCULATE_TABLE = 'tax_calculation';
    private const CUSTOMER_TAX_CLASS_ID = 3;
    private const PRODUCT_TAX_CLASS_ID = 2;
    private const DEFAULT_TAX_RULE_ID = 2;

    /**
     * @var array
     */
    protected $validColumnNames = [
        'TAXGROUP',
        'COUNTRYREGION',
        'ZIPCODE',
        'TaxPercentage',
        'RegionId',
    ];
    /**
     * @var array
     */
    protected $_permanentAttributes = [
        'TAXGROUP',
        'COUNTRYREGION',
        'ZIPCODE',
        'TaxPercentage',
    ];
    /**
     * Json Serializer Instance
     *
     * @var Json
     */
    private $serializer;
    /**
     * @var \Magento\Directory\Model\CountryFactory
     */
    private $countryFactory;
    /**
     * @var array
     */
    private $taxMappings = [
        'TAXGROUP' => 'cl_tax_group',
        'COUNTRYREGION' => 'tax_country_id',
        'ZIPCODE' => 'tax_postcode',
        'TaxPercentage' => 'rate',
        'RegionId' => 'tax_region_id',
    ];

    public function __construct(
        jsonHelper $jsonHelper,
        importExportData $importExportData,
        importData $importData,
        Config $config,
        ResourceConnection $resource,
        resourceHelper $resourceHelper,
        StringUtils $string,
        ProcessingErrorAggregatorInterface $errorAggregator,
        CountryFactory $countryFactory
    ) {
        $this->jsonHelper = $jsonHelper;
        $this->_importExportData = $importExportData;
        $this->_resourceHelper = $resourceHelper;
        $this->_dataSourceModel = $importData;
        $this->_resource = $resource;
        $this->_connection = $resource->getConnection(\Magento\Framework\App\ResourceConnection::DEFAULT_CONNECTION);
        $this->errorAggregator = $errorAggregator;
        $this->countryFactory = $countryFactory;
    }

    /**
     * @return ProcessingErrorAggregatorInterface
     */
    public function validateData()
    {
        if (!$this->_dataValidated) {
            $this->getErrorAggregator()->clear();
            // do all permanent columns exist?
            $absentColumns = array_diff($this->_permanentAttributes, $this->getSource()->getColNames());
            $this->addErrors(self::ERROR_CODE_COLUMN_NOT_FOUND, $absentColumns);
            // check attribute columns names validity
            $emptyHeaderColumns = [];
            $invalidColumns = [];
            $invalidAttributes = [];
            $this->addErrors(self::ERROR_CODE_INVALID_ATTRIBUTE, $invalidAttributes);
            $this->addErrors(self::ERROR_CODE_COLUMN_EMPTY_HEADER, $emptyHeaderColumns);
            $this->addErrors(self::ERROR_CODE_COLUMN_NAME_INVALID, $invalidColumns);
            if (!$this->getErrorAggregator()->getErrorsCount()) {
                $this->_saveValidatedBunches();
                $this->_dataValidated = true;
            }
        }
        return $this->getErrorAggregator();
    }
    /**
     * @return $this
     */
    public function deleteEntity(): self
    {
        $this->saveAndReplaceEntity();
        return $this;
    }

    public function validateRow(array $rowData, $rowNum): bool
    {
        if (isset($this->_validatedRows[$rowNum])) {
            // check that row is already validated
            return !$this->getErrorAggregator()->isRowInvalid($rowNum);
        }
        $this->_validatedRows[$rowNum] = true;
        $this->_processedEntitiesCount++;
        return !$this->getErrorAggregator()->isRowInvalid($rowNum);
    }

    public function mapImportData($rows): array
    {
        $mappedData = [];
        $validColumns = $this->getValidColumnNames();
        foreach ($rows as $rowData) {
            $mappedRow = [];
            foreach ($rowData as $key => $value) {
                if (!in_array($key, $validColumns)) {
                    continue;
                }

                $mappedRow[$this->taxMappings[$key]] = $value;
            }
            $mappedRow['tax_postcode'] = isset($mappedRow['tax_postcode']) ? $mappedRow['tax_postcode'] : "*";
            $mappedRow['tax_region_id'] = isset($mappedRow['tax_region_id']) ? $mappedRow['tax_region_id'] : "0";
            if (isset($mappedRow['tax_country_id'])) {
                $country = $this->countryFactory->create()->loadByCode($mappedRow['tax_country_id'], 'iso3_code');
                if (!$country->getId()) {
                    continue;
                }

                $mappedRow['tax_country_id'] = $country->getData('iso2_code');
            }
            if (
                !isset(
                    $mappedRow['cl_tax_group'],
                    $mappedRow['tax_country_id'],
                    $mappedRow['tax_postcode'],
                    $mappedRow['rate']
                )
            ) {
                continue;
            }

            $mappedRow['code'] =
                $mappedRow['cl_tax_group'] . "*" . $mappedRow['tax_country_id'] . "*" . $mappedRow['tax_postcode'];
            if (!isset($mappedRow['code'])) {
                continue;
            }

            $mappedData[] = $mappedRow;
        }
        return $mappedData;
    }

    public function getValidColumnNames(): array
    {
        return $this->validColumnNames;
    }

    public function getEntityTypeCode(): string
    {
        return 'tax_calculation_rate';
    }

    /**
     * @return $this
     */
    public function replaceEntity(): self
    {
        $this->saveAndReplaceEntity();
        return $this;
    }

    /**
     * @return $this
     */
    public function saveEntity(): self
    {
        $this->saveAndReplaceEntity();
        return $this;
    }

    /**
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function _saveValidatedBunches(): self
    {
        $source = $this->_getSource();
        $currentDataSize = 0;
        $bunchRows = [];
        $startNewBunch = false;
        $nextRowBackup = [];
        $maxDataSize = $this->_resourceHelper->getMaxDataSize();
        $bunchSize = $this->_importExportData->getBunchSize();
        $source->rewind();
        $this->_dataSourceModel->cleanBunches();

        while ($source->valid() || $bunchRows) {
            if ($startNewBunch || !$source->valid()) {
                $this->_dataSourceModel->saveBunch($this->getEntityTypeCode(), $this->getBehavior(), $bunchRows);
                $bunchRows = $nextRowBackup;
                $currentDataSize = strlen($this->getSerializer()->serialize($bunchRows));
                $startNewBunch = false;
                $nextRowBackup = [];
            }
            if (!$source->valid()) {
                continue;
            }

            try {
                $rowData = $source->current();
            } catch (\InvalidArgumentException $e) {
                $this->addRowError($e->getMessage(), $this->_processedRowsCount);
                $this->_processedRowsCount++;
                $source->next();
                continue;
            }

            $this->_processedRowsCount++;
            if ($this->validateRow($rowData, $source->key())) {
                // add row to bunch for save
                $rowData = $this->_prepareRowForDb($rowData);
                $rowSize = strlen($this->jsonHelper->jsonEncode($rowData));
                $isBunchSizeExceeded = $bunchSize > 0 && count($bunchRows) >= $bunchSize;
                if ($currentDataSize + $rowSize >= $maxDataSize || $isBunchSizeExceeded) {
                    $startNewBunch = true;
                    $nextRowBackup = [$source->key() => $rowData];
                } else {
                    $bunchRows[$source->key()] = $rowData;
                    $currentDataSize += $rowSize;
                }
            }
            $source->next();
        }
        $this->_processedEntitiesCount = $this->_processedRowsCount;
        return $this;
    }

    protected function _importData(): bool
    {
        $behavior = $this->getBehavior();
        $this->_validatedRows = [];
        if ($behavior === Import::BEHAVIOR_DELETE) {
            $this->deleteEntity();
        } elseif ($behavior === Import::BEHAVIOR_REPLACE) {
            $this->replaceEntity();
        } elseif ($behavior === Import::BEHAVIOR_APPEND) {
            $this->saveEntity();
        }
        return true;
    }

    protected function saveAndReplaceEntity(): self
    {
        while ($bunch = $this->_dataSourceModel->getNextBunch()) {
            $validRows = [];
            foreach ($bunch as $rowNum => $rowData) {
                if (!$this->validateRow($rowData, $rowNum)) {
                    $this->addRowError('Invalid row.', $rowNum);
                    continue;
                }
                if ($this->getErrorAggregator()->hasToBeTerminated()) {
                    $this->getErrorAggregator()->addRowToSkip($rowNum);
                    continue;
                }
                $validRows[] = $rowData;
            }
            $mappedData = self::mapImportData($validRows);
            $this->saveEntityFinish($mappedData, $this->getEntityTypeCode());
        }
        return $this;
    }

    protected function saveEntityFinish(array $mappedData, $table): self
    {
        if ($mappedData) {
            $codes = [];
            foreach ($mappedData as $mappedRow) {
                $codes[]['code'] = $mappedRow['code'];
            }
            $tableName = $this->_connection->getTableName($table);
            $this->_connection->delete($tableName, ['code IN (?)' => $codes]);
            $this->countItemsCreated +=
                $this->_connection->insertOnDuplicate(
                    $tableName,
                    array_values($mappedData),
                    array_keys($mappedData)
                );
            $this->saveTaxRule($mappedData, $tableName);
        }
        return $this;
    }

    /**
     * @todo return mutlitple types have to fix it
     * @return Json|mixed
     */
    private function getSerializer()
    {
        if ($this->serializer === null) {
            $this->serializer = ObjectManager::getInstance()->get(Json::class);
        }
        return $this->serializer;
    }

    private function saveTaxRule($mappedData, $tableName): void
    {
        foreach ($mappedData as $mappedRow) {
            $taxCode = $mappedRow['code'];
            $result = $this->_connection->fetchAll(
                "SELECT tax_calculation_rate_id from " . $tableName . " WHERE code='" . $taxCode . "'"
            );
            $this->countItemsCreated +=
                $this->_connection->insert(
                    self::TAX_CALCULATE_TABLE,
                    $this->mapTaxRuleData($result)
                );
        }
    }

    private function mapTaxRuleData(array $zoneIds): array
    {
        return [
            'tax_calculation_rate_id' => $zoneIds[0]['tax_calculation_rate_id'],
            'tax_calculation_rule_id' => self::DEFAULT_TAX_RULE_ID,
            'customer_tax_class_id' => self::CUSTOMER_TAX_CLASS_ID,
            'product_tax_class_id' => self::PRODUCT_TAX_CLASS_ID,
        ];
    }
}
