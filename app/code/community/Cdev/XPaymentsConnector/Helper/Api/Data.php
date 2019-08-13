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
 * Helper for API
 */

class Cdev_XPaymentsConnector_Helper_Api_Data extends Cdev_XPaymentsConnector_Helper_Abstract 
{
    // Payment statuses
    const NEW_STATUS           = 1;
    const AUTH_STATUS          = 2;
    const DECLINED_STATUS      = 3;
    const CHARGED_STATUS       = 4;
    const REFUNDED_STATUS      = 5;
    const PART_REFUNDED_STATUS = 6;

    // Payment actions
    const NEW_ACTION         = 1;
    const AUTH_ACTION        = 2;
    const CHARGED_ACTION     = 3;
    const DECLINED_ACTION    = 4;
    const REFUND_ACTION      = 5;
    const PART_REFUND_ACTION = 6;

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

    // Value of the 'type' attribute for list items in XML
    const TYPE_CELL = 'cell';

    /**
     * CURL headers collector callback
     *
     * @return mixed
     */
    protected function getCurlHeadersCollector()
    {
        static $headers = '';

        $args = func_get_args();

        if (count($args) == 1) {

            $return = '';

            if ($args[0] == true) {
                $return = $headers;
            }

            $headers = '';

        } else {

            if (trim($args[1]) != '') {
                $headers .= $args[1];
            }
            $return = strlen($args[1]);
        }

        return $return;
    }

    /**
     * Request
     *
     * @param string $target Request target
     * @param string $action Request action
     * @param array  $data   Data
     *
     * @return array (Operation status & response array)
     */
    private function request($target, $action, array $data = array())
    {
        $settings = Mage::helper('settings_xpc');

        $apiResponse = new Cdev_XPaymentsConnector_Transport_ApiResponse;

        $data['target'] = $target;
        $data['action'] = $action;

        if (!isset($data['api_version'])) {
            $data['api_version'] = $settings->getXpcConfig('xpay_api_version');
        }

        $log = 'URL: ' . $settings->getApiUrl() . PHP_EOL
            . 'Data: ' . var_export($data, true) . PHP_EOL;
        
        $this->writeLog('Request to X-Payments', $log);

        try {

            // Check configuration 
            if (!$settings->isConfigured()) {
                throw new Exception('X-Payments Connector is not configured');
            }

            // Check requirements
            if (!$settings->checkRequirements()) {
                throw new Exception('Check module requirements is failed');
            }

            // Convert array to XML
            $xml = $this->convertHash2XML($data);

            if (!$xml) {
                throw new Exception('Data is not valid');
            }

            // Encrypt
            $xml = $this->encrypt($xml);
            if (!$xml) {
                throw new Exception('Data is not encrypted');
            }

            // HTTPS request
            $post = array(
                'cart_id' => $settings->getXpcConfig('xpay_cart_id'),
                'request' => $xml
            );

            $this->getCurlHeadersCollector(false);

            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL, $settings->getApiUrl());
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15000);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
            curl_setopt($ch, CURLOPT_HEADERFUNCTION, array($this, 'getCurlHeadersCollector'));

            $body = curl_exec($ch);
            $errno = curl_errno($ch);
            $error = curl_error($ch);
            $headers = $this->getCurlHeadersCollector(true);

            curl_close($ch);

            // Check curl error
            if (!empty($error) || 0 != $errno) {
                throw new Exception('Communication error. Curl error #' . $errno . ': ' . $error);
            }

            $response = $this->decryptXml($body, $headers);

            $apiResponse->setStatus(true);
            $apiResponse->setResponse($response);

            if (!empty($response['error'])) {
                $apiResponse->setErrorCode($response['error']);
            }

