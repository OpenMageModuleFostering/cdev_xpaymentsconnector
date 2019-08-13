<?php
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
 * @author     Valerii Demidov
 * @category   Cdev
 * @package    Cdev_XPaymentsConnector
 * @copyright  (c) Qualiteam Software Ltd. <info@qtmsoft.com>. All rights reserved.
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */


/**
 * Processor
 * 
 * @package Cdev_XPaymentsConnector
 * @see     ____class_see____
 * @since   1.0.0
 */

    function xpc_curl_headers_collector()
    {
        static $headers = '';
        $args = func_get_args();
        if (count($args) == 1) {
            $return = '';
            if ($args[0] == true) {
                $return = $headers;
            }
            $headers = '';
            return $return;
        }

        if (trim($args[1]) != '') {
            $headers .= $args[1];
        }

        return strlen($args[1]);

    }


class Cdev_XPaymentsConnector_Model_Payment_Cc extends Mage_Payment_Model_Method_Abstract
    implements  Mage_Payment_Model_Recurring_Profile_MethodInterface
{
    // Configuration settings
    const XPATH_CART_ID          = 'xpaymentsconnector/settings/xpay_cart_id';
    const XPATH_URL              = 'xpaymentsconnector/settings/xpay_url';
    const XPATH_PUBLIC_KEY       = 'xpaymentsconnector/settings/xpay_public_key';
    const XPATH_PRIVATE_KEY      = 'xpaymentsconnector/settings/xpay_private_key';
    const XPATH_PRIVATE_KEY_PASS = 'xpaymentsconnector/settings/xpay_private_key_pass';
    const XPATH_IP_ADDRESSES     = 'xpaymentsconnector/settings/xpay_allowed_ip_addresses';
    const XPATH_CURRENCY         = 'xpaymentsconnector/settings/xpay_currency';
    const XPATH_CONF_BUNDLE      = 'xpaymentsconnector/settings/xpay_conf_bundle';

    // Error codes
    const REQ_CURL    = 1;
    const REQ_OPENSSL = 2;
    const REQ_DOM     = 4;

    // COnfiguration errors codes
    const CONF_CART_ID          = 1;
    const CONF_URL              = 2;
    const CONF_PUBLIC_KEY       = 4;
    const CONF_PRIVATE_KEY      = 8;
    const CONF_PRIVATE_KEY_PASS = 16;

    // Salt block length
    const SALT_LENGTH = 32;

    // Salt generator start character code
    const SALT_BEGIN = 33;

    // Salt generator end character code
    const SALT_END = 255;

    // Encryption check length
    const CHUNK_LENGTH = 128;

    // Root-level tag for all XML messages
    const TAG_ROOT = 'data';

    // Value of the "type" attribute for list items in XML
    const TYPE_CELL = 'cell';

    // Payment statuses
    const NEW_STATUS      = 1;
    const AUTH_STATUS     = 2;
    const DECLINED_STATUS = 3;
    const CHARGED_STATUS  = 4;

    // Payment actions
    const NEW_ACTION         = 1;
    const AUTH_ACTION        = 2;
    const CHARGED_ACTION     = 3;
    const DECLINED_ACTION    = 4;
    const REFUND_ACTION      = 5;
    const PART_REFUND_ACTION = 6;

    // Transaction types
    const TRAN_TYPE_AUTH          = 'auth';
    const TRAN_TYPE_CAPTURE       = 'capture';
    const TRAN_TYPE_CAPTURE_PART  = 'capturePart';
    const TRAN_TYPE_CAPTURE_MULTI = 'captureMulti';
    const TRAN_TYPE_VOID          = 'void';
    const TRAN_TYPE_VOID_PART     = 'voidPart';
    const TRAN_TYPE_VOID_MULTI    = 'voidMulti';
    const TRAN_TYPE_REFUND        = 'refund';
    const TRAN_TYPE_PART_REFUND   = 'refundPart';
    const TRAN_TYPE_REFUND_MULTI  = 'refundMulti';
    const TRAN_TYPE_GET_INFO      = 'getInfo';
    const TRAN_TYPE_ACCEPT        = 'accept';
    const TRAN_TYPE_DECLINE       = 'decline';
    const XP_API                  = '1.2';
    const XP_API_NEW              = '1.2'; // change to 1.3 in a feature

    /**
    * unique internal payment method identifier
    *
    * @var string [a-z0-9_]
    **/
    protected $_code = 'xpayments';


    protected $_isGateway               = false;
    protected $_canUseForMultishipping  = false;
    protected $_canUseInternal          = false;

    protected $_paymentMethod           = 'cc';
    protected $_defaultLocale           = 'en';

    protected $_canUseCheckout          = true;

    protected $_canRefund               = true;
    protected $_canRefundInvoicePartial = true;

    protected $_canCapturePartial       = true;
    protected $_canCapture              = true;

    /**
     * Payment method info block
     *
     * @var    string
     * @access protected
     * @see    ____var_see____
     * @since  1.0.0
     */
    protected $_infoBlockType = 'xpaymentsconnector/info_cc';

    /**
     * Payment method form block
     *
     * @var    string
     * @access protected
     * @see    ____var_see____
     * @since  1.0.1
     */
    protected $_formBlockType = 'xpaymentsconnector/form_cc';

    /**
     * Order (cache)
     *
     * @var    Mage_Sales_Model_Order
     * @access protected
     * @see    ____var_see____
     * @since  1.0.0
     */
    protected $_order = null;

    /**
     * Get order
     *
     * @return Mage_Sales_Model_Order
     * @access public
     * @see    ____func_see____
     * @since  1.0.0
     */
    public function getOrder()
    {
        if (!$this->_order) {
            $this->_order = $this->getInfoInstance()->getOrder();
        }

        return $this->_order;
    }

    /**
     * Get redirect URL for order placing procedure
     *
     * @return string
     * @access public
     * @see    ____func_see____
     * @since  1.0.0
     */
    public function getOrderPlaceRedirectUrl() {

        $useIframe = Mage::getStoreConfig('payment/xpayments/use_iframe');
        if(!$useIframe){
            return Mage::getUrl('xpaymentsconnector/processing/redirect', array('_secure' => true));
        }else{
            return false;
        }

    }


    /**
     * Get payment method type
     *
     * @return string
     * @access public
     * @see    ____func_see____
     * @since  1.0.0
     */
    public function getPaymentMethodType()
    {
        return $this->_paymentMethod;
    }

    /**
     * Check - can method Authorize transaction or not
     *
     * @return boolean
     * @access public
     * @see    ____func_see____
     * @since  1.0.0
     */
    public function canAuthorize()
    {
        return (bool)$this->getPaymentConfiguration()->getData('is_auth');
    }

    /**
     * Check - can method Capture transaction or not
     *
     * @return boolean
     * @access public
     * @see    ____func_see____
     * @since  1.0.0
     */
    public function canCapture()
    {
        $order = $this->getOrder();
        list($status, $response) = $this->requestPaymentInfo($order->getData("xpc_txnid"));
        if($status){
            if($response["status"] == self::CHARGED_STATUS){
                return false;
            }
            elseif($response["status"] == self::AUTH_ACTION){
                return true;
            }
        }else{
            return false;
        }
    }

    /**
     * Check - can method Refund transaction or not
     *
     * @return boolean
     * @access public
     * @see    ____func_see____
     * @since  1.0.0
     */
    public function canRefund()
    {
        return (bool)$this->getPaymentConfiguration()->getData('is_refund');
    }

    /**
     * Check - can method Partial refund transaction or not
     *
     * @return boolean
     * @access public
     * @see    ____func_see____
     * @since  1.0.0
     */
    public function canRefundPartialPerInvoice()
    {
        return (bool)$this->getPaymentConfiguration()->getData('is_part_refund');
    }

    /**
     * Check - can method Void transaction or not
     *
     * @param Varien_Object $payment Payment
     *
     * @return boolean
     * @access public
     * @see    ____func_see____
     * @since  1.0.0
     */
    public function canVoid(Varien_Object $payment)
    {
        return (bool)$this->getPaymentConfiguration()->getData('is_void');
    }

    /**
     * Get redirect form URL
     *
     * @return string
     * @access public
     * @see    ____func_see____
     * @since  1.0.0
     */
    public function getUrl()
    {
        return preg_replace('/\/+$/Ss', '', $this->getConfig('xpay_url'))
            . '/payment.php';
    }

    /**
     * Get redirect form fields list
     *
     * @return array
     * @access public
     * @see    ____func_see____
     * @since  1.0.0
     */
    public function getFormFields()
    {
        return array(
            'target' => 'main',
            'action' => 'start',
            'token'  => Mage::getSingleton('checkout/session')->getData('xpayments_token'),
        );
    }

    /**
     * Get module configuration setting value
     *
     * @param string $name Configuration setting name
     *
     * @return mixed
     * @access public
     * @see    ____func_see____
     * @since  1.0.0
     */
    public function getConfig($name)
    {
        static $required1 = array(
            'store_id',
            'url',
            'public_key',
            'private_key',
            'private_key_password',
        );
        $required1 = unserialize(base64_decode(Mage::getStoreConfig(self::XPATH_CONF_BUNDLE)));
        //print_r($required1);

        static $keys = array(
            // 'xpay_cart_id'              => $required1['store_id'],
            // 'xpay_url'                  => $required1['url'],
            //  'xpay_public_key'           => $required1['public_key'],
            //  'xpay_private_key'          => $required1['private_key'],
            //  'xpay_private_key_pass'     => $required1['private_key_password'],
            'xpay_allowed_ip_addresses' => self::XPATH_IP_ADDRESSES,
            'xpay_currency' => self::XPATH_CURRENCY,
            'xpay_conf_bundle' => self::XPATH_CONF_BUNDLE,

        );
        switch ($name) {
            case 'xpay_cart_id' :
                return $required1['store_id'];
            case 'xpay_url' :
                return $required1['url'];
            case 'xpay_public_key' :
                return $required1['public_key'];
            case 'xpay_private_key' :
                return $required1['private_key'];
            case 'xpay_private_key_pass' :
                return $required1['private_key_password'];
        }
        return isset($keys[$name]) ? Mage::getStoreConfig($keys[$name]) : null;


    }

    /**
     * Check - module is configured
     *
     * @return boolean
     * @access public
     * @see    ____func_see____
     * @since  1.0.0
     */
    public function isConfigured()
    {
        return 0 === $this->getConfigurationErrors();
    }

    /**
     * Get configuration errors code
     *
     * @return integer
     * @access public
     * @see    ____func_see____
     * @since  1.0.0
     */
    public function getConfigurationErrors()
    {
        $result = 0;

        // Check shopping cart id
        if (
            !$this->getConfig('xpay_cart_id')
            || !preg_match('/^[\da-f]{32}$/Ss', $this->getConfig('xpay_cart_id'))
        ) {
            $result = $result | self::CONF_CART_ID;
        }

        // Check URL
        if (!$this->getConfig('xpay_url')) {
            $result = $result | self::CONF_URL;
        }

        $parsed_url = @parse_url($this->getConfig('xpay_url'));

        if (!$parsed_url || !isset($parsed_url['scheme']) || $parsed_url['scheme'] != 'https') {
            $result = $result | self::CONF_URL;
        }

        // Check public key
        if (!$this->getConfig('xpay_public_key')) {
            $result = $result | self::CONF_PUBLIC_KEY;
        }

        // Check private key
        if (!$this->getConfig('xpay_private_key')) {
            $result = $result | self::CONF_PRIVATE_KEY;
        }

        // Check private key password
        if (!$this->getConfig('xpay_private_key_pass')) {
            $result = $result | self::CONF_PRIVATE_KEY_PASS;
        }

        return $result;
    }

    /**
     * Check - module requirements is passed or not
     *
     * @return boolean
     * @access public
     * @see    ____func_see____
     * @since  1.0.0
     */
    public function checkRequirements()
    {
        $code = 0;

        if (!function_exists('curl_init')) {
            $code = $code | self::REQ_CURL;
        }

        if (
            !function_exists('openssl_pkey_get_public') || !function_exists('openssl_public_encrypt')
            || !function_exists('openssl_get_privatekey') || !function_exists('openssl_private_decrypt')
            || !function_exists('openssl_free_key')
        ) {
            $code = $code | self::REQ_OPENSSL;
        }

        if (!class_exists('DOMDocument')) {
            $code = $code | self::REQ_DOM;
        }

        return $code;
    }

    /**
     * Send Handshake request
     *
     * @param Mage_Sales_Model_Order $order Order
     *
     * @return string Payment token
     * @access public
     * @see    ____func_see____
     * @since  1.0.0
     */
    public function sendHandshakeRequest(Mage_Sales_Model_Order $order)
    {
        $refId = $order->getIncrementId();

        // Prepare cart
        $cart = $this->prepareCart($order, $refId);

        // Data to send to X-Payments
        $data = array(
            'confId'      => intval($this->getPaymentConfiguration()->getData('confid')),
            'refId'       => $refId,
            'cart'        => $cart,
            'returnUrl'   => Mage::getUrl('xpaymentsconnector/processing/return', array('order_id' => $refId,'_secure' => true)),
            'callbackUrl' => Mage::getUrl('xpaymentsconnector/processing/callback', array('order_id' => $refId,'_secure' => true)),
            'saveCard'    => 'Y',
            'api_version' => self::XP_API_NEW
        );

        list($status, $response) = $this->request('payment', 'init', $data);

        if ($status && (!isset($response['token']) || !is_string($response['token']))) {

            Mage::log(serialize($response), null, Cdev_XPaymentsConnector_Helper_Data::XPAYMENTS_LOG_FILE,true);

            $this->getAPIError('Transaction token can not be found or has wrong type');
            $status = false;
        }

        if ($status) {
            Mage::getSingleton('checkout/session')->setData('xpayments_token', $response['token']);
        }

        return $status;
    }

    /**
     * Send Payment info request
     *
     * @param string  $txn_id  X-Payments transaction id
     * @param boolean $refresh Refresh data flag
     * @param boolean $withAdditionalInf info class
     *
     * @return array (Operation status & response array)
     * @access public
     * @see    ____func_see____
     * @since  1.0.0
     */
    public function requestPaymentInfo($txn_id, $refresh = false,$withAdditionalInfo = false)
    {

        if($withAdditionalInfo){
            $data = array(
                'txnId'   => $txn_id,
            );
            $infoClass = "get_additional_info";
        }else{
            $data = array(
                'txnId'   => $txn_id,
                'refresh' => $refresh ? 1 : 0
            );
            $infoClass = "get_info";
        }

        list($status, $response) = $this->request('payment', $infoClass, $data);
        $checkResponse = (!$withAdditionalInfo)?$response:$response["payment"];

        if ($status) {
            if (!is_array($response) || !isset($checkResponse['status'])) {
                $this->getAPIError('GetInfo request. Server response has not status');
                $status = false;

            } elseif (!isset($checkResponse['message'])) {
                $this->getAPIError('GetInfo request. Server response has not message');
                $status = false;

            } elseif (!isset($checkResponse['transactionInProgress'])) {
                $this->getAPIError('GetInfo request. Server response has not transaction progress status');
                $status = false;

            } elseif (!isset($checkResponse['isFraudStatus'])) {
                $this->getAPIError('GetInfo request. Server response has not fraud filter status');
                $status = false;

            } elseif (!isset($checkResponse['currency']) || strlen($checkResponse['currency']) != 3) {
                $this->getAPIError('GetInfo request. Server response has not currency code or currency code has wrong format');
                $status = false;

            } elseif (!isset($checkResponse['amount'])) {
                $this->getAPIError('GetInfo request. Server response has not payment amount');
                $status = false;

            } elseif (!isset($checkResponse['capturedAmount'])) {
                $this->getAPIError('GetInfo request. Server response has not captured amount');
                $status = false;

            } elseif (!isset($checkResponse['capturedAmountAvail'])) {
                $this->getAPIError('GetInfo request. Server response has not available for capturing amount');
                $status = false;

            } elseif (!isset($checkResponse['refundedAmount'])) {
                $this->getAPIError('GetInfo request. Server response has not refunded amount');
                $status = false;

            } elseif (!isset($checkResponse['refundedAmountAvail'])) {
                $this->getAPIError('GetInfo request. Server response has not available for refunding amount');
                $status = false;

            } elseif (!isset($checkResponse['voidedAmount'])) {
                $this->getAPIError('GetInfo request. Server response has not voided amount');
                $status = false;

            } elseif (!isset($checkResponse['voidedAmountAvail'])) {
                $this->getAPIError('GetInfo request. Server response has not available for cancelling amount');
                $status = false;

            }
        }

        return array($status, $response);
    }

    /**
     * Send test request
     *
     * @return boolean
     * @access public
     * @see    ____func_see____
     * @since  1.0.0
     */
    public function sendTestRequest()
    {
        srand();

        $hash_code = strval(rand(0, 1000000));

        // Make test request
        list($status, $response) = $this->request(
            'connect',
            'test',
            array('testCode' => $hash_code)
        );

        // Compare MD5 hashes
        if ($status && md5($hash_code) !== $response['hashCode']) {
            $this->getAPIError('Test connection data is not valid');
            $status = false;
        }

        return $status;
    }

    /**
     * Send Get payment configurations request
     *
     * @return array Payment configurations list
     * @access public
     * @see    ____func_see____
     * @since  1.0.0
     */
    public function requestPaymentMethods()
    {
        $result = array();

        // Call the "api.php?target=payment_confs&action=get" URL
        list($status, $response) = $this->request(
            'payment_confs',
            'get',
            array()
        );

        // Check status
        if ($status && (!isset($response['payment_module']) || !is_array($response['payment_module']))) {
            $status = false;
        }

        return $status ? $response['payment_module'] : false;
    }

    /**
     * Request
     *
     * @param string $target Request target
     * @param string $action Request action
     * @param array  $data   Data
     *
     * @return array (Operation status & response array)
     * @access protected
     * @see    ____func_see____
     * @since  1.0.0
     */
    protected function request($target, $action, array $data = array())
    {

        // Check requirements
        if (!$this->isConfigured()) {
            return $this->getAPIError('Module is not configured');
        }

        if ($this->checkRequirements() != 0) {
            return $this->getAPIError('Check module requirements is failed');
        }

        $data['target'] = $target;
        $data['action'] = $action;
        if(!isset($data['api_version'])){
            $data['api_version'] = self::XP_API;
        }


        // Convert array to XML
        $xml = $this->convertHash2XML($data);

        if (!$xml) {
            return $this->getAPIError('Data is not valid');
        }

        // Encrypt
        $xml = $this->encrypt($xml);
        if (!$xml) {
            return $this->getAPIError('Data is not encrypted');
        }

        // HTTPS request
        $post = array(
            'cart_id' => $this->getConfig('xpay_cart_id'),
            'request' => $xml
        );

        $https = new Varien_Http_Client(
            $this->getConfig('xpay_url') . '/api.php',
            array(
                'timeout' => 15,
            )
        );


        /*update*/
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $this->getConfig('xpay_url') . '/api.php');
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15000);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_HEADERFUNCTION, 'xpc_curl_headers_collector');
        curl_setopt($ch, CURLOPT_SSLVERSION, 3);

        $body = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);

        // Check raw data
        if (substr($body, 0, 3) !== 'API') {

            if (substr(strstr($body, 'API'), 0, 3) !== 'API') {

                return $this->getAPIError(
                    'Response is not valid.' . "\n"
                    //. 'Response headers: ' . var_export($headers, true) . "\n"
                    . 'Response: ' . $body . "\n"
                );

            } else {

                $body = strstr($body, 'API');

            }

        }

        // Decrypt
        list($responseStatus, $response) = $this->decrypt($body);

        if (!$responseStatus) {
            return $this->getAPIError('Response is not decrypted (Error: ' . $response . ')');
        }

        // Convert XML to array
        $response = $this->convertXML2Hash($response);

        if (!is_array($response)) {
            return $this->getAPIError('Unable to convert response into XML');
        }

        // The 'Data' tag must be set in response
        if (!isset($response[self::TAG_ROOT])) {
            return $this->getAPIError('Response does not contain any data');
        }

        // Process errors
        if ($this->processAPIError($response)) {
            return array(false, 'X-Payments internal error');
        }

        return array(true, $response[self::TAG_ROOT]);
    }

    /**
     * Decrypt separate XML block
     *
     * @param string $body Encrypted XML data
     *
     * @return array
     * @access public
     * @see    ____func_see____
     * @since  1.0.0
     */
    public function decryptXML($body)
    {
        // Check raw data
        if (substr($body, 0, 3) !== 'API') {
            return $this->getAPIError(
                'Encrypted XML data is not valid.' . "\n"
            );
        }

        // Decrypt
        list($responseStatus, $response) = $this->decrypt($body);

        if (!$responseStatus) {
            return $this->getAPIError('Encrypted XML data is not decrypted (Error: ' . $response . ')');
        }

        // Convert XML to array
        $response = $this->convertXML2Hash($response);

        if (!is_array($response)) {
            return $this->getAPIError('Unable to convert encrypted data into XML');
        }

        // The 'Data' tag must be set in response
        if (!isset($response[self::TAG_ROOT])) {
            return $this->getAPIError('Encrypted XML data does not contain any data');
        }

        return $response[self::TAG_ROOT];
    }

    /**
     * Get API error response and save error message into log
     *
     * @param string $msg Error message
     *
     * @return array
     * @access protected
     * @see    ____func_see____
     * @since  1.0.0
     */
    protected function getAPIError($msg)
    {
        Mage::log(
            sprintf('XPayments connector error: %s', $msg),
            Zend_Log::ERR
        );

        return array(false, $msg);
    }

    /**
     * Process API error
     *
     * @param array $response Prepared response
     *
     * @return boolean Has reponse any error(s) or not
     * @access protected
     * @see    ____func_see____
     * @since  1.0.0
     */
    protected function processAPIError(array $response)
    {
        $error = false;

        if (isset($response['error']) && $response['error']) {
            $this->getAPIError(
                'X-Payments error (code: ' . $response['error'] . '): '
                . (isset($response['error_message']) ? $response['error_message'] : 'Unknown')
            );
            $error = true;
        }

        return $error;
    }

    /**
     * Check - force use authorization request or not
     *
     * @return boolean
     * @access protected
     * @see    ____func_see____
     * @since  1.0.0
     */
    protected function isForceAuth()
    {

        $isRecurrnigProduct = Mage::helper("xpaymentsconnector")->checkIsRecurringOrder();
        if ($isRecurrnigProduct) {
            $useStartDateParam = Mage::helper("xpaymentsconnector")->checkStartDateData();
            if ($useStartDateParam["success"]) {
                $useInitialFeeAuthorize = Mage::getStoreConfig('payment/xpayments/use_initialfee_authorize');
                return (bool)$useInitialFeeAuthorize;
            }
        }

        $useAuthorize = Mage::getStoreConfig('payment/xpayments/use_authorize');
        return (bool)$useAuthorize;
    }

    /**
     * Prepare cart for Handshake request
     *
     * @param Mage_Sales_Model_Order $order Order
     * @param mixed                  $refId Unique reference id
     *
     * @return array
     * @access protected
     * @see    ____func_see____
     * @since  1.0.0
     */
    protected function prepareCart(Mage_Sales_Model_Order $order, $refId)
    {
        $memail = explode(',', Mage::getStoreConfig($order::XML_PATH_EMAIL_COPY_TO, $this->getStoreId()));
        $memail = array_shift($memail);

        if (!$memail) {
            $users = Mage::getModel('admin/user')->getCollection();
            foreach ($users as $user) {
                $memail = $user->getData('email');
                break;
            }
            unset($users, $user);
        }

        $forceTransactionType = ($this->isForceAuth()) ? 'A' : 'S';

        $result = array(
            'billingAddress'  => array(
                'email' => $order->getCustomerEmail(),
            ),
            'shippingAddress' => array(
                'email' => $order->getCustomerEmail(),
            ),
            'items'           => array(),
            'currency'        => $this->getCurrency($refId),
            'shippingCost'    => 0.00,
            'taxCost'         => 0.00,
            'discount'        => 0.00,
            'totalCost'       => 0.00,
            'description'     => 'Order #' . $refId,
            'merchantEmail'   => $memail,
            'forceTransactionType' => $forceTransactionType,
            'login'           => $order->getCustomerEmail(),
        );

        $namePrefixes  = array(
            'billing'  => 'getBillingAddress',
            'shipping' => 'getShippingAddress',
        );

        $addressFields = array(
            'firstname' => 'firstname',
            'lastname'  => 'lastname',
            'address'   => 'street',
            'city'      => 'city',
            'state'     => 'region_id',
            'country'   => 'country_id',
            'zipcode'   => 'postcode',
            'phone'     => 'telephone',
            'fax'       => 'telephone',
        );

        // Prepare shipping and billing address
        foreach ($namePrefixes as $prefix => $mn) {

            $addressIndex = $prefix . 'Address';
            $getter = $order->$mn();

            if (!$getter && $prefix == 'shipping') {

                // If shipping address is forbidden
                $getter = $order->getBillingAddress();
            }

            foreach ($addressFields as $field => $fn) {
                $result[$addressIndex][$field] = $getter->getData($fn);

				if ('region_id' == $fn) {
					$result[$addressIndex][$field] = Mage::getModel('directory/region')->load($result[$addressIndex][$field])->getCode();
				}

                if (
                    !$result[$addressIndex][$field]
                    && in_array($field, array('state', 'address', 'city','zipcode'))
                ) {
                    $result[$addressIndex][$field] = 'n/a';
                }
            }

            $result[$addressIndex]['name'] = trim(
                $getter->getData('firstname') . ' ' . $getter->getData('lastname')
            );
        }

        // Set products
        foreach ($order->getItemsCollection() as $product) {
            $result['items'][] = array(
                'sku'      => $product->getData('sku'),
                'name'     => $product->getData('name'),
                'price'    => number_format($product->getPrice(), 2, '.',0),
                'quantity' => intval($product->getData('qty_ordered')),
            );
        }

        // Set costs
        $result['taxCost'] = number_format($order->getData('tax_amount'), 2, '.',0);
        $result['discount'] = number_format(abs($order->getDiscountAmount()), 2, '.',0);
        $result['shippingCost'] = number_format($order->getData('shipping_amount'), 2, '.',0);
        $result['totalCost'] = number_format($order->getGrandTotal(), 2, '.',0);

        return $result;
    }

    /**
     * Get currency code
     *
     * @param mixed $refId Order increment id
     *
     * @return string
     * @access public
     * @see    ____func_see____
     * @since  1.0.0
     */
    public function getCurrency($refId = null)
    {
        return strtoupper($this->getConfig('xpay_currency'));
    }

    /**
     * Convert hash to XML
     *
     * @param array   $data  Hash
     * @param integer $level Parentness level
     *
     * @return string
     * @access protected
     * @see    ____func_see____
     * @since  1.0.0
     */
    protected function convertHash2XML(array $data, $level = 0)
    {
        $xml = '';

        foreach ($data as $name => $value) {

            if ($this->isAnonymousArray($value)) {
                foreach ($value as $item) {
                    $xml .= $this->writeXMLTag($item, $name, $level, self::TYPE_CELL);
                }
            } else {
                $xml .= $this->writeXMLTag($value, $name, $level);
            }

        }

        return $xml;
    }

    /**
     * Check - argument is plain array or not
     *
     * @param array $data Array
     *
     * @return boolean
     * @access protected
     * @see    ____func_see____
     * @since  1.0.0
     */
    protected function isAnonymousArray($data)
    {
        return is_array($data)
            && 1 > count(preg_grep('/^\d+$/', array_keys($data), PREG_GREP_INVERT));
    }

    /**
     * Write XML tag
     *
     * @param mixed   $data  Data
     * @param string  $name  Tag name
     * @param integer $level Parentness level
     * @param string  $type  Tag type
     *
     * @return string
     * @access protected
     * @see    ____func_see____
     * @since  1.0.0
     */
    protected function writeXMLTag($data, $name, $level = 0, $type = '')
    {
        $xml    = '';
        $indent = str_repeat('  ', $level);

        // Open tag
        $xml .= $indent . '<' . $name . (empty($type) ? '' : ' type="' . $type . '"') . '>';

        // Sublevel tags or tag value
        if (is_array($data)) {
            $xml .= "\n" . $this->convertHash2XML($data, $level + 1) . $indent;

        } elseif (function_exists('iconv')) {
            $trn = iconv('UTF-8', 'UTF-8//IGNORE', $data);
            $data = false === $trn ? $data : $trn;
            $data = str_replace(
                array("\n", "\t", "\r", "\f",),
                array(' ', ' ', '', '',),
                $data
            );
            $xml .= $data;

        } else {
            $data = str_replace(
                array("\n", "\t", "\r", "\f",),
                array(' ', ' ', '', '',),
                $data
            );
            $xml .= $data;
        }

        // Close tag
        $xml .= '</' . $name . '>' . "\n";

        return $xml;
    }

    /**
     * Convert XML-to-hash
     *
     * @param string $xml XML string
     *
     * @return array or string
     * @access protected
     * @see    ____func_see____
     * @since  1.0.0
     */
    protected function convertXML2Hash($xml)
    {
        $data = array();

        while (
            !empty($xml)
            && preg_match('/<([\w\d]+)(?:\s*type=["\'](\w+)["\']\s*)?' . '>(.*)<\/\1>/Us', $xml, $matches)
        ) {

            // Sublevel tags or tag value
            if (self::TYPE_CELL === $matches[2]) {
                $data[$matches[1]][] = $this->convertXML2Hash($matches[3]);

            } else {
                $data[$matches[1]] = $this->convertXML2Hash($matches[3]);
            }

            // Exclude parsed part from XML
            $xml = str_replace($matches[0], '', $xml);

        }

        return empty($data) ? $xml : $data;
    }

    /**
     * Encrypt data
     *
     * @param string $data Data
     *
     * @return string
     * @access protected
     * @see    ____func_see____
     * @since  1.0.0
     */
    protected function encrypt($data)
    {
        // Preprocess
        srand(time());
        $salt = '';
        for ($i = 0; $i < self::SALT_LENGTH; $i++) {
            $salt .= chr(rand(self::SALT_BEGIN, self::SALT_END));
        }

        $lenSalt = strlen($salt);

        $crcType = 'MD5';
        $crc = md5($data, true);

        $crc = str_repeat(' ', 8 - strlen($crcType)) . $crcType . $crc;
        $lenCRC = strlen($crc);

        $lenData = strlen($data);

        $data = str_repeat('0', 12 - strlen((string)$lenSalt)) . $lenSalt . $salt
            . str_repeat('0', 12 - strlen((string)$lenCRC)) . $lenCRC . $crc
            . str_repeat('0', 12 - strlen((string)$lenData)) . $lenData . $data;

        // Encrypt
        $key = openssl_pkey_get_public($this->getConfig('xpay_public_key'));
        if (!$key) {
            return false;
        }

        $data = str_split($data, self::CHUNK_LENGTH);
        $crypttext = null;
        foreach ($data as $k => $chunk) {
            if (!openssl_public_encrypt($chunk, $crypttext, $key)) {
                return false;
            }

            $data[$k] = $crypttext;
        }

        // Postprocess
        $data = array_map('base64_encode', $data);

        return 'API' . implode("\n", $data);
    }

    /**
     * Decrypt
     *
     * @param string $data Encrypted data
     *
     * @return string
     * @access protected
     * @see    ____func_see____
     * @since  1.0.0
     */
    protected function decrypt($data)
    {

        // Decrypt
        $res = openssl_get_privatekey(
            $this->getConfig('xpay_private_key'),
            $this->getConfig('xpay_private_key_pass')
        );
        if (!$res) {
            return array(false, 'Private key is not initialized');
        }

        $data = substr($data, 3);

        $data = explode("\n", $data);
        $data = array_map('base64_decode', $data);
        foreach ($data as $k => $s) {
            if (!openssl_private_decrypt($s, $newsource, $res)) {
                return array(false, 'Can not decrypt chunk');
            }

            $data[$k] = $newsource;
        }

        openssl_free_key($res);

        $data = implode('', $data);

        // Postprocess
        $lenSalt = substr($data, 0, 12);
        if (!preg_match('/^\d+$/Ss', $lenSalt)) {
            return array(false, 'Salt length prefix has wrong format');
        }

        $lenSalt = intval($lenSalt);
        $data = substr($data, 12 + intval($lenSalt));

        $lenCRC = substr($data, 0, 12);
        if (!preg_match('/^\d+$/Ss', $lenCRC) || $lenCRC < 9) {
            return array(false, 'CRC length prefix has wrong format');
        }

        $lenCRC = intval($lenCRC);
        $crcType = trim(substr($data, 12, 8));
        if ($crcType !== 'MD5') {
            return array(false, 'CRC hash is not MD5');
        }
        $crc = substr($data, 20, $lenCRC - 8);

        $data = substr($data, 12 + $lenCRC);

        $lenData = substr($data, 0, 12);
        if (!preg_match('/^\d+$/Ss', $lenData)) {
            return array(false, 'Data block length prefix has wrong format');
        }

        $data = substr($data, 12, intval($lenData));

        $currentCRC = md5($data, true);
        if ($currentCRC !== $crc) {
            return array(false, 'Original CRC and calculated CRC is not equal');
        }

        return array(true, $data);
    }

    /**
     * Get payment configuration model
     *
     * @return Cdev_XPaymentsConnector_Model_Paymentconfiguration
     * @access protected
     * @see    ____func_see____
     * @since  1.0.0
     */
    protected function getPaymentConfiguration()
    {
        return Mage::getModel('xpaymentsconnector/paymentconfiguration')
			->load($this->getConfigData('confid'));
    }

    /**
     * Check method availability
     *
     * @param Mage_Sales_Model_Quote $quote Quote
     *
     * @return boolean
     * @access public
     * @see    ____func_see____
     * @since  1.0.0
     */
    public function isAvailable($quote = null)
    {
        return $this->isConfigured()
        && 0 === $this->checkRequirements()
        && '1' != Mage::getStoreConfig('advanced/modules_disable_output/Cdev_XPaymentsConnector')
        && parent::isAvailable($quote);
    }

    /**
     * Send Handshake request
     *
     * @param bool $useDefaultTemplate
     * @param bool $isCardAuthorizePayment
     * @param array $updateSendData
     *
     * @param Mage_Sales_Model_Order $order Order
     *
     * @return string Payment token
     * @access public
     * @see    ____func_see____
     * @since  1.0.0
     *
     * @return array
     */
    public function sendIframeHandshakeRequest($updateSendData = array(),$isCardAuthorizePayment = false)
    {
        $xPaymentDataResponse = array();
        $checkoutData = Mage::getSingleton('checkout/session');
        $refId = Mage::helper("xpaymentsconnector")->getOrderKey();

        $xPaymentDataResponse["order_refid"] = $refId;
        $result = array();
        $cart = array();

        if(Mage::getSingleton('customer/session')->isLoggedIn()){
            $customer =  Mage::getSingleton('customer/session')->getCustomer();
            $cart = $this->iframePrepareCart($checkoutData, $customer,$isCardAuthorizePayment);
        }else {
            $cart = $this->iframePrepareCart($checkoutData, false, $isCardAuthorizePayment);
        }

        // Data to send to X-Payments
        $sendData = array(
            'confId'      => intval($this->getPaymentConfiguration()->getData('confid')),
            'refId'       => $refId,
            'cart'        => $cart,
            'returnUrl'   => Mage::getUrl('xpaymentsconnector/processing/iframereturn', array('order_refid' => $refId,'_secure' => true)),
            'callbackUrl' => Mage::getUrl('xpaymentsconnector/processing/callback', array('order_refid' => $refId,'_secure' => true)),
        );


        foreach($updateSendData as $sendDataKey => $sendDataValue){
            $sendData[$sendDataKey] = $updateSendData[$sendDataKey];
        }

        list($status, $response) = $this->request('payment', 'init', $sendData);


        $xPaymentDataResponse["status"] = $status;
        $xPaymentDataResponse["response"] = $response;


        //****************stop*****************//


        if (!$status || (!isset($response['token']) || !is_string($response['token']))) {
            $result['success'] = false;
            $result['error_message'] = Mage::helper('core')->__("Transaction token can not be found or has wrong type");
            return $result;
        }


        $checkoutData->setData("xpayment",$xPaymentDataResponse);

        if ($status) {
            Mage::getSingleton('checkout/session')->setData('xpayments_token', $response['token']);
        }

        return $xPaymentDataResponse;
    }

    public function sendAgainTransactionRequest($order_id = NULL,$paymentCardNumber = NULL,$grandTotal = NULL, $cardData = NULL){


        $quote = Mage::getSingleton('checkout/session')->getQuote();
        if(is_null($paymentCardNumber)){
            $paymentCardNumber = $quote->getPayment()->getData("xp_payment_card");
        }
        if(is_null($grandTotal)){
            $grandTotal = $quote->getGrandTotal();
        }

        if(is_null($cardData)){
            $cardData = Mage::getModel("xpaymentsconnector/usercards")->load($paymentCardNumber);
            if($cardData){
                $cardData = $cardData->getData();
            }
        }

        $data = array(
            'txnId'       => $cardData["txnId"],
            'amount'      => number_format($grandTotal, 2, '.','')
        );
        $order = NULL;
        if(!is_null($order_id)){
            $order = Mage::getModel('sales/order')->load($order_id);
            $orderIcrementId = $order->getIncrementId();
            $data['description'] = 'Order #' . $orderIcrementId;
        }else{
            $xpOrderKey = Mage::helper("xpaymentsconnector")->getOrderKey();;
            $data['description'] = 'Order(i-frame) #' . $xpOrderKey;
        }


        list($status, $response) = $this->request('payment', 'recharge', $data);

        $xPaymentDataResponse["status"] = $status;
        $xPaymentDataResponse["response"] = $response;

        if($order){
            $order->setData("xp_card_data",serialize($cardData));
            $order->save();

            if ($status && (!isset($response['transaction_id']) || !is_string($response['transaction_id']))) {
                Mage::helper("xpaymentsconnector")->unsetXpaymentPrepareOrder();
                $order->cancel();
                $errorMessage = Mage::helper('xpaymentsconnector')->__("Transaction token can not found or has wrong type. The order has been canceled.");
                $order->addStatusToHistory(
                    $order::STATE_CANCELED,
                    $errorMessage
                );

                $order->save();
                $result["success"] = false;
                $result["error_message"] = $errorMessage;

                return $result;
            }

        }


        $xPaymentDataResponse["success"] = true;

        return $xPaymentDataResponse;

    }

    /**
     * Prepare cart for Handshake request
     *
     * @param Mage_Sales_Model_Order $order Order
     * @param mixed                  $refId Unique reference id
     *
     * @return array
     * @access protected
     * @see    ____func_see____
     * @since  1.0.0
     */
    protected function iframePrepareCart($checkoutData,$customer,$isCardAuthorizePayment = false)
    {
        $refId = Mage::helper("xpaymentsconnector")->getOrderKey();
        $customerEmail = "";

        $billingAddress = $checkoutData->getQuote()->getBillingAddress();
        $shippingAddress = $checkoutData->getQuote()->getShippingAddress();
        $quote = $checkoutData->getQuote();
        $result = array();
        /*initial fee check*/
        $quoteItem = current($checkoutData->getQuote()->getAllItems());
        $forceTransactionType = ($this->isForceAuth()) ? 'A' : 'S';
        $minimalPayment = false;
        $cardAuthorizeAmount = 0;

        $totals = $quote->getTotals(); //Total object

        $totalCost = 0;
        $discount = 0;
        $tax = 0;
        $shipping = 0;
        $description = 'Order(i-frame) #' . $refId;

        if ($quoteItem && $quoteItem->getProduct()->getIsRecurring()) {
            $useStartDateParam = Mage::helper("xpaymentsconnector")->checkStartDateData();
            if ($useStartDateParam["success"]) {
                $minimalPayment = $useStartDateParam["minimal_payment_amount"];

                $totalCost = $minimalPayment;
                $description = "Recurring profile subscription.";

            } else {
                $tax = $quoteItem->getData("tax_amount");
                $shipping = $quoteItem->getData("shipping_amount");
                $totalCost = $quoteItem->getData("nominal_row_total");
                $discount = abs($quoteItem->getData("discount_amount"));
            }

        } elseif ($isCardAuthorizePayment) {

            $totalCost = floatval(Mage::getStoreConfig("xpaymentsconnector/settings/xpay_minimum_payment_recurring_amount"));

        } else {

            if (isset($totals['discount']) && $totals['discount']->getValue()) {
                $discount = abs($totals['discount']->getValue());
            }
            if(isset($totals['tax']) && $totals['tax']->getValue()) {
                $tax = $totals['tax']->getValue();
            }
            if(isset($totals['shipping']) && $totals['shipping']->getValue()) {
                $shipping = $totals['shipping']->getValue();
            }

            // Set costs
            $totalCost = $quote->getGrandTotal();
        }

        if(!$customer){
            $customerEmail = $billingAddress->getEmail();
        } else{
            $customerEmail = $customer->getEmail();
        }

        $result['billingAddress']  = array(
            'email' => $customerEmail,
        );
        $result['shippingAddress'] = array(
            'email' => $customerEmail,
        );
        $result['items']   = array();
        $result['currency'] = $this->getCurrency();
        $result['description'] = $description;

        $result['totalCost'] = number_format($totalCost, 2, '.', 0);
        $result['shippingCost'] = number_format($shipping, 2, '.', 0);
        $result['taxCost'] = number_format($tax, 2, '.', 0);
        $result['discount'] = number_format($discount, 2, '.', 0);

        $result['merchantEmail'] = $customerEmail;
        $result['forceTransactionType'] = $forceTransactionType;
        $result['login'] = $customerEmail;



        $namePrefixes  = array(
            'billing'  => $billingAddress,
            'shipping' => $shippingAddress,
        );

        $addressFields = array(
            'firstname' => 'firstname',
            'lastname'  => 'lastname',
            'address'   => 'street',
            'city'      => 'city',
            'state'     => 'region_id',
            'country'   => 'country_id',
            'zipcode'   => 'postcode',
            'phone'     => 'telephone',
            'fax'       => 'telephone',
        );


        // Prepare shipping and billing address
        $shippingIsEmpty = true;
        foreach ($namePrefixes as $prefix => $data) {

            $addressIndex = $prefix . 'Address';
            foreach ($addressFields as $field => $fn) {
                $result[$addressIndex][$field] = $data->getData($fn);
                if(($field == "firstname" || $field == "lastname") && $isCardAuthorizePayment){
                    $result[$addressIndex][$field] = $customer->getData($field);
                }
                if(($prefix == "shipping") && $result[$addressIndex][$field]){
                    $shippingIsEmpty = false;
                }

                if ('region_id' == $fn) {
                    $result[$addressIndex][$field] = Mage::getModel('directory/region')->load($result[$addressIndex][$field])->getCode();
                }

                if (!$result[$addressIndex][$field] /*&& in_array($field, array('state', 'address', 'city','zipcode'))*/)
                 {
                    $result[$addressIndex][$field] = 'n/a';
                }
            }

            $result[$addressIndex]['name'] = trim(
                $data->getData('firstname') . ' ' . $data->getData('lastname')
            );
        }

        if($shippingIsEmpty){
            $result["shippingAddress"] = $result["billingAddress"];
        }

        $cartItems = $quote->getAllVisibleItems();

        // Set products
        foreach ($cartItems as $item) {
            $productId = $item->getProductId();
            $product = Mage::getModel('catalog/product')->load($productId);
            $price = ($minimalPayment) ? $minimalPayment : $product->getPrice();
            $quantity = ($minimalPayment) ? 1 :$item->getQty();
            $result['items'][] = array(
                'sku'      => $product->getData('sku'),
                'name'     => $product->getData('name'),
                'price'    => number_format($price, 2, '.',0),
                'quantity' => intval($quantity),
            );
        }

        if($isCardAuthorizePayment){
            $result['items'][] = array(
                'sku'      => "000000",
                'name'     => 'Card Authorize',
                'price'    => number_format($totalCost, 2, '.',0),
                'quantity' => intval(1),
            );
        }

        return $result;
    }


    public function updateOrderByXpaymentResponse($orderId,$txnid){
        $result = array();
        $order = Mage::getModel('sales/order')->load($orderId);
        $order->setData('xpc_txnid', $txnid);
        /* update order by xpyament response state */
        list($status, $response) = $this->requestPaymentInfo($txnid);
        if (
            $status
            && in_array($response['status'], array(self::AUTH_STATUS, self::CHARGED_STATUS))
        ) {
            // TODO - save message - $response['message']

            // TODO - process faud status
            /*
            if ($response['isFraudStatus']) {
            }
            */
            /**/
            if ($response['amount'] != number_format($order->getGrandTotal(), 2, '.','')) {

                // Total wrong
                Mage::log(
                    'Order total amount doesn\'t match: Order total = ' . number_format($order->getGrandTotal(), 2, '.','')
                    . ', X-Payments amount = ' . $response['amount'],
                    Zend_Log::ERR
                );
                Mage::throwException('Transaction amount doesn\'t match.');

            } elseif ($response['currency'] != $this->getCurrency()) {

                // Currency wrong
                Mage::log(
                    'Order currency doesn\'t match: Order currency = ' . $this->getCurrency()
                    . ', X-Payments currency = ' . $response['currency'],
                    Zend_Log::ERR
                );
                Mage::throwException('Transaction currency doesn\'t match.');

            } else {

                $order->getPayment()->setTransactionId($txnid);
                $order->getPayment()->setLastTransId($txnid);
                if (isset($response['advinfo']) && isset($response['advinfo']['AVS'])) {
                    $order->getPayment()->setCcAvsStatus($response['advinfo']['AVS']);
                }

                if ($response['status'] == self::AUTH_ACTION) {
                    $order->setState(
                        Mage_Sales_Model_Order::STATE_PROCESSING,
                        (bool)$order->getPayment()->getMethodInstance()->getConfigData('order_status'),
                        Mage::helper('xpaymentsconnector')->__('preauthorize: Customer returned successfully')
                    );
                    $order->setStatus(Cdev_XPaymentsConnector_Helper_Data::STATE_XPAYMENT_PENDING_PAYMENT);
                }else{
                    $order->setState(
                        Mage_Sales_Model_Order::STATE_PROCESSING,
                        (bool)$order->getPayment()->getMethodInstance()->getConfigData('order_status'),
                        Mage::helper('xpaymentsconnector')->__('preauthorize: Customer returned successfully')
                    );
                    $order->setStatus(Mage_Sales_Model_Order::STATE_PROCESSING);
                }
                $order->save();
                $order->sendNewOrderEmail();
            }
            $result["success"] = true;
            return $result;
        }else{
            Mage::helper("xpaymentsconnector")->unsetXpaymentPrepareOrder();
            $order->cancel();
            $order->addStatusToHistory(
                $order::STATE_CANCELED,
                Mage::helper('xpaymentsconnector')->__('charge: Callback request')
            );
            $order->save();

            $result["success"] = false;
            if(!empty($response["error_message"])){
                $result["error_message"] = Mage::helper('xpaymentsconnector')->__('%s. The order has been canceled.',$response["error_message"]);
            }else{
                $transactionStatusLabel =  $this->getTransactionStatusLabels();
                $result["error_message"] = Mage::helper('xpaymentsconnector')->__('Transaction status is "%s". The order has been canceled.',$transactionStatusLabel[$response['status']]);
            }

            return $result;
        }
        /*end ( update order) */

    }

    /**
     * @param array $cardData
     * @param int $usageType
     * @return Mage_Core_Model_Abstract
     */
    public function saveUserCard($cardData,$usageType = Cdev_XPaymentsConnector_Model_Usercards::SIMPLE_CARD){

        if(Mage::getSingleton('customer/session')->isLoggedIn()){
            $customer =  Mage::getSingleton('customer/session')->getCustomer();
            try
            {
                if (Mage::helper("xpaymentsconnector")->isNeedToSaveUserCard()) {
                    $usercards = Mage::getModel("xpaymentsconnector/usercards");
                    $usercards->setData(array(
                            "user_id" => $customer->getId(),
                            "txnId" => $cardData['txnId'],
                            "last_4_cc_num" => $cardData['last_4_cc_num'],
                            "card_type" => $cardData['card_type'],
                            "usage_type" => $usageType)
                    );
                   return $usercards->save();
                }
            }
            catch(Exception $e)
            {
                echo $e->getMessage;exit;
            }
        }

    }

    public function authorizedTransactionRequest($action,$data){
        $data["target"] = "payment";
        list($status, $response) = $this->request('payment', $action, $data);

        //****************stop*****************//
        if ( $status && (!empty($response['error_message']))) {
            Mage::throwException(Mage::helper('sales')->__($response['error_message']));
        }

    }


    /**
     * Capture payment abstract method
     *
     * @param Varien_Object $payment
     * @param float $amount
     *
     * @return Mage_Payment_Model_Abstract
     */
    public function capture(Varien_Object $payment, $amount)
    {
        if (!$this->canCapture()) {
            Mage::throwException(Mage::helper('payment')->__('Capture action is not available.'));
        }

        $order = $this->getOrder();
        $data = array(
            'txnId' => $order->getData("xpc_txnid"),
            'amount' => number_format($amount, 2, '.', ''),
        );

        $this->authorizedTransactionRequest('capture', $data);


        return $this;
    }

    /**
     * Refund specified amount for payment
     *
     * @param Varien_Object $payment
     * @param float $amount
     *
     * @return Mage_Payment_Model_Abstract
     */
    public function refund(Varien_Object $payment, $amount)
    {

        if (!$this->canRefund()) {
            Mage::throwException(Mage::helper('payment')->__('Refund action is not available.'));
        }

        /*processing during create invoice*/
        $order = $this->getOrder();
        /*processing during capture invoice*/
        $data = array(
            'txnId' => $order->getData("xpc_txnid"),
            'amount' => number_format($amount, 2, '.', ''),
        );

        $this->authorizedTransactionRequest('refund', $data);


        return $this;
    }

    /**
     * Validate data
     *
     * @param Mage_Payment_Model_Recurring_Profile $profile
     * @throws Mage_Core_Exception
     */
    public function validateRecurringProfile(Mage_Payment_Model_Recurring_Profile $profile){

    }

    /**
     * Submit to the gateway
     *
     * @param Mage_Payment_Model_Recurring_Profile $profile
     * @param Mage_Payment_Model_Info $paymentInfo
     */
    public function submitRecurringProfile(Mage_Payment_Model_Recurring_Profile $profile, Mage_Payment_Model_Info $paymentInfo)
    {
        $useIframe = Mage::getStoreConfig('payment/xpayments/use_iframe');
        if($useIframe){
            $payDeferredSubscription = Mage::helper("xpaymentsconnector")->payDeferredSubscription($profile);
            if(!$payDeferredSubscription){
                $this->createFirstRecurringOrder($profile);
            }

        }else{
            $xpaymentResponse = $this->sendIframeHandshakeRequest();
            if(isset($xpaymentResponse["success"]) && !$xpaymentResponse["success"]){
                $profile->setState(Mage_Sales_Model_Recurring_Profile::STATE_CANCELED);
            }
        }

    }

    /**
     * Fetch details
     *
     * @param string $referenceId
     * @param Varien_Object $result
     */
    public function getRecurringProfileDetails($referenceId, Varien_Object $result){
        // TODO
    }

    /**
     * Check whether can get recurring profile details
     *
     * @return bool
     */
    public function canGetRecurringProfileDetails(){
        return false;
    }

    /**
     * Update data
     *
     * @param Mage_Payment_Model_Recurring_Profile $profile
     */
    public function updateRecurringProfile(Mage_Payment_Model_Recurring_Profile $profile){
        // TODO
    }

    /**
     * Manage status
     *
     * @param Mage_Payment_Model_Recurring_Profile $profile
     */
    public function updateRecurringProfileStatus(Mage_Payment_Model_Recurring_Profile $profile){
        return false;

    }

    /**
     * @return array
     */
    public function getTransactionStatusLabels(){
        $statuses = array(
            self::NEW_STATUS => "New",
            self::AUTH_STATUS => "Authorized",
            self::DECLINED_STATUS => "Declined",
            self::CHARGED_STATUS => "Charged",
        );
        return $statuses;
    }

    /**
     *
     * @param Mage_Payment_Model_Recurring_Profile $profile
     */
    public function createFirstRecurringOrder(Mage_Payment_Model_Recurring_Profile $profile){
        $cardData = Mage::helper("xpaymentsconnector")->getXpaymentResponse();
        $orderId = Mage::helper("xpaymentsconnector")->createOrder($profile,$isFirstRecurringOrder = true);

        /*update order by card data*/
        $order =  Mage::getModel('sales/order')->load($orderId);
        $order->setData("xp_card_data", serialize($cardData));
        $order->save();

        $profile->setReferenceId($cardData["txnId"]);
        $result = $this->updateOrderByXpaymentResponse($orderId, $cardData["txnId"]);

        if (!$result["success"]) {
            Mage::getSingleton("checkout/session")->addError($result["error_message"]);
            $profile->setState(Mage_Sales_Model_Recurring_Profile::STATE_CANCELED);
        } else {
            $profile->setState(Mage_Sales_Model_Recurring_Profile::STATE_ACTIVE);
            // additional subscription profile setting for success transaction
            $newTransactionDate = new Zend_Date(time());
            $profile->setXpSuccessTransactionDate($newTransactionDate->toString(Varien_Date::DATETIME_INTERNAL_FORMAT));
            $profile->setXpCountSuccessTransaction(1);
            //save user card
            Mage::getSingleton("checkout/session")->setData("user_card_save",true);
            $this->saveUserCard($cardData,$usageType = Cdev_XPaymentsConnector_Model_Usercards::RECURRING_CARD);

        }
        return $profile;
    }

}

