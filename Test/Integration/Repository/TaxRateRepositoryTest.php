<?php
/**
 * Copyright © Visionet Systems, Inc. All rights reserved.
 */
declare(strict_types=1);

namespace TaxRates\Test\Integration\Repository;

use Magento\Framework\Api\SearchCriteriaBuilder;
use Magento\Framework\Registry;
use Magento\Tax\Api\Data\TaxRateInterface;
use Magento\Tax\Api\TaxRateRepositoryInterface as CoreTaxRateRepositoryInterface;
use Magento\Tax\Model\Calculation\Rate;
use Magento\TestFramework\Helper\Bootstrap;
use Magento\TestFramework\TestCase\AbstractController;
use TaxRates\Api\TaxRateRepositoryInterface;

class TaxRateRepositoryTest extends AbstractController
{
    private $taxRateRepository;
    private $coreTaxRateRepository;
    private $searchCriteriaBuilder;
    private $registry;

    /**
     * @magentoDbIsolation enabled
     * @magentoDataFixture loadFixture
     */
    public function testTaxRatePersistence(): void
    {
        $entityId = $this->registry->registry('tax_rate_id');
        /** @var TaxRateInterface $taxRate */
        $taxRate = $this->coreTaxRateRepository->get($entityId);
        $this->assertEquals('ES', $taxRate->getTaxCountryId());
        $this->assertEquals('TEST_001', $taxRate->getTaxPostcode());
        $this->assertEquals('C-ES-STA', $taxRate->getData(TaxRateRepositoryInterface::TAX_GROUP_VAT));
    }

    /**
     * @magentoDbIsolation enabled
     * @magentoDataFixture loadFixture
     */
    public function testGetTaxGroupByCountry(): void
    {
        $taxGroup = 'C-ES-STA';
        $countryId = 'ES';
        $result = $this->taxRateRepository->getTaxGroupByCountry($countryId);
        $this->assertEquals($taxGroup, $result);
    }

    /**
     * @magentoDbIsolation enabled
     * @magentoDataFixture loadFixture
     */
    public function testTaxGroupByCountryPostCode(): void
    {
        $countryId = 'ES';
        $postCode = 'TEST_001';
        $taxGroup = 'C-ES-STA';
        $result = $this->taxRateRepository->getTaxGroupByCountryPostCode($countryId, $postCode);
        if (empty($result)) {
            $result = $this->taxRateRepository->getTaxGroupByCountry($countryId);
        }
        $this->assertEquals($taxGroup, $result);
    }

    /**
     * @magentoDbIsolation enabled
     * @magentoDataFixture loadFixture
     */
    public function testTaxRateByCountryPostCode(): void
    {
        $countryId = 'ES';
        $postCode = 'TEST_001';
        $result = $this->taxRateRepository->getTaxRateByCountryPostCode($countryId, $postCode);
        $this->assertInstanceOf(Rate::class, $result);
        $this->assertEquals(21.0000, $result->getRate());
    }

    public static function loadFixture(): void
    {
        include __DIR__ . '/../_files/TaxRate.php';
    }

    protected function setUp(): void
    {
        $this->taxRateRepository = Bootstrap::getObjectManager()->create(
            TaxRateRepositoryInterface::class
        );
        $this->coreTaxRateRepository = Bootstrap::getObjectManager()->create(
            CoreTaxRateRepositoryInterface::class
        );
        $this->searchCriteriaBuilder = Bootstrap::getObjectManager()->create(
            SearchCriteriaBuilder::class
        );
        $this->registry = Bootstrap::getObjectManager()->get(
            Registry::class
        );
    }
}
