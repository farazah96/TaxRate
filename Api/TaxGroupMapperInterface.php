<?php
declare(strict_types=1);

namespace TaxRates\Api;

interface TaxGroupMapperInterface
{
    /**
     * @param string $country
     * @param string $postCode
     * @param bool $useVat
     * @return string
     */
    public function mapByCountryAndPostCode(string $country, string $postCode, bool $useVat = true): string;
}
