<?php
declare(strict_types=1);

/**
 * Copyright © Visionet Systems, Inc. All rights reserved.
 */

namespace TaxRates\Repository;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Api\SearchCriteriaBuilderFactory;
use Magento\Framework\Api\SearchCriteriaInterface;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Tax\Api\TaxRateRepositoryInterface as CoreTaxRateRepositoryInterface;
use Magento\Tax\Model\Calculation\Rate;
use TaxRates\Api\TaxRateRepositoryInterface;
use TaxRates\System\Config\TaxConfig;

class TaxRateRepository implements TaxRateRepositoryInterface
{
    private CoreTaxRateRepositoryInterface $rateRepository;
    private TaxConfig $taxConfig;
    private SearchCriteriaBuilder $searchCriteriaBuilder;

    public function __construct(
        CoreTaxRateRepositoryInterface $rateRepository,
        SearchCriteriaBuilderFactory $searchCriteriaBuilderFactory,
        TaxConfig $taxConfig
    ) {
        $this->rateRepository = $rateRepository;
        $this->taxConfig = $taxConfig;
        $this->searchCriteriaBuilder = $searchCriteriaBuilderFactory->create();
    }

    public function getTaxGroupByCountry(string $countryId, int $storeId = null): string
    {
        $defaultTaxGroup = $this->taxConfig->getDefaultTaxGroup($storeId);
        $this->searchCriteriaBuilder->addFilter(TaxRateRepositoryInterface::TAX_COUNTRY_ID, $countryId);
        $this->searchCriteriaBuilder->addFilter(TaxRateRepositoryInterface::TAX_RATE, 0, 'gt');
        $searchCriteria = $this->searchCriteriaBuilder->create();
        $result = $this->getTaxGroupBySearchResult($searchCriteria, $storeId);

        return $result ?: $defaultTaxGroup;
    }

    public function getTaxGroupByCountryPostCode(string $countryId, string $postCode, int $storeId = null): string
    {
        $this->searchCriteriaBuilder->addFilter(TaxRateRepositoryInterface::TAX_COUNTRY_ID, $countryId);
        $this->searchCriteriaBuilder->addFilter(TaxRateRepositoryInterface::TAX_POSTCODE, $postCode);

        $searchCriteria = $this->searchCriteriaBuilder->create();
        $result = $this->getTaxGroupBySearchResult($searchCriteria, $storeId);

        return empty($result) ? $this->getTaxGroupByCountry($countryId) : '';
    }

    /**
     * @throws NoSuchEntityException
     * @throws InputException
     */
    public function getTaxRateByCountryPostCode(string $countryId, string $postCode): Rate
    {
        $this->searchCriteriaBuilder->addFilter(TaxRateRepositoryInterface::TAX_COUNTRY_ID, $countryId);
        $this->searchCriteriaBuilder->addFilter(TaxRateRepositoryInterface::TAX_POSTCODE, $postCode);

        $searchCriteria = $this->searchCriteriaBuilder->create();
        $searchCriteria->setPageSize(1);

        $result = $this->rateRepository->getList($searchCriteria);
        $taxItems = $result->getItems();

        if (!empty($taxItems) && $taxItems[0] instanceof Rate) {
            return $taxItems[0];
        }

        //if tax rate not found for country and post code, we will get for country only
        return $this->getTaxRateByCountry($countryId);
    }

    /**
     * @throws NoSuchEntityException
     * @throws InputException
     */
    public function getTaxRateByCountryRegionId(string $countryId, int $regionId): Rate
    {
        $this->searchCriteriaBuilder->addFilter(TaxRateRepositoryInterface::TAX_COUNTRY_ID, $countryId);
        $this->searchCriteriaBuilder->addFilter(TaxRateRepositoryInterface::TAX_REGION_ID, $regionId);

        $searchCriteria = $this->searchCriteriaBuilder->create();
        $searchCriteria->setPageSize(1);

        $result = $this->rateRepository->getList($searchCriteria);
        $taxItems = $result->getItems();

        if (!empty($taxItems) && $taxItems[0] instanceof Rate) {
            return $taxItems[0];
        }

        //if tax rate not found for country and region, we will get for country only
        return $this->getTaxRateByCountry($countryId);
    }

    private function getTaxGroupBySearchResult(SearchCriteriaInterface $searchCriteria, int $storeId = null): string
    {
        $searchCriteria->setPageSize(1);
        $defaultTaxGroup = '';

        $result = $this->rateRepository->getList($searchCriteria);
        $taxItems = $result->getItems();

        if (!empty($taxItems) && $taxItems[0] instanceof Rate) {
            /** @var Rate $taxItem */
            $taxItem = $taxItems[0];
            return $taxItem->getData(TaxRateRepositoryInterface::TAX_GROUP_VAT) ?: $defaultTaxGroup;
        }

        return $defaultTaxGroup;
    }

    /**
     * @throws NoSuchEntityException
     * @throws InputException
     */
    private function getTaxRateByCountry(string $countryId): Rate
    {
        $this->searchCriteriaBuilder->addFilter(TaxRateRepositoryInterface::TAX_COUNTRY_ID, $countryId);
        $searchCriteria = $this->searchCriteriaBuilder->create();
        $searchCriteria->setPageSize(1);

        $result = $this->rateRepository->getList($searchCriteria);
        $taxItems = $result->getItems();

        if (!empty($taxItems) && $taxItems[0] instanceof Rate) {
            return $taxItems[0];
        }

        throw new NoSuchEntityException(__('Tax rate not found for ' . $countryId));
    }
}
