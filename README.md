---
title: README

---

# TaxRates  

## General  

The purpose of this module is to customize the Magento core tax importer module to function correctly with the CSV file we have from AX.  

## Customization  

| table-name           | field-name    | description                               |
|:---------------------|:--------------|:------------------------------------------|
| tax_calculation_rate | cl_tax_group  | Field to save the Commerce-Link tax group |
| quote                | cl_tax_group  | Field to save the Commerce-Link tax group |
| sales_order          | cl_tax_group  | Field to save the Commerce-Link tax group |

## Plugins  

| type-class                    | plugin-class                         | name             |
|:------------------------------|:-------------------------------------|:-----------------|
| Magento\Tax\Model\Calculation | TaxRates\Plugin\Converter | get_cl_attribute |

#### ```TaxRates\Plugin\Converter```  
The purpose of this plugin is to add the ``cl_tax_group`` custom field while adding the tax data to the quote.  

## Rewrites  

| source-class                                        | custom-class                                            |
|:----------------------------------------------------|:--------------------------------------------------------|
| Magento\TaxImportExport\Model\Rate\CsvImportHandler | TaxRates\Model\Rate\CsvImportHandler         |
| Magento\Tax\Block\Adminhtml\Rate\Form               | TaxRates\Block\Adminhtml\Rate\Form           |
| Magento\Tax\Model\Calculation\Rate\Converter        | TaxRates\Model\Calculation\Rate\TaxConverter |
| TaxRates\Repository\TaxRateRepository        | TaxRates\Api\TaxRateRepositoryInterface |

#### ```\TaxRates\Model\Rate\CsvImportHandler```  
Extended magento tax importer module to accommodate the custom field we have in AX csv file.  

#### ```\TaxRates\Block\Adminhtml\Rate\Form```  
Added custom ``cl_tax_group`` field in admin tax form in admin panel.  

#### ```\TaxRates\Model\Calculation\Rate\TaxConverter```  
Added ``cl_tax_group`` field to tax rate data object. This rewrite was necessary because we have to set the  
``cl_tax_group`` value in taxRateData Object which was created in the function body.  

#### ```TaxRates\Api\TaxRateRepositoryInterface```
This class has two methods ``TaxRates\Api\TaxRateRepositoryInterface::getTaxGroupByCountry`` will return  
tax group by country id and ``TaxRates\Api\TaxRateRepositoryInterface::getTaxGroupByCountryPostCode``  
will return tax group by country id and postcode.

## Mappers

#### ```\\TaxRates\Mapper\TaxGroupMapper```
This mapper will return tax group by country and zip code, if vat is true then will return b2c else b2b group.

## Integration  

#### Inbound / Import  

```etc/import.xml``` is used to add 'tax_calculation_rate' into the Magento scheduled import entity list.  

## Known Issues  

- ```\TaxRates\Model\Rate\CsvImportHandler``` contains unused local variable `$regionCode`.   
- ```\TaxRates\Model\Rate\CsvImportHandler``` contains usage of a deprecated method `save()`.  
- ```\TaxRates\Model\Rate\CsvImportHandler``` contains unhandled exception.  
- ```\TaxRates\Block\Adminhtml\Rate\Form``` contains use of some deprecated methods.  
- ```\TaxRates\Model\Import\TaxRates``` contains unused local variables `$columnNumber`, `$behavior` and `$skuSet`.  
- ```\TaxRates\Model\Import\TaxRates``` contains unused constructor parameters ``\Magento\Framework\Stdlib\StringUtils $string`` and ``\Magento\Eav\Model\Config $config``.  
- ```\TaxRates\Model\Import\TaxRates``` contains deprecated class `\Magento\Framework\Json\Helper\Data` call in constructor.  
- ```\TaxRates\Model\Import\TaxRates``` contains dynamically created field `_resource`.   
