<?php
declare(strict_types=1);

/**
 * Copyright © Visionet Systems, Inc. All rights reserved.
 */

namespace TaxRates\Setup\Patch\Data;

use Exception;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Tax\Api\Data\TaxRateInterfaceFactory;
use Magento\Tax\Api\Data\TaxRuleInterfaceFactory;
use Magento\Tax\Api\TaxClassRepositoryInterface;
use Magento\Tax\Api\TaxRateRepositoryInterface;
use Magento\Tax\Api\TaxRuleRepositoryInterface;
use Directory\Api\RegionRepositoryInterface;

class AddTaxRateAndRuleForCanada implements DataPatchInterface
{
    private RegionRepositoryInterface $regionRepository;

    private SearchCriteriaBuilder $searchCriteriaBuilder;

    private TaxClassRepositoryInterface $taxClassRepository;

    private TaxRateInterfaceFactory $taxRateFactory;

    private TaxRuleInterfaceFactory $taxRuleFactory;

    private TaxRateRepositoryInterface $taxRateRepository;

    private TaxRuleRepositoryInterface $taxRuleRepository;

    public function __construct(
        RegionRepositoryInterface $regionRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        TaxClassRepositoryInterface $taxClassRepository,
        TaxRateInterfaceFactory $taxRateFactory,
        TaxRuleInterfaceFactory $taxRuleFactory,
        TaxRateRepositoryInterface $taxRateRepository,
        TaxRuleRepositoryInterface $taxRuleRepository
    ) {
        $this->regionRepository = $regionRepository;
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->taxClassRepository = $taxClassRepository;
        $this->taxRateFactory = $taxRateFactory;
        $this->taxRuleFactory = $taxRuleFactory;
        $this->taxRateRepository = $taxRateRepository;
        $this->taxRuleRepository = $taxRuleRepository;
    }

    public function apply(): void
    {
        try {
            $BCRegion = $this->regionRepository->getRegionByCodeAndCountryId('BC', 'CA');
            $taxRateForBC = $this->taxRateFactory->create();
            $taxRateForBC->setCode('CAN-*-7%');
            $taxRateForBC->setTaxCountryId('CA');
            $taxRateForBC->setTaxRegionId((int)$BCRegion->getId());
            $taxRateForBC->setZipIsRange(0);
            $taxRateForBC->setTaxPostcode('*');
            $taxRateForBC->setRate(7.000);
            $this->taxRateRepository->save($taxRateForBC);

            $SKRegion = $this->regionRepository->getRegionByCodeAndCountryId('SK', 'CA');
            $taxRateForSK = $this->taxRateFactory->create();
            $taxRateForSK->setCode('CAN-*-6%');
            $taxRateForSK->setTaxCountryId('CA');
            $taxRateForSK->setTaxRegionId((int)$SKRegion->getId());
            $taxRateForSK->setZipIsRange(0);
            $taxRateForSK->setTaxPostcode('*');
            $taxRateForSK->setRate(6.000);
            $this->taxRateRepository->save($taxRateForSK);

            $productClassIds = [2];
            $this->searchCriteriaBuilder->addFilter('class_type', 'PRODUCT');
            $this->searchCriteriaBuilder->addFilter('class_name', 'DC020500');
            $searchCriteria = $this->searchCriteriaBuilder->create();
            $taxClassResult = $this->taxClassRepository->getList($searchCriteria);
            if ($taxClassResult->getTotalCount() > 0) {
                $items = $taxClassResult->getItems();
                $taxClass = reset($items);
                $productClassIds[] = $taxClass->getClassId();
            }

            $taxRule = $this->taxRuleFactory->create();
            $taxRule->setCode('Canada Customers');
            $taxRule->setPriority(0);
            $taxRule->setCustomerTaxClassIds([3]);
            $taxRule->setProductTaxClassIds($productClassIds);
            $taxRule->setTaxRateIds([(int)$taxRateForBC->getId(), (int)$taxRateForSK->getId()]);
            $this->taxRuleRepository->save($taxRule);
        } catch (Exception $exception) {
            return;
        }
    }

    public function getAliases(): array
    {
        return [];
    }

    public static function getDependencies(): array
    {
        return [];
    }
}
