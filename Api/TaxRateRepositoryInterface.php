<?php
declare(strict_types=1);

/**
 * Copyright © Visionet Systems, Inc. All rights reserved.
 */

namespace TaxRates\Api;

use Magento\Framework\Exception\InputException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Tax\Model\Calculation\Rate;

interface TaxRateRepositoryInterface
{
    public const TAX_CALCULATION_RATE_ID = 'tax_calculation_rate_id';
    public const TAX_COUNTRY_ID = 'tax_country_id';
    public const TAX_REGION_ID = 'tax_region_id';
    public const TAX_POSTCODE = 'tax_postcode';
    public const CL_TAX_GROUP = 'cl_tax_group';
    public const TAX_GROUP_VAT = 'tax_group_vat';
    public const TAX_GROUP_NO_VAT = 'tax_group_no_vat';
    public const TAX_RATE = 'rate';
    public const ZIP_IS_RANGE    = 'zip_is_range';
    public const ZIP_RANGE_FROM  = 'zip_from';
    public const ZIP_RANGE_TO    = 'zip_to';
    public const CODE            = 'code';

    public function getTaxGroupByCountry(string $countryId, int $storeId = null): string;

    public function getTaxGroupByCountryPostCode(string $countryId, string $postCode, int $storeId = null): string;

    /**
     * @throws NoSuchEntityException
     * @throws InputException
     */
    public function getTaxRateByCountryPostCode(string $countryId, string $postCode): Rate;

    /**
     * @throws NoSuchEntityException
     * @throws InputException
     */
    public function getTaxRateByCountryRegionId(string $countryId, int $regionId): Rate;
}
