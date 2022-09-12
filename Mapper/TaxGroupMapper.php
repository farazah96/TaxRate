<?php
declare(strict_types=1);

namespace TaxRates\Mapper;

use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use TaxRates\Api\TaxGroupMapperInterface;
use TaxRates\Api\TaxRateRepositoryInterface;

class TaxGroupMapper implements TaxGroupMapperInterface
{
    private TaxRateRepositoryInterface $taxRateRepository;

    public function __construct(
        TaxRateRepositoryInterface $taxRateRepository
    ) {
        $this->taxRateRepository = $taxRateRepository;
    }

    public function mapByCountryAndPostCode(string $country, string $postCode, bool   $useVat = true): string
    {
        $taxGroup = '';
        try {
            $taxRate = $this->taxRateRepository->getTaxRateByCountryPostCode($country, $postCode);
            return !$useVat ?
                (string)$taxRate->getData(TaxRateRepositoryInterface::TAX_GROUP_VAT) :
                (string)$taxRate->getData(TaxRateRepositoryInterface::TAX_GROUP_NO_VAT);
        } catch (NoSuchEntityException | InputException $exception) {
            return $taxGroup;
        }
    }
}
