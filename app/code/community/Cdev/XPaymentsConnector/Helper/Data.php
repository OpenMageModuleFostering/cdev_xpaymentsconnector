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
 * Common helper
 *
 * @package Cdev_XPaymentsConnector
 * @see     ____class_see____
 * @since   1.0.0
 */

class Cdev_XPaymentsConnector_Helper_Data extends Cdev_XPaymentsConnector_Helper_Abstract 
{
    const DAY_TIME_STAMP = 86400;
    const WEEK_TIME_STAMP = 604800;
    const SEMI_MONTH_TIME_STAMP = 1209600;

    /**
     * Order statuses. Actual vaues left for the backwrds compatibility
     */
    const STATUS_AUTHORIZED = 'xp_pending_payment';
    const STATUS_CHARGED    = 'processing';
    const STATUS_FRAUD      = 'fraud';

    const RECURRING_ORDER_TYPE = 'recurring';
    const SIMPLE_ORDER_TYPE = 'simple';

    /**
     * Column name for the serialized CC data in order model 
     */
    const XPC_DATA = 'xp_card_data';

    /**
     * Checkout methods
     */
    const METHOD_LOGIN_IN = 'login_in';
    const METHOD_REGISTER = 'register';

    /**
     * save/update qty for createOrder function.
     * @var int
     */
    protected $itemsQty = null;
    public  $payDeferredProfileId = null;

    /**
     * Save masked CC details to the order model
     *
     * @param Mage_Sales_Model_Order $order 
     *
     * @return void
     */
    public function saveMaskedCardToOrder(Mage_Sales_Model_Order $order, $data)
    {
        $data = serialize($data);

        $order->setData(self::XPC_DATA, $data);
        
        $order->save();
    }

    /**
     * Get saved CC details from the order model
     *
     * @param Mage_Sales_Model_Order $order
     *
     * @return array 
     */
    public function getOrderXpcCardData(Mage_Sales_Model_Order $order)
    {
        return @unserialize($order->getData(self::XPC_DATA));
    }

    /**
     * Check if user is registered, or registers at checkout
     *
     * @return bool
     */
    public function isRegisteredUser()
    {
        $result = false;

        $checkoutMethod = Mage::getSingleton('checkout/type_onepage')->getCheckoutMethod();

        if (
            Mage_Checkout_Model_Type_Onepage::METHOD_REGISTER == $checkoutMethod
            || Mage_Checkout_Model_Type_Onepage::METHOD_CUSTOMER == $checkoutMethod
            || 'register' == Mage::getSingleton('checkout/session')->getData('xpc_checkout_method')
        ) {
            $result = true;
        }

        return $result;
    }

    /**
     * This function prepare order keys for recurring orders
     * @return int
     */
    public function prepareOrderKeyByRecurringProfile(Mage_Payment_Model_Recurring_Profile $recurringProfile)
    {
        $quote = $recurringProfile->getQuote();
        $this->itemsQty = $quote->getItemsCount();
        $orderItemInfo = $recurringProfile->getData('order_item_info');
        $quote->getItemById($orderItemInfo['item_id'])->isDeleted(true);
    }

    /**
     * @param Mage_Payment_Model_Recurring_Profile $recurringProfile
     * @param bool $isFirstRecurringOrder
     * @return int
     */
    public function createOrder(Mage_Payment_Model_Recurring_Profile $recurringProfile, $isFirstRecurringOrder = false,$orderAmountData = array())
    {
        $orderItemInfo = $recurringProfile->getData('order_item_info');

        if (!is_array($orderItemInfo)) {
            $orderItemInfo = unserialize($orderItemInfo);
        }
        $initialFeeAmount = $recurringProfile->getInitAmount();

        $productSubtotal = ($isFirstRecurringOrder) ? $orderItemInfo['row_total'] + $initialFeeAmount : $orderItemInfo['row_total'];
        $price = $productSubtotal / $orderItemInfo['qty'];
        if(isset($orderAmountData['product_subtotal']) && $initialFeeAmount > 0 ){
            $productSubtotal = $orderAmountData['product_subtotal'];
            $price = $orderAmountData['product_subtotal'] / $orderItemInfo['qty'];
        }

        //check is set related orders

        $useDiscount = ($recurringProfile->getXpCountSuccessTransaction() > 0) ? false : true;
        $discountAmount = ($useDiscount) ? $orderItemInfo['discount_amount'] : 0;
        if(isset($orderAmountData['discount_amount'])){
            $discountAmount = $orderAmountData['discount_amount'];
        }

        $taxAmount = $recurringProfile->getData('tax_amount');
        if(isset($orderAmountData['tax_amount'])){
            $taxAmount = $orderAmountData['tax_amount'];
        }

        if($isFirstRecurringOrder && !isset($orderAmountData['tax_amount'])){
            $taxAmount += $orderItemInfo['initialfee_tax_amount'];
        }

        $shippingAmount = $recurringProfile->getData('shipping_amount');
        if(isset($orderAmountData['shipping_amount'])){
            $shippingAmount = $orderAmountData['shipping_amount'];
        }

        $productItemInfo = new Varien_Object;
        $productItemInfo->setDiscountAmount($discountAmount);
        $productItemInfo->setPaymentType(Mage_Sales_Model_Recurring_Profile::PAYMENT_TYPE_REGULAR);
        $productItemInfo->setTaxAmount($taxAmount);
        $productItemInfo->setShippingAmount($shippingAmount);
        $productItemInfo->setPrice($price);
        $productItemInfo->setQty($orderItemInfo['qty']);
        $productItemInfo->setRowTotal($productSubtotal);
        $productItemInfo->setBaseRowTotal($productSubtotal);
        $productItemInfo->getOriginalPrice($price);

        if($initialFeeAmount > 0){
            if(isset($orderAmountData['grand_total'])){
                $productItemInfo->setRowTotalInclTax($orderAmountData['grand_total']);
                $productItemInfo->setBaseRowTotalInclTax($orderAmountData['grand_total']);
            }
        }

        Mage::dispatchEvent('before_recurring_profile_order_create', array('profile' => $recurringProfile, 'product_item_info' => $productItemInfo));
        $order = $recurringProfile->createOrder($productItemInfo);
        $order->setCustomerId($recurringProfile->getCustomerId());

        if ($isFirstRecurringOrder) {
            $orderKey = 0;
            if ($this->getPrepareRecurringMasKey($recurringProfile)) {
                $orderKey = $this->getPrepareRecurringMasKey($recurringProfile);
            } elseIf ($this->getOrderKey()) {
                $orderKey = $this->getOrderKey();
            }
            if ($orderKey) {
                $order->setIncrementId($orderKey);
            }
        }
        if(isset($orderAmountData['shipping_amount'])){
            $order->setShippingAmount($orderAmountData['shipping_amount']);
            $order->setBaseShippingAmount($orderAmountData['shipping_amount']);
        }


        Mage::dispatchEvent('before_recurring_profile_order_save', array('order' => $order));

        $order->save();
        $recurringProfile->addOrderRelation($order->getId());

        return $order->getId();
    }

