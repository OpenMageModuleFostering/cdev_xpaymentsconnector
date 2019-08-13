<?php
// vim: set ts=4 sw=4 sts=4 et:
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * @author     Qualiteam Software <info@x-cart.com>
 * @category   Cdev
 * @package    Cdev_XPaymentsConnector
 * @copyright  (c) 2010-present Qualiteam software Ltd <info@x-cart.com>. All rights reserved
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Helper for settings
 */

class Cdev_XPaymentsConnector_Helper_Settings_Data extends Cdev_XPaymentsConnector_Helper_Abstract 
{
    /**
     * Maximum quantity of the payment methods that can be enabled
     */
    const MAX_SLOTS = 3;

    /**
     * List of tabs
     */
    const TAB_WELCOME         = 'welcome';
    const TAB_CONNECTION      = 'connection';
    const TAB_PAYMENT_METHODS = 'payment_methods';
    const TAB_ZERO_AUTH       = 'zero_auth';

    // Configuration settings path
    const XPATH_XPC         = 'xpaymentsconnector/settings/';
    const XPATH_PAYMENT     = 'payment/xpayments';
    const XPATH_SAVED_CARDS = 'payment/savedcards/';

    // Validation regexp for serialized bundle
    const BUNDLE_REGEXP = '/a:[56]:{s:8:"store_id";s:\d+:"[0-9a-z]+";s:3:"url";s:\d+:"[^"]+";s:10:"public_key";s:\d+:"-----BEGIN CERTIFICATE-----[^"]+-----END CERTIFICATE-----";s:11:"private_key";s:\d+:"-----BEGIN [A-Z ]*PRIVATE KEY-----[^"]+-----END [A-Z ]*PRIVATE KEY-----";s:20:"private_key_password";s:32:".{32}";(s:9:"server_ip";s:\d+:"[0-9a-fA-F\.:]*";)?}/s';

    // Requirements errors
    const REQ_CURL    = 'PHP extension cURL is not installed on your server';
    const REQ_OPENSSL = 'PHP extension OpenSSL is not installed on your server';
    const REQ_DOM     = 'PHP extension DOM is not installed on your server';

    // Configuration errors
    const CONF_CART_ID          = 'Store ID is empty or has an incorrect value';
    const CONF_URL              = 'X-Payments URL is empty or has an incorrect value';
    const CONF_PUBLIC_KEY       = 'Public key is empty';
    const CONF_PRIVATE_KEY      = 'Private key is empty';
    const CONF_PRIVATE_KEY_PASS = 'Private key password is empty';
    const CONF_API_VERSION      = 'API version is empty';

    /**
     * Disabled zero auth method index
     */
    const ZERO_AUTH_DISABLED = -1;

    /**
     * List of supported API versions
     */
    private $apiVersions = array(
        '1.7',
        '1.6',
    );

    /**
     * Api Version. Check connection result.
     */
    private $apiVersion = null;

    /**
     * Tab names
     */
    private $tabs = array(
        self::TAB_WELCOME         => 'Welcome',
        self::TAB_CONNECTION      => 'Connection settings',
        self::TAB_PAYMENT_METHODS => 'Payment methods',
        self::TAB_ZERO_AUTH       => 'Save credit card setup',
    );

    /**
     * List of configuration errors
     */
    private static $configurationErrors = null;

    /**
     * List of server requirements errors
     */
    private static $requirementsErrors = null;

    /**
     * Flag indicating if at least one of the payment configurations allows to save cards
     */
    private static $canSaveCardsFlag = null;

    /**
     * Zero auth mehod model
     */
    private static $zeroAuthMethod = null;

    /**
     * Is it necessary to re-check connection
     */
    private static $recheckFlag = false;

    /**
     * Flag indicating configuration is in progress
     * To prevent concurent requests to X-Payments
     */
    private static $configurationInProgressFlag = false;

    /**
     * Map of fields stored in the bundle
     */
    private $bundleMap = array(
        'xpay_cart_id'          => 'store_id',
        'xpay_url'              => 'url',
        'xpay_public_key'       => 'public_key',
        'xpay_private_key'      => 'private_key',
        'xpay_private_key_pass' => 'private_key_password',
    );

    /**
     * Constructor
     *
     * @return void
     */
    public function _construct()
    {
        parent::_construct();

        // Translate tabs
        foreach ($this->tabs as $tab => $tabName) {
            $this->tabs[$tab] = Mage::helper('adminhtml')->__($tabName);
        }
    }

