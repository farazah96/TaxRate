<?php
/**
 * Copyright © Visionet Systems, Inc. All rights reserved.
 */
namespace TaxRates\Model\Rate;

use Magento\TaxImportExport\Model\Rate\CsvImportHandler as importHander;

/**
 * Class CsvImportHandler
 * Preference Class for Magento\TaxImportExport\Model\Rate\CsvImportHandler
 */
class CsvImportHandler extends importHander
{
    /**
     * Retrieve a list of fields required for CSV file (order is important!)
     *
     * @return array
     */
    public function getRequiredCsvFields()
    {
        // indexes are specified for clarity, they are used during import
        return [
            0 => __('TAXGROUP'),
            2 => __('COUNTRYREGION'),
            3 => __('ZIPCODE'),
            21 => __('TaxPercentage'),
        ];
    }

    /**
     * Import single rate
     *
     * @param array $rateData
     * @param array $regionsCache cache of regions of already used countries (is used to optimize performance)
     * @param array $storesCache cache of stores related to tax rate titles
     * @return array regions cache populated with regions related to country of imported tax rate
     * @throws \Magento\Framework\Exception\LocalizedException
     */
    protected function _importRate(array $rateData, array $regionsCache, array $storesCache)
    {
        // data with index 1 must represent country code
        $countryCode = $rateData[2];
        $country = $this->_countryFactory->create()->loadByCode($countryCode, 'iso3_code');
        if (!$country->getId()) {
            throw new \Magento\Framework\Exception\LocalizedException(__('One of the countries has invalid code.'));
        }
        /* Convert three letter country code to two letter country code */
        $countryCode = $country->getData('iso2_code');
        $regionsCache = $this->_addCountryRegionsToCache($countryCode, $regionsCache);

        // data with index 2 must represent region code
        $regionCode = $rateData[2];
        $postCode = empty($rateData[3]) ? "*" : $rateData[3];
        $modelData = [
            'code' => $rateData[99],
            'tax_country_id' => $countryCode,
            'tax_region_id' => 0,
            'tax_postcode' => $postCode,
            'rate' => $rateData[21],
            'zip_is_range' => null,
            'zip_from' => null,
            'zip_to' => null,
            'cl_tax_group' => $rateData[0],
        ];
       // try to load existing rate
        /** @var $rateModel \Magento\Tax\Model\Calculation\Rate */
        $rateModel = $this->_taxRateFactory->create()->loadByCode($modelData['code']);
        $rateModel->addData($modelData);

        // compose titles list
        $rateTitles = [];
        foreach ($storesCache as $fileFieldIndex => $storeId) {
            $rateTitles[$storeId] = $rateData[$fileFieldIndex];
        }
        $rateModel->setTitle($rateTitles);
        $rateModel->save();
        return $regionsCache;
    }
    /**
     * Filter file fields (i.e. unset invalid fields)
     *
     * @param array $fileFields
     * @return string[] filtered fields
     */
    protected function _filterFileFields(array $fileFields)
    {
        \Magento\Framework\App\ObjectManager::getInstance()
            ->get(\Psr\Log\LoggerInterface::class)->info('import model _filterFileFields');
        $filteredFields = $this->getRequiredCsvFields();
        return $filteredFields;
    }
    /**
     * Filter rates data (i.e. unset all invalid fields and check consistency)
     *
     * @param array $rateRawData
     * @param array $invalidFields assoc array of invalid file fields
     * @param array $validFields assoc array of valid file fields
     * @return array
     * @throws \Magento\Framework\Exception\LocalizedException
     * @SuppressWarnings(PHPMD.UnusedLocalVariable)
     */
    protected function _filterRateData(array $rateRawData, array $invalidFields, array $validFields)
    {
        \Magento\Framework\App\ObjectManager::getInstance()
            ->get(\Psr\Log\LoggerInterface::class)->info('import model _filterRateData');
        $validFieldsNum = count($validFields);
        foreach ($rateRawData as $rowIndex => $dataRow) {
            // skip empty rows
            if (count($dataRow) <= 1) {
                unset($rateRawData[$rowIndex]);
                continue;
            }
            // unset invalid fields from data row
            foreach ($dataRow as $fieldIndex => $fieldValue) {
                if (isset($invalidFields[$fieldIndex])) {
                    unset($rateRawData[$rowIndex][$fieldIndex]);
                }
                $rateRawData[$rowIndex][99] =
                    $rateRawData[$rowIndex][0]."*".
                    $rateRawData[$rowIndex][2]."*".
                    $rateRawData[$rowIndex][3]."*".
                    $rateRawData[$rowIndex][21]."%";
            }
            // check if number of fields in row match with number of valid fields + our extra code field
            if (count($rateRawData[$rowIndex]) != $validFieldsNum+1) {
                throw new \Magento\Framework\Exception\LocalizedException(__('Invalid file format.'));
            }
        }
        return $rateRawData;
    }
}
