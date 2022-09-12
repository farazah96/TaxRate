<?php
declare(strict_types=1);

namespace TaxRates\Block\Adminhtml\Rate;

use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Tax\Block\Adminhtml\Rate\Form as MainForm;
use Magento\Tax\Controller\RegistryConstants;
use Magento\Tax\Model\Calculation\Rate;
use TaxRates\Api\TaxRateRepositoryInterface as TaxRateData;

class Form extends MainForm
{
    protected function _prepareForm(): self
    {
        $data = parent::_prepareForm();
        $form = $data->getForm();
        $taxRateId = $this->_coreRegistry->registry(RegistryConstants::CURRENT_TAX_RATE_ID);
        $taxRateDataObject = null;
        try {
            if ($taxRateId) {
                /** @var Rate $taxRateDataObject */
                $taxRateDataObject = $this->_taxRateRepository->get($taxRateId);
            }
            // phpcs:ignore Magento2.CodeAnalysis.EmptyBlock
        } catch (NoSuchEntityException $e) {
            //tax rate not found//
        }

        $sessionFormValues = (array)$this->_coreRegistry->registry(RegistryConstants::CURRENT_TAX_RATE_FORM_DATA);
        $formData = isset($taxRateDataObject)
            ? $this->_taxRateConverter->createArrayFromServiceObject($taxRateDataObject)
            : [];
        $formData = array_merge($formData, $sessionFormValues);
        if ($taxRateId && $taxRateDataObject) {
            $formData[TaxRateData::TAX_GROUP_VAT] = $taxRateDataObject->getData(TaxRateData::TAX_GROUP_VAT);
            $formData[TaxRateData::TAX_GROUP_NO_VAT] = $taxRateDataObject->getData(TaxRateData::TAX_GROUP_NO_VAT);
        }

        $fieldset = $form->getElement('base_fieldset');
        if (!$fieldset) {
            return $this;
        }
        $fieldset->addField(
            TaxRateData::TAX_GROUP_VAT,
            'text',
            [
                'name' => TaxRateData::TAX_GROUP_VAT,
                'label' => __('Tax Group VAT'),
                'title' => __('Tax Group VAT'),
            ]
        );
        $fieldset->addField(
            TaxRateData::TAX_GROUP_NO_VAT,
            'text',
            [
                'name' => TaxRateData::TAX_GROUP_NO_VAT,
                'label' => __('Tax Group No VAT'),
                'title' => __('Tax Group No VAT'),
            ]
        );

        $form->setValues($formData);
        $this->setForm($form);

        return $this;
    }
}
