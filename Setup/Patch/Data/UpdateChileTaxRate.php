<?php
declare(strict_types=1);

/**
 * Copyright © Visionet Systems, Inc. All rights reserved.
 */

namespace TaxRates\Setup\Patch\Data;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Tax\Api\TaxRateRepositoryInterface;

class UpdateChileTaxRate implements DataPatchInterface
{
    /** @var SearchCriteriaBuilder  */
    private $searchCriteriaBuilder;

    /** @var TaxRateRepositoryInterface  */
    private $taxRateRepository;

    public function __construct(
        TaxRateRepositoryInterface $taxRateRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder
    ) {
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->taxRateRepository = $taxRateRepository;
    }

    public function apply(): void
    {
        $this->searchCriteriaBuilder->addFilter('tax_country_id', 'CL');
        $searchCriteria = $this->searchCriteriaBuilder->create();
        $result = $this->taxRateRepository->getList($searchCriteria);
        if ($result->getTotalCount() < 1) {
            return;
        }

        $items = $result->getItems();
        $taxRate = reset($items);
        $taxRate->setRate(19.0);
        $this->taxRateRepository->save($taxRate);
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