    /**
     * @param Mage_Payment_Model_Recurring_Profile $recurringProfile
     * @return int
     */
    public function getCurrentBillingPeriodTimeStamp(Mage_Payment_Model_Recurring_Profile $recurringProfile)
    {
        $periodUnit = $recurringProfile->getData('period_unit'); //day
        $periodFrequency = $recurringProfile->getData('period_frequency');
        //$masPeriodUnits = $recurringProfile->getAllPeriodUnits();
        $billingTimeStamp = 0;
        switch ($periodUnit) {
            case Mage_Payment_Model_Recurring_Profile::PERIOD_UNIT_DAY:
                $billingTimeStamp = self::DAY_TIME_STAMP;
                break;
            case Mage_Payment_Model_Recurring_Profile::PERIOD_UNIT_WEEK;
                $billingTimeStamp = self::WEEK_TIME_STAMP;
                break;
            case Mage_Payment_Model_Recurring_Profile::PERIOD_UNIT_SEMI_MONTH;
                $billingTimeStamp = self::SEMI_MONTH_TIME_STAMP;
                break;
            case Mage_Payment_Model_Recurring_Profile::PERIOD_UNIT_MONTH;
                $startDateTime = strtotime($recurringProfile->getStartDatetime());
                $lastSuccessTransactionDate = strtotime($recurringProfile->getXpSuccessTransactionDate());
                $lastActionDate = ($startDateTime > $lastSuccessTransactionDate) ? $startDateTime : $lastSuccessTransactionDate;
                $nextMonth = mktime(date('H', $lastActionDate), date('i', $lastActionDate), date('s', $lastActionDate), date('m', $lastActionDate) + 1, date('d', $lastActionDate), date('Y', $lastActionDate));
                $billingTimeStamp = $nextMonth - $lastActionDate;

                break;
            case Mage_Payment_Model_Recurring_Profile::PERIOD_UNIT_YEAR;
                $startDateTime = strtotime($recurringProfile->getStartDatetime());
                $lastSuccessTransactionDate = strtotime($recurringProfile->getXpSuccessTransactionDate());
                $lastActionDate = ($startDateTime > $lastSuccessTransactionDate) ? $startDateTime : $lastSuccessTransactionDate;
                $nextYear = mktime(date('H', $lastActionDate), date('i', $lastActionDate), date('s', $lastActionDate), date('m', $lastActionDate), date('d', $lastActionDate), date('Y', $lastActionDate) + 1);
                $billingTimeStamp = $nextYear - $lastActionDate;

                break;
        }

        $billingPeriodTimeStamp = round($billingTimeStamp / $periodFrequency);

        return $billingPeriodTimeStamp;

    }

    /**
     * @param Mage_Payment_Model_Recurring_Profile $recurringProfile
     * @return array
     */
    public function getProfileOrderCardData(Mage_Payment_Model_Recurring_Profile $recurringProfile)
    {
        $txnId = $recurringProfile->getReferenceId();
        $cardData = Mage::getModel('xpaymentsconnector/usercards')->load($txnId, 'txnid');
        return $cardData;
    }

    /**
     * @param Mage_Payment_Model_Recurring_Profile $recurringProfile
     * @param bool $result
     * @param timestamp $newTransactionDate
     */
    public function updateCurrentBillingPeriodTimeStamp(Mage_Payment_Model_Recurring_Profile $recurringProfile, bool $result, $newTransactionDate = null)
    {
        if (!$result) {
            $this->updateProfileFailureCount($recurringProfile);
        } else {
            $recurringProfile->setXpCycleFailureCount(0);

            // update date of success transaction
            $newTransactionDate = new Zend_Date($newTransactionDate);
            $recurringProfile->setXpSuccessTransactionDate($newTransactionDate->toString(Varien_Date::DATETIME_INTERNAL_FORMAT));

            // update count of success transaction
            $currentSuccessCycles = $recurringProfile->getXpCountSuccessTransaction();
            $currentSuccessCycles++;
            $recurringProfile->setXpCountSuccessTransaction($currentSuccessCycles);
        }

        $recurringProfile->save();

    }

