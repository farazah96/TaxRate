<?php
declare(strict_types=1);

/**
 * Copyright © Visionet Systems, Inc. All rights reserved.
 */

namespace TaxRates\Setup\Patch\Data;

use Exception;
use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Exception\InputException;
use Magento\Framework\Setup\Patch\DataPatchInterface;
use Magento\Tax\Api\TaxRateRepositoryInterface as CoreTaxRateRepository;
use Magento\Tax\Model\Calculation\Rate;
use Psr\Log\LoggerInterface;
use Store\Api\StoreManagerInterface;
use TaxRates\Api\TaxRateRepositoryInterface;
use TaxRates\System\Config\TaxConfig;

class UpdateClTaxGroupDataPatch implements DataPatchInterface
{
    /** @var SearchCriteriaBuilder */
    private $searchCriteriaBuilder;

    /** @var CoreTaxRateRepository */
    private $taxRateRepository;

    /** @var TaxConfig */
    private $taxConfig;

    /** @var StoreManagerInterface */
    private $storeManager;

    /** @var LoggerInterface */
    private $logger;

    public function __construct(
        CoreTaxRateRepository $taxRateRepository,
        SearchCriteriaBuilder $searchCriteriaBuilder,
        TaxConfig $taxConfig,
        StoreManagerInterface $storeManager,
        LoggerInterface $logger
    ) {
        $this->searchCriteriaBuilder = $searchCriteriaBuilder;
        $this->taxRateRepository = $taxRateRepository;
        $this->taxConfig = $taxConfig;
        $this->storeManager = $storeManager;
        $this->logger = $logger;
    }

    public function apply(): void
    {
        $this->searchCriteriaBuilder->addFilter(TaxRateRepositoryInterface::CL_TAX_GROUP, true, 'null');
        $this->searchCriteriaBuilder->addFilter(TaxRateRepositoryInterface::TAX_RATE, 0, 'gt');
        $searchCriteria = $this->searchCriteriaBuilder->create();
        try {
            $result = $this->taxRateRepository->getList($searchCriteria);
        } catch (InputException $e) {
            $this->logger->warning(
                sprintf("UpdateClTaxGroupDataPatch: Unable to get list of tax rates. %s", $e->getMessage())
            );
            return;
        }
        if ($result->getTotalCount() < 1) {
            $this->logger->info("UpdateClTaxGroupDataPatch: There are no tax rates to update");
            return;
        }
        $items = $result->getItems();
        /** @var  Rate $item */
        foreach ($items as $item) {
            $store = $this->storeManager->getByLanguageAndCountry('en', $item->getTaxCountryId());
            if ($store === null) {
                continue;
            }

            $taxGroup = $this->taxConfig->getDefaultTaxGroup((int)$store->getId());
            $item->setData(TaxRateRepositoryInterface::CL_TAX_GROUP, $taxGroup);
            try {
                $this->taxRateRepository->save($item);
            } catch (Exception $e) {
                $this->logger->error(
                    sprintf("UpdateClTaxGroupDataPatch: tax rate save failed. %s", $e->getMessage())
                );
            }
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
