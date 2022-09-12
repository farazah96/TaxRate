<?php
declare(strict_types=1);

namespace TaxRates\Model\Calculation\Rate;

use Magento\Framework\App\ObjectManager;
use Magento\Framework\Locale\FormatInterface;
use Magento\Tax\Api\Data\TaxRateInterface;
use Magento\Tax\Api\Data\TaxRateInterfaceFactory;
use Magento\Tax\Api\Data\TaxRateTitleInterfaceFactory;
use Magento\Tax\Model\Calculation\Rate;
use Magento\Tax\Model\Calculation\Rate\Converter;
use TaxRates\Api\TaxRateRepositoryInterface as TaxRateData;

class TaxConverter extends Converter
{
    private ?FormatInterface $format;

    public function __construct(
        TaxRateInterfaceFactory      $taxRateDataObjectFactory,
        TaxRateTitleInterfaceFactory $taxRateTitleDataObjectFactory,
        FormatInterface              $format = null
    ) {
        $this->format = $format ?: ObjectManager::getInstance()->get(FormatInterface::class);
        parent::__construct($taxRateDataObjectFactory, $taxRateTitleDataObjectFactory);
    }

    /**
     * @param array $formData
     * @return TaxRateInterface
     */
    public function populateTaxRateData($formData): TaxRateInterface
    {
        /** @var Rate $taxRate */
        $taxRate = $this->taxRateDataObjectFactory->create();
        $taxRate->setId((int)$this->extractFormData($formData, 'tax_calculation_rate_id'))
            ->setTaxCountryId((string)$this->extractFormData($formData, TaxRateData::TAX_COUNTRY_ID))
            ->setTaxRegionId((int)$this->extractFormData($formData, TaxRateData::TAX_REGION_ID))
            ->setTaxPostcode((string)$this->extractFormData($formData, TaxRateData::TAX_POSTCODE))
            ->setCode((string)$this->extractFormData($formData, TaxRateData::CODE))
            ->setRate($this->format instanceof FormatInterface ?
                (float)$this->format->getNumber(
                    (string)$this->extractFormData($formData, TaxRateData::TAX_RATE)
                ) : 0.0000);
        if (isset($formData[TaxRateData::ZIP_IS_RANGE]) && $formData[TaxRateData::ZIP_IS_RANGE]) {
            $taxRate->setZipFrom((int)$this->extractFormData($formData, TaxRateData::ZIP_RANGE_FROM))
                ->setZipTo((int)$this->extractFormData($formData, TaxRateData::ZIP_RANGE_TO))
                ->setZipIsRange(1);
        }
        $taxRate->setData(
            TaxRateData::TAX_GROUP_VAT,
            $formData[TaxRateData::TAX_GROUP_VAT] ??
            $this->extractFormData($formData, TaxRateData::TAX_GROUP_VAT)
        );
        $taxRate->setData(
            TaxRateData::TAX_GROUP_NO_VAT,
            $formData[TaxRateData::TAX_GROUP_NO_VAT] ??
            $this->extractFormData($formData, TaxRateData::TAX_GROUP_NO_VAT)
        );

        if (isset($formData['title'])) {
            $titles = [];
            foreach ($formData['title'] as $storeId => $value) {
                $titles[] = $this->taxRateTitleDataObjectFactory->create()->setStoreId($storeId)->setValue($value);
            }

            $taxRate->setTitles($titles);
        }

        return $taxRate;
    }
}