    /**
     * This function update 'failure count params' in recurring profile
     * @param Mage_Payment_Model_Recurring_Profile $recurringProfile
     */
    public function updateProfileFailureCount(Mage_Payment_Model_Recurring_Profile $recurringProfile){
        $currentCycleFailureCount = $recurringProfile->getXpCycleFailureCount();
        //add failed attempt
        $currentCycleFailureCount++;
        $recurringProfile->setXpCycleFailureCount($currentCycleFailureCount);
        $maxPaymentFailure = $recurringProfile->getSuspensionThreshold();
        if ($currentCycleFailureCount >= $maxPaymentFailure) {
            $recurringProfile->cancel();
        }
        $recurringProfile->save();
    }

    /**
     * update recurring profile for deferred pay.
     * @param Mage_Payment_Model_Recurring_Profile $recurringProfile
     * @return bool
     */
    public function  payDeferredSubscription(Mage_Payment_Model_Recurring_Profile $recurringProfile)
    {

        $orderItemInfo = $recurringProfile->getData('order_item_info');
        $infoBuyRequest = unserialize($orderItemInfo['info_buyRequest']);
        $startDateTime = isset($infoBuyRequest['recurring_profile_start_datetime'])
            ? $infoBuyRequest['recurring_profile_start_datetime']
            : false;
        $xpaymentCCModel = Mage::getModel('xpaymentsconnector/payment_cc');

        if (!empty($startDateTime)) {
            $dateTimeStamp = strtotime($startDateTime);
            $zendDate = new Zend_Date($dateTimeStamp);
            $currentZendDate = new Zend_Date(time());

            if ($zendDate->getTimestamp() > $currentZendDate->getTimestamp()) {
                //set start date time
                $recurringProfile->setStartDatetime($zendDate->toString(Varien_Date::DATETIME_INTERNAL_FORMAT));
                $orderAmountData = $this->preparePayDeferredOrderAmountData($recurringProfile);

                if (!isset($orderItemInfo['recurring_initial_fee'])) {
                    $orderAmountData['grand_total'] = floatval(Mage::getStoreConfig('xpaymentsconnector/settings/xpay_zero_auth_amount'));
                }


                $paymentMethodCode = $recurringProfile->getData('method_code');

                $cardData = NULL;
                switch ($paymentMethodCode) {
                    case(Mage::getModel('xpaymentsconnector/payment_savedcards')->getCode()):

                        if(!is_null($recurringProfile->getInitAmount())){
                            $paymentCardNumber = $recurringProfile->getQuote()->getPayment()->getData('xp_payment_card');
                            $card = Mage::getModel('xpaymentsconnector/usercards')->load($paymentCardNumber);
                            $cardData = $card->getData();
                            $this->resendPayDeferredRecurringTransaction($recurringProfile, $orderAmountData, $cardData);
                        } else {
                            $recurringProfile->activate();
                        }
                        $this->payDeferredProfileId = $recurringProfile->getProfileId();

                        return true;
                        break;
                    case($xpaymentCCModel->getCode()):
                            $quoteId = Mage::app()->getRequest()->getParam('quote_id');
                            $cardData = $this->getXpCardData($quoteId);
                            if (!is_null($this->payDeferredProfileId)) {
                                $this->resendPayDeferredRecurringTransaction($recurringProfile, $orderAmountData, $cardData);
                            } else {
                                $recurringProfile->setReferenceId($cardData['txnId']);
                                //check transaction state
                                $response = $xpaymentCCModel->requestPaymentInfo($cardData['txnId']);
                                if (
                                    $response->getStatus()
                                    && in_array($response->getField('status'), array(Cdev_XPaymentsConnector_Model_Payment_Cc::AUTH_STATUS, Cdev_XPaymentsConnector_Model_Payment_Cc::CHARGED_STATUS))
                                ) {
                                    if(!is_null($recurringProfile->getInitAmount())){
                                        //create order
                                        $orderId = $this->createOrder($recurringProfile, $isFirstRecurringOrder = true, $orderAmountData);
                                        //update order
                                        $xpaymentCCModel->updateOrderByXpaymentResponse($orderId, $cardData['txnId']);
                                    }

                                    $recurringProfile->activate();
                                } else {
                                    $this->addRecurringTransactionError($response);
                                    $recurringProfile->cancel();
                                }

                            }

                            $this->payDeferredProfileId = $recurringProfile->getProfileId();
                            return true;
                }

            }
        }

        $this->payDeferredProfileId = $recurringProfile->getProfileId();
        return false;
    }

