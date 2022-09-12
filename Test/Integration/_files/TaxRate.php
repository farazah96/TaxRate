<?php
declare(strict_types=1);

use Magento\Framework\Registry;
use Magento\Tax\Api\Data\TaxRateInterface;
use Magento\Tax\Api\TaxRateRepositoryInterface;
use Magento\TestFramework\Helper\Bootstrap;
use TaxRates\Api\TaxRateRepositoryInterface as LocalTaxRateRepositoryInterface;

$objectManager = Bootstrap::getObjectManager();

/** @var TaxRateInterface $taxRate */
$taxRate = $objectManager->create(TaxRateInterface::class);
/** @var TaxRateRepositoryInterface $taxRatetRepository */
$taxRatetRepository = $objectManager->create(TaxRateRepositoryInterface::class);
$taxRate->setTaxCountryId('ES');
$taxRate->setTaxPostcode('TEST_001');
$taxRate->setCode('C-ZONE-TXF*ES*38613');
$taxRate->setTaxRegionId(0);
$taxRate->setRate(21);
$taxRate->setData(LocalTaxRateRepositoryInterface::CL_TAX_GROUP, 'C-ES-STA');
$taxRate->setData(LocalTaxRateRepositoryInterface::TAX_GROUP_VAT, 'C-ES-STA');
$taxRate->setData(LocalTaxRateRepositoryInterface::TAX_GROUP_NO_VAT, 'C-ES-RC');
$taxRate = $taxRatetRepository->save($taxRate);

/** @var Registry $registry */
$registry = $objectManager->get(Registry::class);
$registry->unregister('tax_rate_id');
$registry->register('tax_rate_id', $taxRate->getId());
