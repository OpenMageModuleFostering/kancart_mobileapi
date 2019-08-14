<?php

/**
 * KanCart
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@kancart.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade KanCart to newer
 * versions in the future. If you wish to customize KanCart for your
 * needs please refer to http://www.kancart.com for more information.
 *
 * @copyright  Copyright (c) 2011 kancart.com (http://www.kancart.com)
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Kancart_MobileApi_Model_Currency extends Kancart_MobileApi_Model_Abstract {

    protected $_symbolsData = array();

    /**
     * Config path to custom currency symbol value
     */
    const XML_PATH_CUSTOM_CURRENCY_SYMBOL = 'currency/options/customsymbol';
    const XML_PATH_ALLOWED_CURRENCIES = 'currency/options/allow';

    /*
     * Separator used in config in allowed currencies list
     */
    const ALLOWED_CURRENCIES_CONFIG_SEPARATOR = ',';

    public function getCurrencies() {
        $result = Mage::getModel('mobileapi/Result');
        try {
            $currency = Mage::getModel('core/store')->load(Mage::app()->getStore()->getId())->getCurrentCurrencyCode();
            $currencies = $this->formatCurrency($currency);
            if (is_null($currencies)) {
                $result->setResult('', null, null, 'getCurrencies() return null.');
                return $result->returnResult();
            }
            $result->setResult('0x0000', array('currencies' => $currencies));
            return $result->returnResult();
        } catch (Exception $e) {
            $result->setResult('0x0013', null, null, $e->getMessage());
            return $result->returnResult();
        }
    }

    public function formatCurrency($currencyCode) {
        $result = Mage::getModel('mobileapi/Result');
        try {
            $currencyData = $this->getCurrencySymbolsData();
            $currencies = array();
            foreach ($currencyData as $code => $currencyInfo) {
                $data = array();
                $data['currency_code'] = $code;
                $data['currency_symbol'] = $currencyInfo['displaySymbol'];
                $data['currency_symbol_right'] = FALSE;
                $data['decimal_symbol'] = ".";
                $data['group_symbol'] = ",";
                $data['decimal_places'] = 2;
                $data['description'] = $currencyInfo['displayName'];
                $currencies[] = $data;
            }
            return $currencies;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Returns currency symbol properties array based on config values
     *
     * @return array
     */
    public function getCurrencySymbolsData() {
        if ($this->_symbolsData) {
            return $this->_symbolsData;
        }

        $this->_symbolsData = array();

        $allowedCurrencies = explode(
                self::ALLOWED_CURRENCIES_CONFIG_SEPARATOR, Mage::getStoreConfig(self::XML_PATH_ALLOWED_CURRENCIES, null)
        );

        /* @var $storeModel Mage_Adminhtml_Model_System_Store */
        $storeModel = Mage::getSingleton('adminhtml/system_store');
        foreach ($storeModel->getWebsiteCollection() as $website) {
            $websiteShow = false;
            foreach ($storeModel->getGroupCollection() as $group) {
                if ($group->getWebsiteId() != $website->getId()) {
                    continue;
                }
                foreach ($storeModel->getStoreCollection() as $store) {
                    if ($store->getGroupId() != $group->getId()) {
                        continue;
                    }
                    if (!$websiteShow) {
                        $websiteShow = true;
                        $websiteSymbols = $website->getConfig(self::XML_PATH_ALLOWED_CURRENCIES);
                        $allowedCurrencies = array_merge($allowedCurrencies, explode(
                                        self::ALLOWED_CURRENCIES_CONFIG_SEPARATOR, $websiteSymbols
                                ));
                    }
                    $storeSymbols = Mage::getStoreConfig(self::XML_PATH_ALLOWED_CURRENCIES, $store);
                    $allowedCurrencies = array_merge($allowedCurrencies, explode(
                                    self::ALLOWED_CURRENCIES_CONFIG_SEPARATOR, $storeSymbols
                            ));
                }
            }
        }
        ksort($allowedCurrencies);

        $currentSymbols = $this->_unserializeStoreConfig(self::XML_PATH_CUSTOM_CURRENCY_SYMBOL);

        /** @var $locale Mage_Core_Model_Locale */
        $locale = Mage::app()->getLocale();
        foreach ($allowedCurrencies as $code) {
            if (!$symbol = $locale->getTranslation($code, 'currencysymbol')) {
                $symbol = $code;
            }
            $name = $locale->getTranslation($code, 'nametocurrency');
            if (!$name) {
                $name = $code;
            }
            $this->_symbolsData[$code] = array(
                'parentSymbol' => $symbol,
                'displayName' => $name
            );

            if (isset($currentSymbols[$code]) && !empty($currentSymbols[$code])) {
                $this->_symbolsData[$code]['displaySymbol'] = $currentSymbols[$code];
            } else {
                $this->_symbolsData[$code]['displaySymbol'] = $this->_symbolsData[$code]['parentSymbol'];
            }
            if ($this->_symbolsData[$code]['parentSymbol'] == $this->_symbolsData[$code]['displaySymbol']) {
                $this->_symbolsData[$code]['inherited'] = true;
            } else {
                $this->_symbolsData[$code]['inherited'] = false;
            }
        }

        return $this->_symbolsData;
    }

    /**
     * Unserialize data from Store Config.
     *
     * @param string $configPath
     * @param int $storeId
     * @return array
     */
    protected function _unserializeStoreConfig($configPath, $storeId = null) {
        $result = array();
        $configData = (string) Mage::getStoreConfig($configPath, $storeId);
        if ($configData) {
            $result = unserialize($configData);
        }

        return is_array($result) ? $result : array();
    }

    public function getConfigCurrencies($path) {
        $read = $this->_getReadAdapter();
        $select = $read->select()
                ->from($this->getTable('core/config_data'))
                ->where($read->quoteInto(' path = ? ', $path))
                ->order(' value ASC ');
        $data = $read->fetchAll($select);
        $tmp_array = array();
        foreach ($data as $configRecord) {
            $tmp_array = array_merge($tmp_array, explode(',', $configRecord['value']));
        }
        $data = array_unique($tmp_array);
        return $data;
    }

}