    /**
     * @param Mage_Payment_Model_Recurring_Profile $recurringProfile
     * @param $orderAmountData
     * @param $cardData
     */
    public function resendPayDeferredRecurringTransaction(Mage_Payment_Model_Recurring_Profile $recurringProfile, $orderAmountData, $cardData)
    {
        $xpaymentCCModel = Mage::getModel('xpaymentsconnector/payment_cc');
        $recurringProfile->setReferenceId($cardData['txnId']);
        $orderId = NULL;
        //create order
        if (!is_null($recurringProfile->getInitAmount())) {
            $orderId = $this->createOrder($recurringProfile, $isFirstRecurringOrder = true, $orderAmountData);
        }
        $response = $xpaymentCCModel->sendAgainTransactionRequest(
            $orderId,
            NULL,
            $orderAmountData['grand_total'],
            $cardData);

        //update order
        if(!is_null($orderId)){
            $result = $xpaymentCCModel->updateOrderByXpaymentResponse($orderId, $response['response']['transaction_id']);
            if($result['success'] == false){
                $this->updateProfileFailureCount($recurringProfile);
                return;
            }
        }

        if ($response['success']) {
            $recurringProfile->activate();
        } else {
            Mage::getSingleton('checkout/session')->addError($response['error_message']);
            $recurringProfile->cancel();
        }
    }

    /**
     * @return array
     */
    public function checkStartDateData()
    {
        $result = array();
        $result['total_min_amount'] = 0;
        $quoteItems = Mage::getSingleton('checkout/session')->getQuote()->getAllItems();
        foreach ($quoteItems as $quoteItem) {
            if ($quoteItem) {
                $currentProduct = $quoteItem->getProduct();
                $isRecurringProduct = (bool)$currentProduct->getIsRecurring();
                if ($isRecurringProduct) {
                    $checkQuoteItemResult = $this->checkStartDateDataByProduct($currentProduct,$quoteItem);
                    if ($checkQuoteItemResult[$currentProduct->getId()]['success']) {
                        $minimumPaymentAmount = $checkQuoteItemResult[$currentProduct->getId()]['minimal_payment_amount'];
                        $result['total_min_amount'] += $minimumPaymentAmount;
                    }

                    $result['items'] = $checkQuoteItemResult;
                } else {
                    $result['items'][$currentProduct->getId()] = false;
                }
            }
        }

        return $result;
    }

    /**
     * @param $product
     * @param null $quoteItem
     * @return mixed
     */
    public function checkStartDateDataByProduct($product,$quoteItem = false)
    {
        $productAdditionalInfo = unserialize($product->getCustomOption('info_buyRequest')->getValue());
        $dateTimeStamp = isset($productAdditionalInfo['recurring_profile_start_datetime'])
            ? strtotime($productAdditionalInfo['recurring_profile_start_datetime'])
            : false;

        if ($dateTimeStamp) {
            $userSetTime = new Zend_Date($productAdditionalInfo['recurring_profile_start_datetime']);
            $currentZendDate = new Zend_Date(time());
            if ($userSetTime->getTimestamp() > $currentZendDate->getTimestamp()) {
                $result[$product->getId()]['success'] = true;
                $recurringProfileData = $product->getData('recurring_profile');
                if($quoteItem){
                    $initAmount = $quoteItem->getXpRecurringInitialFee();
                }else{
                    $initAmount = $recurringProfileData['init_amount'];
                }

                $defaultMinimumPayment = floatval(Mage::getStoreConfig('xpaymentsconnector/settings/xpay_zero_auth_amount'));
                $minimumPaymentAmount = ($initAmount) ? $initAmount : $defaultMinimumPayment;
                $result[$product->getId()]['minimal_payment_amount'] = $minimumPaymentAmount;

            } else {
                $result[$product->getId()]['success'] = false;
            }
        } else {
            $result[$product->getId()]['success'] = false;
        }
        return $result;
    }

    public function getRecurringProfileState()
    {
        $maxPaymentFailureMessage = $this->__('This profile has run out of maximal limit of unsuccessful payment attempts.');

        if (Mage::app()->getStore()->isAdmin()) {
            Mage::getSingleton('adminhtml/session')->addNotice($maxPaymentFailureMessage);
        } else {
            Mage::getSingleton('core/session')->addNotice($maxPaymentFailureMessage);
        }
    }

    public function addRecurringTransactionError($response = array())
    {
        if (!empty($response)) {
            if (!empty($response['error_message'])) {
                $errorMessage = $this->__("%s. The subscription has been canceled.", $response['error_message']);
                Mage::getSingleton('checkout/session')->addError($errorMessage);
            } else {
                $transactionStatusLabel = Mage::getModel('xpaymentsconnector/payment_cc')->getTransactionStatusLabels();
                $errorMessage = $this->__("Transaction status is '%s'. The subscription has been canceled.",
                    $transactionStatusLabel[$response['status']]);
                Mage::getSingleton('checkout/session')->addError($errorMessage);
            }
        } else {
            $errorMessage = $this->__('The subscription has been canceled.');
            Mage::getSingleton('checkout/session')->addError($errorMessage);
        }
        Mage::getSingleton('checkout/session')->addNotice($this->getFailureCheckoutNoticeHelper());
    }

    /**
     * This function fixed magento bug. Magento can't create user
     * during checkout with recurring products.
     * @return mixed
     * @throws Exception
     */
    public function registeredUser()
    {
        $transaction = Mage::getModel('core/resource_transaction');
        $quote = Mage::getSingleton('checkout/session')->getQuote();
        if ($quote->getCustomerId()) {
            $customer = $quote->getCustomer();
            $transaction->addObject($customer);
        }

        try {
            $transaction->save();
            $customer->save();
            return $customer;
        } catch (Exception $e) {

            if (!Mage::getSingleton('customer/session')->isLoggedIn()) {
                // reset customer ID's on exception, because customer not saved
                $quote->getCustomer()->setId(null);
            }

            throw $e;
        }
        return false;
    }