    /**
     * Get tabs
     *
     * @return array
     */
    public function getTabs()
    {
        return $this->tabs;
    }

    /**
     * Set flag to force the connection check.
     * And null other flags/cache just in case.
     *
     * Flag is reverted after the connection check is done.
     *
     * @return void
     */
    public function setRecheckFlag()
    {
        static::$configurationErrors = null;
        static::$requirementsErrors = null;
        static::$canSaveCardsFlag = null;
        static::$zeroAuthMethod = null;

        static::$recheckFlag = true;
    }

    /**
     * Check if X-Payments Connector module is configured
     *
     * @return bool
     */
    public function isConfigured()
    {
        // If configuration is in progress return true to avoid the concurent checking
        if (static::$configurationInProgressFlag) {
            return true;
        }

        if (static::$recheckFlag) {

            // Prevent concurent requests when configuration is in progress
            // E.g. do not obtain API version during payment confs import
            static::$configurationInProgressFlag = true;

            try {

                $result = false;

                $configuration = $this->getXpcConfig('xpay_conf_bundle');

                if (!empty($configuration)) {

                    $errors = $this->getConfigurationErrors();

                    if (empty($errors)) {
                        $result = true;
                    }
                }

                $this->setXpcConfig('xpay_is_configured', $result, true);

            } catch (Exception $exception) {

                // Switch off configuration in progress flag for any exception
                static::$configurationInProgressFlag = false;

                // And throw exception further
                throw $exception;
            }

            // Switch off configuration checking flags
            static::$recheckFlag = false;
            static::$configurationInProgressFlag = false;
        }

        return $this->getXpcConfig('xpay_is_configured');
    }

    /**
     * Check connection with X-Payments via test request. Set the API version
     *
     * @return string Error message during communication with X-Payments (if any) 
     */
    private function obtainApiVersion()
    {
        $isUpgradeNeeded = false;

        $errors = array();

        try {

            $this->setXpcConfig('xpay_api_version', '', true);

            foreach ($this->apiVersions as $version) {

                $response = Mage::helper('api_xpc')->testConnection($version);

                if ($response->getStatus()) {

                    $this->setXpcConfig('xpay_api_version', $version, true);
                    break;

                } elseif (!empty($response->getErrorCode())) {

                    if ('506' == $response->getErrorCode()) {
                        $isUpgradeNeeded = true;
                    }

                    $errors[] = $response->getErrorMessage();
                }
            }

            if (!$this->getXpcConfig('xpay_api_version')) {

                if ($isUpgradeNeeded) {
                    $error = 'Unable to detect supported API version for your X-Payments installation. You should upgrade X-Payments or X-Payments connector extension';
                } else {
                    $error = implode(PHP_EOL, $errors);
                }

            } else {

                $error = false;
            }

        } catch (Exception $e) {

            $error = $e->getMessage();
        }

        return $error;
    }

    /**
     * Check if some external checkout module is enablled and activated
     *
     * @param string $name Module name
     * @param string $configPath Configuration path to activate the module
     *
     * @return bool
     */
    private function checkExternalCheckoutModuleEnabled($name, $configPath)
    {
        $modules = Mage::getConfig()->getNode('modules')->children();
        $modules = (array)$modules;

        $result = false;

        if (isset($modules[$name])) {

            $module = $modules[$name];

            if ($module->active) {
                $result = (bool)Mage::getStoreConfig($configPath, Mage::app()->getStore());
            }
        }

        return $result;
    }

    /**
     * Check if Idev OneStepCheckout module is enablled and activated
     *
     * @return bool
     */
    public function checkOscModuleEnabled()
    {
        return $this->checkExternalCheckoutModuleEnabled(
            'Idev_OneStepCheckout',
            'onestepcheckout/general/rewrite_checkout_links'
        );
    }

    /**
     * Check if Firecheckout module is enablled and activated
     *
     * @return bool
     */
    public function checkFirecheckoutModuleEnabled()
    {
        return $this->checkExternalCheckoutModuleEnabled(
            'TM_FireCheckout',
            'firecheckout/general/enabled'
        );
    }

    /**
     * Check if iframe should be used or not
     *
     * @return bool
     */
    public function isUseIframe()
    {
        return $this->checkOscModuleEnabled()
            || $this->checkFirecheckoutModuleEnabled()
            || $this->getXpcConfig('xpay_use_iframe'); 
    }

