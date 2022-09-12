<?php
/**
 * Copyright © Visionet Systems, Inc. All rights reserved.
 */
namespace TaxRates\Plugin;

/**
 * Class Converter
 * Plugin Class for Magento\Tax\Model\Calculation
 */

use Psr\Log\LoggerInterface;

class Converter
{
    /** @var \Magento\Tax\Model\ResourceModel\Calculation\CollectionFactory  */
    private $collectionFactory;
    /** @var \Magento\Checkout\Model\Session  */
    private $checkoutSession;
    /**
     * @var \Magento\Quote\Api\CartRepositoryInterface
     */
    protected $quoteRepository;
    /**
     * Converter constructor.
     * @param \Magento\Tax\Model\ResourceModel\Calculation\CollectionFactory $collectionFactory
     * @param \Magento\Checkout\Model\Session $checkoutSession
     */
    public function __construct(
        \Magento\Tax\Model\ResourceModel\Calculation\CollectionFactory $collectionFactory,
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Quote\Api\CartRepositoryInterface $quoteRepository
    ) {
        $this->collectionFactory = $collectionFactory;
        $this->checkoutSession = $checkoutSession;
        $this->quoteRepository = $quoteRepository;
    }

    /**
     * @param \Magento\Tax\Model\Calculation $subject
     * @param $result
     * @param $request
     * @return mixed
     * @throws \Magento\Framework\Exception\LocalizedException
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function afterGetAppliedRates(\Magento\Tax\Model\Calculation $subject, $result, $request)
    {
        if (isset($result[0]['rates'][0]['code'])) {
            $rateCode = $result[0]['rates'][0]['code'];
            $getTaxByRuleId = $this->collectionFactory->create()
                ->addFieldToFilter('tax_calculation_rule_id', $result[0]['rates'][0]['rule_id']);
            $getTaxByRuleId->getSelect()->join(
                ['rate' => 'tax_calculation_rate'],
                'rate.tax_calculation_rate_id = main_table.tax_calculation_rate_id',
                [
                    'rate.cl_tax_group',
                ]
            )->where('rate.code="'.$rateCode.'"');

            $appliedRate = $getTaxByRuleId->getFirstItem();
            if ($appliedRate) {
                $appliedTaxCode = $appliedRate->getData("cl_tax_group");
                $quoteId= $this->checkoutSession->getQuoteId();
                if($quoteId){
                    $this->quoteRepository->get($quoteId)->setClTaxGroup($appliedTaxCode)->save();
                }
            }
        }
        return $result;
    }
}