    /**
     * Add default settings for submitRecurringProfile function
     * @param Mage_Payment_Model_Recurring_Profile $profile
     */
    public function addXpDefaultRecurringSettings(Mage_Payment_Model_Recurring_Profile $profile){
        // set primary 'recurring profile' state
        $profile->setState(Mage_Sales_Model_Recurring_Profile::STATE_PENDING);

        $quote = $profile->getQuote();
        $customerId = $quote->getCustomer()->getId();
        if (is_null($customerId)) {
            $customer = $this->registeredUser();
            if($customer){
                $customerId = $customer->getId();
            }
        }
        $profile->setCustomerId($customerId);
        $orderItemInfo = $profile->getOrderItemInfo();
        if($orderItemInfo['xp_recurring_initial_fee']){
            $profile->setInitAmount($orderItemInfo['xp_recurring_initial_fee']);
        }

    }

    /**
     * Calculate tax for product custom price.
     * @param $product
     * @param $price
     * @return mixed
     */
    public function calculateTaxForProductCustomPrice($product, $price)
    {
        $quote = Mage::getSingleton('checkout/session')->getQuote();
        $store = Mage::app()->getStore($quote->getStoreId());
        $request = Mage::getSingleton('tax/calculation')->getRateRequest(null, null, null, $store);
        $taxClassId = $product->getData('tax_class_id');
        $percent = Mage::getSingleton('tax/calculation')->getRate($request->setProductClassId($taxClassId));
        $tax = $price * ($percent / 100);

        return $tax;
    }

    /**
     * orderAmountData
     * - discount_amount
     * - tax_amount
     * - shipping_amount
     * - product_subtotal
     * @param Mage_Payment_Model_Recurring_Profile $recurringProfile
     * @return array
     */
    public function preparePayDeferredOrderAmountData(Mage_Payment_Model_Recurring_Profile $recurringProfile)
    {
        $quoteItemInfo = $recurringProfile->getQuoteItemInfo();

        if(!is_null($quoteItemInfo)){
            $currentProduct = $quoteItemInfo->getProduct();
        }else{
            $orderItemInfo = $recurringProfile->getOrderItemInfo();
            if(is_string($orderItemInfo)){
                $orderItemInfo = unserialize($orderItemInfo);
            }
            $currentProduct = Mage::getModel('catalog/product')->load($orderItemInfo['product_id']);
        }

        $orderAmountData = array();
        if(!is_null($recurringProfile->getInitAmount())){
            $orderAmountData['discount_amount'] = 0;
            if(isset($orderItemInfo['initialfee_tax_amount']) && !empty($orderItemInfo['initialfee_tax_amount']) ){
                $orderAmountData['tax_amount'] = $orderItemInfo['initialfee_tax_amount'];
            }else{
                $orderAmountData['tax_amount'] =
                    $this->calculateTaxForProductCustomPrice($currentProduct,$recurringProfile->getInitAmount());
            }
            $orderAmountData['shipping_amount'] = 0;
            $orderAmountData['product_subtotal'] = $recurringProfile->getInitAmount();
            $orderAmountData['grand_total'] = $orderAmountData['tax_amount'] + $orderAmountData['product_subtotal'];
        }

        return $orderAmountData;
    }

    /**
     * @return string
     */
    public function getFailureCheckoutNoticeHelper()
    {
        $noticeMessage = "Did you enter your billing info correctly? Here are a few things to double check:<br />".
        "1. Ensure your billing address matches the address on your credit or debit card statement;<br />".
        "2. Check your card verification code (CVV) for accuracy;<br />".
        "3. Confirm you've entered the correct expiration date.";

        $noticeHelper = $this->__($noticeMessage);

        return $noticeHelper;
    }

    /**
     * Prepare masked card data as a string
     *
     * @param array $data Masked card data
     * @param bool $withType Include card type or not
     *
     * @return string
     */
    public function prepareCardDataString($data, $withType = false)
    {
        $result = '';

        if (!empty($data)) {

            if (isset($data['last4'])) {
                $last4 = $data['last4'];
            } elseif (isset($data['last_4_cc_num'])) {
                $last4 = $data['last_4_cc_num'];
            } else {
                $last4 = '****';
            }

            if (isset($data['first6'])) {
                $first6 = $data['first6'];
            } else {
                $first6 = '******';
            }

            $result = $first6 . '******' . $last4;       
 
            if (
                !empty($data['expire_month']) 
                && !empty($data['expire_year'])
            ) {
                $result .= ' (' . $data['expire_month'] . '/' . $data['expire_year'] . ')';
            }

            if (
                $withType
                && !empty($data['type'])
            ) {
                $result = strtoupper($data['type']) . ' ' . $result;
            }
        }

        return $result;
    }

    /**
     * Get callback URL for initial payment request 
     *
     * @param string $entityId   Quote ID or Cutomer ID
     * @param int    $xpcSlot    Slot index of the XPC payment method
     * @param bool   $isZeroAuth Is it zero auth request
     *
     * @return array
     */
    public function getCallbackUrl($entityId, $xpcSlot, $isZeroAuth = false)
    {
        $params = array(
            '_secure'  => !Mage::helper('settings_xpc')->getXpcConfig('xpay_force_http'),
            '_nosid'   => true,
            'xpc_slot' => $xpcSlot,
        );

        if ($isZeroAuth) {

            $params['customer_id'] = $entityId;

        } else {

            $params['quote_id'] = $entityId;
        }

        $url = Mage::getUrl('xpaymentsconnector/processing/callback', $params);

        return $url;
    }