    /**
     * Get place to display iframe. Review or payment step of checkout
     *
     * @return string "payment" or "review"
     */
    public function getIframePlace()
    {
        $place = $this->getXpcConfig('xpay_iframe_place');

        if (
            $this->checkOscModuleEnabled()
            || $this->checkFirecheckoutModuleEnabled()
        ) {
            $place = 'payment';
        } elseif (
            $place != 'payment'
            && $place != 'review'
        ) {
            $place = 'payment';
        }

        return $place;
    }

    /**
     * Get configuration errors list
     *
     * @return array 
     */
    public function getConfigurationErrors()
    {
        if (is_array(static::$configurationErrors)) {
            return static::$configurationErrors;
        }

        static::$configurationErrors = array();

        // Check shopping cart id
        if (
            !$this->getXpcConfig('xpay_cart_id')
            || !preg_match('/^[\da-f]{32}$/Ss', $this->getXpcConfig('xpay_cart_id'))
        ) {
            static::$configurationErrors[] = self::CONF_CART_ID;
        }

        // Check URL
        if (!$this->getXpcConfig('xpay_url')) {
            static::$configurationErrors[] = self::CONF_URL;
        }

        $parsed_url = @parse_url($this->getXpcConfig('xpay_url'));

        if (!$parsed_url || !isset($parsed_url['scheme']) || $parsed_url['scheme'] != 'https') {
            static::$configurationErrors[] = self::CONF_URL;
        }

        // Check public key
        if (!$this->getXpcConfig('xpay_public_key')) {
            static::$configurationErrors[] = self::CONF_PUBLIC_KEY;
        }

        // Check private key
        if (!$this->getXpcConfig('xpay_private_key')) {
            static::$configurationErrors[] = self::CONF_PRIVATE_KEY;
        }

        // Check private key password
        if (!$this->getXpcConfig('xpay_private_key_pass')) {
            static::$configurationErrors[] = self::CONF_PRIVATE_KEY_PASS;
        }

        // Obtain API version from X-Payments if necessary
        if (static::$recheckFlag) {
            $error = $this->obtainApiVersion();
        }

        // Check API version
        if (!$this->getXpcConfig('xpay_api_version')) {
            static::$configurationErrors[] = !empty($error) ? $error : self::CONF_API_VERSION;
        }                

        return static::$configurationErrors;
    }

    /**
     * Get list of the server requirements errors
     *
     * @return array
     */
    public function getRequirementsErrors()
    {
        if (is_array(static::$requirementsErrors)) {
            return static::$requirementsErrors;
        }

        static::$requirementsErrors = array();

        if (!function_exists('curl_init')) {
            static::$requirementsErrors[] = self::REQ_CURL;
        }

        if (
            !function_exists('openssl_pkey_get_public') || !function_exists('openssl_public_encrypt')
            || !function_exists('openssl_get_privatekey') || !function_exists('openssl_private_decrypt')
            || !function_exists('openssl_free_key')
        ) {
            static::$requirementsErrors[] = self::REQ_OPENSSL;
        }

        if (!class_exists('DOMDocument')) {
            static::$requirementsErrors[] = self::REQ_DOM;
        }

        return static::$requirementsErrors;
    }

    /**
     * Check - module requirements is passed or not
     *
     * @return bool 
     */
    public function checkRequirements()
    {
        $errors = $this->getRequirementsErrors();

        return empty($errors);
    }

    /**
     * Check if at least one of the payment configurations can be used for saving cards
     *
     * @return bool
     */
    public function isCanSaveCards()
    {
        if (!is_null(static::$canSaveCardsFlag)) {
            return static::$canSaveCardsFlag;
        }

        static::$canSaveCardsFlag = false;

        $list = Mage::getModel('xpaymentsconnector/paymentconfiguration')->getCollection();

        if (!empty($list)) {

            foreach ($list as $paymentConf) {
                if ('Y' == $paymentConf->getData('can_save_cards')) {
                    static::$canSaveCardsFlag = true;
                    break;
                }
            }
        }

        return static::$canSaveCardsFlag;
    }

    /**
     * Get payment configuration ID by slot index of the XPC payment method
     *
     * @param int $xpcSlot Slot index of the XPC payment method
     *
     * @return int
     */
    public function getConfidByXpcSlot($xpcSlot)
    {
        $xpcSlot = $this->checkXpcSlot($xpcSlot);

        return Mage::helper('settings_xpc')->getPaymentConfig('confid', $xpcSlot);
    }

