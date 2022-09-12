<?php
declare(strict_types=1);

/**
 * Copyright © Visionet Systems, Inc. All rights reserved.
 */

namespace TaxRates\System\Config;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;

class TaxConfig
{
    private const XML_PATH_TV_CART_TAX_GROUP = 'tvcart/general_config/tax_group';

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var StoreManagerInterface
     */
    private $storeManager;

    public function __construct(ScopeConfigInterface $scopeConfig, StoreManagerInterface $storeManager)
    {
        $this->scopeConfig = $scopeConfig;
        $this->storeManager = $storeManager;
    }

    public function getDefaultTaxGroup(int $storeId = null): string
    {
        return $this->getConfigValue(
            self::XML_PATH_TV_CART_TAX_GROUP,
            $storeId
        );
    }

    private function getConfigValue(string $path, int $storeId = null): string
    {
        try {
            $storeId = $storeId ?? (int)$this->storeManager->getStore()->getId();
        } catch (NoSuchEntityException $e) {
            $storeId = 0;
        }

        return (string)$this->scopeConfig->getValue(
            $path,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }
}