    /**
     * Get return URL for initial payment request
     *
     * @param string $entityId   Quote ID or Cutomer ID
     * @param int    $xpcSlot    Slot index of the XPC payment method
     * @param bool   $isZeroAuth Is it zero auth request
     * @param bool   $isBackend  Is it backend order or not
     *
     * @return array
     */
    public function getReturnUrl($entityId, $xpcSlot, $isZeroAuth = false, $isBackend = false)
    {
        $params = array(
            '_secure'  => !Mage::helper('settings_xpc')->getXpcConfig('xpay_force_http'),
            '_nosid'   => true,
            'xpc_slot' => $xpcSlot,
        );

        if ($isZeroAuth) {

            $params['customer_id'] = $entityId;

            if ($isBackend) {
                $url = Mage::helper('adminhtml')->getUrl('adminhtml/addnewcard/return', $params);
            } else {
                $url = Mage::getUrl('xpaymentsconnector/customer/return', $params);
            }

        } else {

            $params['quote_id'] = $entityId;

            if ($isBackend) {
                $url = Mage::helper('adminhtml')->getUrl('adminhtml/sales_order_payment/return', $params);
            } else {
                $url = Mage::getUrl('xpaymentsconnector/processing/return', $params);
            }
        }

        return $url;
    }

    /**
     * Get error message from X-Payments callback or detailed info data
     *
     * @param array $data Callback data
     *
     * @return string
     */
    public function getResultMessage($data)
    {
        $message = array();

        // Regular message from X-Payments
        if (!empty($data['message'])) {
            $message[] = $data['message'];
        }

        if (isset($data['advinfo'])) {

            // Message from payment gateway
            if (isset($data['advinfo']['message'])) {
                $message[] = $data['advinfo']['message'];
            }

            // Message from 3-D Secure
            if (isset($data['advinfo']['s3d_message'])) {
                $message[] = $data['advinfo']['s3d_message'];
            }
        }

        $message = array_unique($message);

        return implode("\n", $message);
    }

    /**
     * Get address data saved at checkout
     *
     * @param array  $data Checkout data
     * @param string $type Address type 'billing' or 'shipping'
     *
     * @return array
     */
    protected function getCheckoutAddressData($data, $type)
    {
        $addressBookData = $checkoutAddressData = array();

        $key = $type . '_address_id';

        if (isset($data[$key])) {

            // Address data from the address book
            $address = Mage::getModel('customer/address')->load($data[$key]);
            $addressBookData = array_filter($address->getData());
        }

        if (isset($data[$type])) {

            if (
                isset($data[$type]['street'])
                && is_array($data[$type]['street'])
            ) {

                // Prevent array to string conversion notice.
                // Necessary for:
                //  - Registration at checkout for OSC module
                $data[$type]['street'] = implode(PHP_EOL, $data[$type]['street']);
            }

            // Addrress data from checkout
            $checkoutAddressData = array_filter($data[$type]);
        }

        // Address data from checkout has a higher priority
        $result = array_merge(
            $addressBookData,
            $checkoutAddressData
        );

        return $result;
    }

    /**
     * Process data saved at checkout
     *
     * @param Cdev_XPaymentsConnector_Model_Quote $quote
     *
     * @return void
     */
    protected function processCheckoutData(Cdev_XPaymentsConnector_Model_Quote $quote)
    {
        // Check if external checkout module is enabled
        $isOscModuleEnabled = Mage::helper('settings_xpc')->checkOscModuleEnabled();
        $isFirecheckoutModuleEnabled = Mage::helper('settings_xpc')->checkFirecheckoutModuleEnabled();

        // Grab data saved at checkout
        $data = unserialize($quote->getXpcData()->getData('checkout_data'));

        // Add billing address data from checkout
        $quote->getBillingAddress()->addData($this->getCheckoutAddressData($data, 'billing'));

        if (
            isset($data['billing']['use_for_shipping'])
            && (bool)$data['billing']['use_for_shipping']
        ) {

            // Overwrite shipping address data with billing one
            $data['shipping'] = $data['billing'];

            if (
                $isOscModuleEnabled 
                && !empty($data['billing_address_id'])
            ) {
                $shippingAddress = Mage::getModel('customer/address')->load($data['billing_address_id']);
                $data['shipping'] = $shippingAddress->getData();
            }
        }

        if (
            !empty($data['billing']['telephone'])
            && empty($data['shipping']['telephone'])
        ) {
            // WA fix for Firecheckout which doesn't fill shipping phone
            $data['shipping']['telephone'] = $data['billing']['telephone'];
        }

        // Add shipping address data from checkout
        $quote->getShippingAddress()->addData($this->getCheckoutAddressData($data, 'shipping'))->save();

        if (!$isOscModuleEnabled) {

            // Save shipping method
            if (!empty($data['shipping_method'])) {

                if ($isFirecheckoutModuleEnabled) {
                    // Necessary for the FC. 
                    // See TM_FireCheckout_IndexController::saveShippingAction()
                    $quote->collectTotals();
                }

                $quote->getShippingAddress()->setCollectShippingRates(true)->collectShippingRates();
                $quote->setShippingMethod($data['shipping_method']);
            }
        }

        $copyFields = array(
            'email',
            'firstname',
            'lastname',
        );

        foreach ($copyFields as $field) {

            if (
                !$quote->getData('customer_' . $field)
                && !empty($data['billing'][$field])
            ) {
                $quote->setData('customer_' . $field, $data['billing'][$field]);
            }
        }
    }