    /**
     * Get slot index of the XPC payment method by payment configuration ID
     *
     * @param int $confid Payment configuration ID
     *
     * @return int
     */
    public function getXpcSlotByConfid($confid)
    {
        $found = false;

        for ($xpcSlot = 1; $xpcSlot <= self::MAX_SLOTS; $xpcSlot++) {
            if (Mage::helper('settings_xpc')->getPaymentConfig('confid', $xpcSlot) == $confid) {
                $found = true;
                break;
            }
        }

        if (!$found) {
            throw new Exception('Payment configuration is not configured. ID: ' . $confid);
        }

        return $xpcSlot;
    }

    /**
     * Get redirect form URL
     *
     * @return string
     */
    public function getPaymentUrl()
    {
        $url = $this->getXpcConfig('xpay_url');
        $url = rtrim($url, '/') . '/payment.php';

        return $url;
    }

    /**
     * Get admin URL
     *
     * @return string
     */
    public function getAdminUrl()
    {
        $url = $this->getXpcConfig('xpay_url');
        $url = rtrim($url, '/') . '/admin.php';

        return $url;
    }

    /**
     * Get admin URL
     *
     * @return string
     */
    public function getApiUrl()
    {
        $url = $this->getXpcConfig('xpay_url');
        $url = rtrim($url, '/') . '/api.php';

        return $url;
    }

    /**
     * List of the allowed JS events' origins
     *
     * @return array
     */
    public function getAllowedOrigins()
    {
        $xpHost = parse_url($this->getXpcConfig('xpay_url'), PHP_URL_HOST);

        // Use both HTTP and HTTPS protocols
        $result = array(
            'https://' . $xpHost,
            'https://' . parse_url(Mage::getStoreConfig('web/secure/base_url'), PHP_URL_HOST),
            'http://' . $xpHost,
            'http://' . parse_url(Mage::getStoreConfig('web/unsecure/base_url'), PHP_URL_HOST),
        );
   
        return array_unique($result);
    }

    /**
     * Validate slot index of the XPC payment method 
     *
     * @param int  $xpcSlot Slot index of the XPC payment method
     * @param bool $throwException Throw exception or not
     *
     * @throws Exception
     *  
     * @return int
     */
    public function checkXpcSlot($xpcSlot, $throwException = true)
    {
        if (
            !is_numeric($xpcSlot)
            || $xpcSlot < 1
            || $xpcSlot > self::MAX_SLOTS
        ) {

            $xpcSlot = var_export($xpcSlot, true);

            $this->writeLog('Access to the invalid X-Payments method. Slot: ' . $xpcSlot, '', true);

            if ($throwException) {
                throw new Exception('Access to the invalid X-Payments method. Slot: ' . $xpcSlot);
            } else {
                $xpcSlot = 0;
            }
        }

        return $xpcSlot;
    }

    /**
     * Check if zero auth method is configured
     *
     * @return bool
     */
    public function isZeroAuthMethodConfigured()
    {
        $result = false;

        if ($this->getXpcConfig('xpay_zero_auth_confid')) {

            try {
                
                $result = (bool)$this->getZeroAuthMethod();

            } catch (Exception $e) {

                // Do nothing
            }
        }

        return $result;
    }

    /**
     * Check if zero auth method is disaled
     *
     * @return bool
     */
    public function isZeroAuthMethodDisabled()
    {
        return self::ZERO_AUTH_DISABLED == $this->getXpcConfig('xpay_zero_auth_confid');
    }

    /**
     * Get payment method model for zero auth
     *
     * @return Cdev_XPaymentsConnector_Model_Payment_Cc
     */
    public function getZeroAuthMethod()
    {
        if (is_null(static::$zeroAuthMethod)) {

            $confid = $this->getXpcConfig('xpay_zero_auth_confid');

            $found = false; 

            for ($xpcSlot = 1; $xpcSlot <= self::MAX_SLOTS; $xpcSlot++) {
                if (Mage::helper('settings_xpc')->getPaymentConfig('confid', $xpcSlot) == $confid) {
                    $found = true;
                    break;
                }
            }

            if (!$found) {
                throw new Exception('Card setup payment method is not configured');
            }

            static::$zeroAuthMethod = Mage::getModel('xpaymentsconnector/payment_cc' . $xpcSlot);
        }

        return static::$zeroAuthMethod;
    }

    /**
     * Check if the payment method code is X-Payments' one
     *
     * @param $code Payment method code to check
     * @param $includeSavedCards Include seved cards method or not
     *
     * @return bool
     */
    public function isXpcMethod($code, $includeSavedCards = true)
    {
        return 0 === strpos($code, 'xpayments')
            || (
                $includeSavedCards 
                && 'savedcards' == $code
            );
    }