            if (!empty($response['error_message'])) {
                $apiResponse->setErrorMessage($response['error_message']);
            }

        } catch (Exception $e) {

            $apiResponse->setStatus(false);
            $apiResponse->setErrorMessage($e->getMessage());
        }

        $this->writeLog('Response from X-Payments', $apiResponse->getData());

        return $apiResponse;
    }

    /**
     * Decrypt separate XML block
     *
     * @param string $body Encrypted XML data
     *
     * @return array
     */
    public function decryptXml($body, $headers = null)
    {
        // Check raw data
        if (substr($body, 0, 3) !== 'API') {

            $error = array(
                'Response is not valid:',
                $body,
            );
            
            if ($headers) {
                $error += array(
                    'Headers:',
                    $headers,
                );
            }

            $error = implode(PHP_EOL, $error);

            throw new Exception($error);
        }

        // Decrypt
        list($responseStatus, $response) = $this->decrypt($body);

        if (!$responseStatus) {
            throw new Exception('Response is not decrypted (Error: ' . $response . ')');
        }

        // Convert XML to array
        $response = $this->convertXML2Hash($response);

        if (!is_array($response)) {
            throw new Exception('Unable to convert response into XML');
        }

        // The 'Data' tag must be set in response
        if (!isset($response[self::TAG_ROOT])) {
            throw new Exception('Response does not contain any data');
        }
        return $response[self::TAG_ROOT];
    }

    /**
     * Convert hash to XML
     *
     * @param array   $data  Hash
     * @param integer $level Parentness level
     *
     * @return string
     */
    public function convertHash2Xml(array $data, $level = 0)
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
     */
    private function isAnonymousArray($data)
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
     */
    private function convertXml2Hash($xml)
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
     */
    public function encrypt($data)
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
        $key = openssl_pkey_get_public(Mage::helper('settings_xpc')->getXpcConfig('xpay_public_key'));
        if (!$key) {
            throw new Exception('Cannot initialize public key');
        }

        $data = str_split($data, self::CHUNK_LENGTH);
        $crypttext = null;
        foreach ($data as $k => $chunk) {
            if (!openssl_public_encrypt($chunk, $crypttext, $key)) {
                throw new Exception('Cannot enctypt chunk');
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
     */
    private function decrypt($data)
    {
        // Decrypt
        $res = openssl_get_privatekey(
            Mage::helper('settings_xpc')->getXpcConfig('xpay_private_key'),
            Mage::helper('settings_xpc')->getXpcConfig('xpay_private_key_pass')
        );
        if (!$res) {
            throw new Exception('Private key is not initialized');
        }

        $data = substr($data, 3);

        $data = explode("\n", $data);
        $data = array_map('base64_decode', $data);
        foreach ($data as $k => $s) {
            if (!openssl_private_decrypt($s, $newsource, $res)) {
                throw new Exception('Can not decrypt chunk');
            }

            $data[$k] = $newsource;
        }

        openssl_free_key($res);

        $data = implode('', $data);

        // Postprocess
        $lenSalt = substr($data, 0, 12);
        if (!preg_match('/^\d+$/Ss', $lenSalt)) {
            throw new Exception('Salt length prefix has wrong format');
        }

        $lenSalt = intval($lenSalt);
        $data = substr($data, 12 + intval($lenSalt));

        $lenCRC = substr($data, 0, 12);
        if (!preg_match('/^\d+$/Ss', $lenCRC) || $lenCRC < 9) {
            throw new Exception('CRC length prefix has wrong format');
        }

        $lenCRC = intval($lenCRC);
        $crcType = trim(substr($data, 12, 8));
        if ($crcType !== 'MD5') {
            throw new Exception('CRC hash is not MD5');
        }
        $crc = substr($data, 20, $lenCRC - 8);

        $data = substr($data, 12 + $lenCRC);

        $lenData = substr($data, 0, 12);
        if (!preg_match('/^\d+$/Ss', $lenData)) {
            throw new Exception('Data block length prefix has wrong format');
        }

        $data = substr($data, 12, intval($lenData));

        $currentCRC = md5($data, true);
        if ($currentCRC !== $crc) {
            throw new Exception('Original CRC and calculated CRC is not equal');
        }

        return array(true, $data);
    }

    /**
     * Check if status is succesfull
     *
     * @param string $status Status
     *
     * @return bool
     */
    public function isSuccessStatus($status)
    {
        return self::AUTH_STATUS == $status
            || self::CHARGED_STATUS == $status;
    }

    /**
     * Process Connect Test request
     *
     * @param string $apiVersion API version
     * @param string $error Error message (if any)
     * @param string $code Error code (if any)
     *
     * @return Cdev_XPaymentsConnector_Transport_ApiResponse
     */
    public function testConnection($apiVersion)
    {
        srand();

        $hashCode = strval(rand(0, 1000000));

        $params = array(
            'testCode' => $hashCode,
        );

        $params['api_version'] = $apiVersion;

        // Send test request
        
        $response = $this->request(
            'connect',
            'test',
            $params
        );

        $result = $response->getStatus();

        if (
            $response->getStatus()
            && md5($hashCode) != $response->getField('hashCode')
        ) {
            $response->setStatus(false);
            $response->setErrorMessage('Connection with X-Payments is not verified');
        }

        return $response;
    }

    /**
     * Send Get payment configurations request
     *
     * @return array Payment configurations list
     */
    public function requestPaymentMethods()
    {
        $result = array();

        // Call the "api.php?target=payment_confs&action=get" URL
        $response = $this->request(
            'payment_confs',
            'get',
            array()
        );

        // Check status
        if (
            $response->getStatus() 
            && (is_array($response->getField('payment_module'))
        )) {
            $result = $response->getField('payment_module');
        }

        return $result;
    }

    /**
     * Initialize payment
     *
     * @param array $data Request data
     *
     * @return Cdev_XPaymentsConnector_Transport_ApiResponse
     */
    public function initPayment($data)
    {
        $response = $this->request('payment', 'init', $data);

        if (
            empty($response->getField('token'))
            || empty($response->getField('txnId'))
        ) {

            $response->setStatus(false);
        }

        return $response;
    }

    /**
     * Send Payment info request
     *
     * @param string  $txnId  X-Payments transaction id
     * @param boolean $refresh Refresh data flag
     * @param boolean $withAdditionalInfo Flag for additional info
     *
     * @return Cdev_XPaymentsConnector_Transport_ApiResponse
     */
    public function requestPaymentInfo($txnId, $refresh = false, $withAdditionalInfo = false)
    {
        $data = array(
            'txnId' => $txnId,
        );

        if ($withAdditionalInfo) {

            $action = 'get_additional_info';

        } else {

            $data['refresh'] = $refresh ? 1 : 0;

            $action = 'get_info';
        }

        return $this->request('payment', $action, $data);
    }

    /**
     * Send capture payment request
     *
     * @param string  $txnId  X-Payments transaction id
     * @param float   $amount Amount
     *
     * @return Cdev_XPaymentsConnector_Transport_ApiResponse
     */
    public function requestPaymentCapture($txnId, $amount = false)
    {
        $data = array(
            'txnId' => $txnId,
        );

        if ($amount) {
            $data['amount'] = $this->preparePrice($amount);
        } 

        return $this->request('payment', 'capture', $data);
    }

    /**
     * Send void payment request
     *
     * @param string  $txnId  X-Payments transaction id
     * @param float   $amount Amount
     *
     * @return Cdev_XPaymentsConnector_Transport_ApiResponse
     */
    public function requestPaymentVoid($txnId, $amount = false)
    {
        $data = array(
            'txnId' => $txnId,
        );

        if ($amount) {
            $data['amount'] = $this->preparePrice($amount);
        }

        return $this->request('payment', 'void', $data);
    }

    /**
     * Send refund payment request
     *
     * @param string  $txnId  X-Payments transaction id
     * @param float   $amount Amount
     *
     * @return Cdev_XPaymentsConnector_Transport_ApiResponse
     */
    public function requestPaymentRefund($txnId, $amount = false)
    {
        $data = array(
            'txnId' => $txnId,
        );

        if ($amount) {
            $data['amount'] = $this->preparePrice($amount);
        }

        return $this->request('payment', 'refund', $data);
    }

    /**
     * Send accept payment request
     *
     * @param string $txnId  X-Payments transaction id
     *
     * @return Cdev_XPaymentsConnector_Transport_ApiResponse
     */
    public function requestPaymentAccept($txnId)
    {
        $data = array(
            'txnId' => $txnId,
        );

        return $this->request('payment', 'accept', $data);
    }

    /**
     * Send decline payment request
     *
     * @param string $txnId  X-Payments transaction id
     *
     * @return Cdev_XPaymentsConnector_Transport_ApiResponse
     */
    public function requestPaymentDecline($txnId)
    {
        $data = array(
            'txnId' => $txnId,
        );

        return $this->request('payment', 'decline', $data);
    }

    /**
     * Send recharge payment request
     *
     * @param array $data Payment data
     *
     * @return Cdev_XPaymentsConnector_Transport_ApiResponse
     */
    public function requestPaymentRecharge($data)
    {
        return $this->request('payment', 'recharge', $data);
    }
}