    /**
     * Create customer and assign it to the quote 
     *
     * @param Cdev_XPaymentsConnector_Model_Quote $quote
     *
     * @return Mage_Customer_Model_Customer
     */
    protected function createNewCustomer(Cdev_XPaymentsConnector_Model_Quote $quote)
    {
        $billing = $quote->getBillingAddress();

        $shipping = $quote->isVirtual() 
            ? null 
            : $quote->getShippingAddress();

        $customer = $quote->getCustomer();

        $customerBilling = $billing->exportCustomerAddress();
        $customer->addAddress($customerBilling);
        $billing->setCustomerAddress($customerBilling);
        $customerBilling->setIsDefaultBilling(true);

        if (
            $shipping 
            && !$shipping->getSameAsBilling()
        ) {

            $customerShipping = $shipping->exportCustomerAddress();
            $customer->addAddress($customerShipping);
            $shipping->setCustomerAddress($customerShipping);
            $customerShipping->setIsDefaultShipping(true);

        } else {

            $customerBilling->setIsDefaultShipping(true);
        }

        Mage::helper('core')->copyFieldset('checkout_onepage_quote', 'to_customer', $quote, $customer);

        $password = '';

        if ($quote->getPasswordHash()) {
            // One page checkout
            $password = $customer->decryptPassword($quote->getPasswordHash());
        } else {
            $data = unserialize($quote->getXpcData()->getData('checkout_data'));
            if (!empty($data['billing']['customer_password'])) {
                // One step checkout
                $password = $data['billing']['customer_password'];
            }
        }

        $customer->setPassword($password);

        $quote->setCustomer($customer)
            ->setCustomerId(true);

        $quote->save();
        $customer->save();

        $this->writeLog('Created new customer', $customer->getId());

        return $customer;
    }

    /**
     * Check if new customer profile should be created
     *
     * @param Mage_Sales_Model_Quote $quote
     * @param $includeLoginMethod Consider login_in method or not
     *
     * @return bool
     */
    public function isCreateNewCustomer(Cdev_XPaymentsConnector_Model_Quote $quote, $includeLoginMethod = false)
    {
        $result = false;

        $settings = Mage::helper('settings_xpc');

        if ($settings->checkOscModuleEnabled()) {

            // For One Step Checkout module
            $data = unserialize($quote->getXpcData()->getData('checkout_data'));

            $result = isset($data['create_account']) 
                && (bool)$data['create_account'];

        } elseif ($settings->checkFirecheckoutModuleEnabled()) {

            // For Firecheckout module
            $data = unserialize($quote->getXpcData()->getData('checkout_data'));

            $result = isset($data['billing']['register_account']) 
                && (bool)$data['billing']['register_account'];
            
        } else {

            // For default one page checkout
            $checkoutMethod = $quote->getCheckoutMethod();

            $result = self::METHOD_REGISTER == $checkoutMethod
                || $includeLoginMethod && self::METHOD_LOGIN_IN == $checkoutMethod;
        }

        return $result;
    }

    /**
     * Convert quote to order
     *
     * @param Cdev_XPaymentsConnector_Model_Quote $quote
     *
     * @return string
     */
    public function funcPlaceOrder(Cdev_XPaymentsConnector_Model_Quote $quote)
    {
        $refId = false;

        try {

            // Process data saved at checkout
            $this->processCheckoutData($quote);

            if ($this->isCreateNewCustomer($quote)) {
            
                // Create customer's profile  who's registered at checkout
                $this->createNewCustomer($quote);

                $quote->getXpcData()->setData('address_saved', true)->save();
            }

            // Set payment method (maybe not necessary. Just in case)
            $quote->setTotalsCollectedFlag(false)->collectTotals()->getPayment()->setMethod('xpayments' . $quote->getXpcSlot());

            // Place order
            $service = Mage::getModel('sales/service_quote', $quote);
            $service->submitAll();

            $order = $service->getOrder();

            if (!$order) {

                $orderId = $quote->getXpcData()->getData('recurring_order_id');

                if ($orderId) {
                    $order = Mage::getModel('sales/order')->load($orderId);
                }

                if (!$order) {

                    // Cannot proceed further without an order anyway
                    throw new Exception('Quote was not converted to order');
                }
            }

            $quote->setIsActive(false)->save();

            $xpcData = $quote->getXpcData()->getData();
            $order->setData(self::XPC_DATA, serialize($xpcData));

            $order->setData('xpc_txnid', $quote->getXpcData()->getData('txn_id'))->save();

            $refId = $order->getIncrementId();

            $this->writeLog('Placed order #' . $refId, $xpcData);

        } catch (Exception $e) {

            $this->writeLog('Unable to create order: ' . $e->getMessage(), $e->getTraceAsString());

            // Save error message in quote
            $quote->getXpcData()
                ->setData('xpc_message', $e->getMessage())
                ->save();
        }

        return $refId;
    }