    /**
     * Check if the payment method code is X-Payments' saved cards one
     *
     * @param $code Payment method code to check
     *
     * @return bool
     */
    public function isSavedCardsMethod($code)
    {
        return 'savedcards' == $code;
    }

    /**
     * Get config option value for specific payment method
     *
     * @param string $name    Option name
     * @param int    $xpcSlot Slot index of the XPC payment method
     *
     * @return string (or whatever is there)
     */ 
    public function getPaymentConfig($name, $xpcSlot)
    {
        $xpcSlot = $this->checkXpcSlot($xpcSlot);

        $path = self::XPATH_PAYMENT . $xpcSlot . '/' . $name;

        return Mage::getStoreConfig($path);
    }

    /**
     * Set config option value for specific payment method
     *
     * @param string $name    Option name
     * @param string $value   Option value
     * @param int    $xpcSlot Slot index of the XPC payment method
     *
     * @return void
     */
    public function setPaymentConfig($name, $value, $xpcSlot)
    {
        $id = $this->checkXpcSlot($xpcSlot);

        $path = self::XPATH_PAYMENT . $xpcSlot . '/' . $name;

        $config = new Mage_Core_Model_Config();
        $config->saveConfig($path, $value);
    }

    /**
     * Get config option value for saved cards payment method 
     *
     * @param string $name Option name
     *
     * @return string (or whatever is there)
     */
    public function getSavedCardsConfig($name)
    {
        $path = self::XPATH_SAVED_CARDS . $name;

        return Mage::getStoreConfig($path);
    }

    /**
     * Set config option value for saved cards payment method 
     *
     * @param string $name Option name
     * @param string $value Option value
     *
     * @return void
     */
    public function setSavedCardsConfig($name, $value)
    {
        $path = self::XPATH_SAVED_CARDS . $name;

        $config = new Mage_Core_Model_Config();
        $config->saveConfig($path, $value);
    }

    /**
     * Decode bundle. It's base64 encoded
     * serialized or JSON-encoded array
     *
     * @param string $bundle If not passed use value from the config
     *
     * @return array
     */
    public function decodeBundle($bundle = null)
    {
        if (is_null($bundle)) {
            $bundle = $this->getXpcConfig('xpay_conf_bundle');
        }

        $decoded = base64_decode($bundle, true);

        if (false !== $decoded) {

            if (preg_match(self::BUNDLE_REGEXP, $decoded)) {
                // Try old-fashioned serialized bundle
                $result = @unserialize($decoded);
            } else {
                // Try modern JSON bundle
                $result = json_decode($decoded, true);
            }
        }

        if (empty($result) || !is_array($result)) {
            $result = array();
        }

        return $result;
    }

    /**
     * Get config option value common for all payment methods
     *
     * @param string $name Option name
     *
     * @return string (or whatever is there)
     */
    public function getXpcConfig($name)
    {
        $path = self::XPATH_XPC . $name;

        if (array_key_exists($name, $this->bundleMap)) {
            $bundle = $this->decodeBundle();
            $value = $bundle[$this->bundleMap[$name]];
        } else {
            $value = Mage::getStoreConfig($path);
        }

        return $value;
    }

    /**
     * Set config option value common for all payment methods
     *
     * @param string $name Option name
     * @param string $value Option value
     * @param bool   $reinit Is it necessary to reload config
     *
     * @return void
     */
    public function setXpcConfig($name, $value, $reinit = false)
    {
        $path = self::XPATH_XPC . $name;

        $config = new Mage_Core_Model_Config();
        $config->saveConfig($path, $value);

        if ($reinit) {

            // This cleans up config cache. But looks like not realy necessary now.
            // Mage::app()->getCacheInstance()->cleanType('config');

            // This reloads config. This is realy necessary.
            Mage::app()->getConfig()->reinit();
        }
    }

    /**
     * Process and save bundle in the config
     *
     * @param $bundle Base64 encoded, serialized bundle
     *
     * @return void
     */
    public function setXpcBundle($bundle)
    {
        $decoded = $this->decodeBundle($bundle);

        if (!empty($decoded['server_ip'])) {
            $this->setXpcConfig('xpay_allowed_ip_addresses', $decoded['server_ip']);
        }

        $this->setXpcConfig('xpay_conf_bundle', $bundle, true);
    }
}