    /**
     * Create invoice for the charged payment
     *
     * @param Mage_Sales_Model_Order $order
     *
     * @return void
     */ 
    public function processCreateInvoice(Mage_Sales_Model_Order $order)
    {
        if (
            $order->getStatus() == self::STATUS_CHARGED
            && $order->canInvoice()
        ) {

            $invoice = Mage::getModel('sales/service_order', $order)->prepareInvoice();
            $invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_ONLINE);

            $invoice->register();

            $transaction = Mage::getModel('core/resource_transaction')
                ->addObject($invoice)
                ->addObject($invoice->getOrder());

            $transaction->save();
        }
    }

    /** 
     * Get recurring profile for order
     *
     * @param Mage_Sales_Model_Order $order Order
     *
     * @return Mage_Sales_Model_Recurring_Profile or false
     */
    public function getOrderRecurringProfile(Mage_Sales_Model_Order $order)
    {
        $txnId = $order->getData('xpc_txnid');

        try {

            $profile = Mage::getModel('sales/recurring_profile')->load($txnId, 'reference_id');

            if (!$profile->isValid()) {

                $profile = false;
            }

        } catch (Exception $e) {

            $this->writeLog('Unable to load recurring profile for reference ' . $txnId, $e->getMessage());

            $profile = false;
        }

        return $profile;
    }

    /**
     * Just a wrapper to omit sid and secure params
     *
     * @param $path URL path
     *
     * @return string
     */
    protected function getXpcCheckoutUrl($path, $params = array())
    {
        $params += array(
            '_nosid'  => true,
            '_secure' => !Mage::helper('settings_xpc')->getXpcConfig('xpay_force_http'), 
        );

        return Mage::getUrl($path, $params);
    }

    /**
     * Get redirect URL 
     *
     * @param int    $xpcSlot Slot index of the XPC payment method
     * @param bool   $dropToken Drop current token or not
     * @param string $checkoutMethod Checkout method (guest, register, etc)
     * @param array  $params URL parameters
     *
     * @return string
     */
    protected function getRedirectUrl($xpcSlot, $dropToken = false, $checkoutMethod = '', $params = array())
    {
        $params['xpc_slot'] = $xpcSlot;

        if ($dropToken) {
            $params['drop_token'] = '1';
        }

        if ($checkoutMethod) {
            $params['checkout_method'] = $checkoutMethod;
        }

        return $this->getXpcCheckoutUrl('xpaymentsconnector/processing/redirect', $params);
    }

    /**
     * Get some JSON data for javascript at checkout
     * 
     * @return string 
     */
    public function getXpcJsonData()
    {
        $settings = Mage::helper('settings_xpc');

        // Display iframe on the review step (after payment step) or not
        $isDisplayOnReviewStep = 'review' == $settings->getIframePlace();

        $data = array(
            'origins'             => $settings->getAllowedOrigins(),
            'displayOnReviewStep' => $isDisplayOnReviewStep,
            'useIframe'           => $settings->isUseIframe(),
            'isOneStepCheckout'   => $settings->checkOscModuleEnabled(),
            'isFirecheckout'      => $settings->checkFirecheckoutModuleEnabled(),   
            'height'              => 0,
        );

        for ($xpcSlot = 1; $xpcSlot <= Cdev_XPaymentsConnector_Helper_Settings_Data::MAX_SLOTS; $xpcSlot++) {
            $data['url']['xpayments' . $xpcSlot] = array(
                'redirect'             => $this->getRedirectUrl($xpcSlot),
                'dropTokenAndRedirect' => $this->getRedirectUrl($xpcSlot, true),
                'setMethodRegister'    => $this->getRedirectUrl($xpcSlot, true, 'register'),
                'setMethodGuest'       => $this->getRedirectUrl($xpcSlot, true, 'guest'),
                'saveCheckoutData'     => $this->getXpcCheckoutUrl('xpaymentsconnector/processing/save_checkout_data', array('xpc_slot' => $xpcSlot)),
                'changeMethod'         => $this->getXpcCheckoutUrl('checkout/cart/'),
                'checkAgreements'      => $this->getXpcCheckoutUrl('xpaymentsconnector/processing/check_agreements'),
            );
        }

        return json_encode($data, JSON_FORCE_OBJECT);
    }

    /**
     * Clear init data and something about "unset prepared order"
     *
     * @param Cdev_XPaymentsConnector_Model_Quote $quote
     *
     * @return void 
     */
    public function resetInitData(Cdev_XPaymentsConnector_Model_Quote $quote)
    {
        $quote->getXpcData()->clear();
    }

    /**
     * Find last order by X-Payments transaction ID
     *
     * @param string $txnId
     * @param string $parentTxnId
     *
     * @return Mage_Sales_Model_Order
     */
    public function getOrderByTxnId($txnId, $parentTxnId = '')
    {
        $order = Mage::getModel('sales/order')->getCollection()
            ->addAttributeToSelect('*')
            ->addFieldToFilter('xpc_txnid', $txnId)
            ->getLastItem();

        if (
            !$order->getId()
            && $parentTxnId
        ) {

            $order = Mage::getModel('sales/order')->getCollection()
                ->addAttributeToSelect('*')
                ->addFieldToFilter('xpc_txnid', $parentTxnId)
                ->getLastItem();

            // We've found the child order, so now update its transaction reference with the actual value
            $order->setData('xpc_txnid', $txnId)->save();
        }

        return $order;
    }

    /**
     * Get order's Fraud check data colection 
     *
     * @param string $orderId
     *
     * @return Cdev_XPaymentsConnector_Model_Mysql4_FraudCheckData_Collection
     */
    public function getOrderFraudCheckData($orderId)
    {
        return Mage::getModel('xpaymentsconnector/fraudcheckdata')->getCollection()
            ->addAttributeToSelect('*')
            ->addFieldToFilter('order_id', $orderId);
    }
}
