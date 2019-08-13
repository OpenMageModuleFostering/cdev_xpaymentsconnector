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
 * Common helper
 *
 * @package Cdev_XPaymentsConnector
 * @see     ____class_see____
 * @since   1.0.0
 */

class Cdev_XPaymentsConnector_Helper_Data extends Mage_Payment_Helper_Data
{
    const DAY_TIME_STAMP = 86400;
    const WEEK_TIME_STAMP = 604800;
    const SEMI_MONTH_TIME_STAMP = 1209600;

    const STATE_XPAYMENT_PENDING_PAYMENT = 'xp_pending_payment';

    const XPAYMENTS_LOG_FILE = 'xpayments.log';
    const RECURRING_ORDER_TYPE = 'recurring';
    const SIMPLE_ORDER_TYPE = 'simple';

    /**
     * save/update qty for createOrder function.
     * @var int
     */
    protected $itemsQty = null;
    public  $payDeferredProfileId = null;

    /**
     * This function return 'IncrementId' for feature order.
     * Xpayment Prepare Order Mas(xpayment_prepare_order):
     * - prepare_order_id (int)
     * - xpayment_response
     * - token
     * return
     * @return bool or int
     */
    public function getOrderKey()
    {
        $xpaymentPrepareOrderData = Mage::getSingleton('checkout/session')->getData('xpayment_prepare_order');
        if ($xpaymentPrepareOrderData && isset($xpaymentPrepareOrderData['prepare_order_id'])) {
            return $xpaymentPrepareOrderData['prepare_order_id'];
        }
        return false;
    }

    /**
     * This function create 'IncrementId' for feature order.
     * Xpayment Prepare Order Mas(xpayment_prepare_order):
     * - prepare_order_id (int)
     * - xpayment_response
     * - token
     * return
     * @return bool or int
     */
    public function prepareOrderKey()
    {
        $xpaymentPrepareOrder = Mage::getSingleton('checkout/session')->getData('xpayment_prepare_order');
        if(!isset($xpaymentPrepareOrder["prepare_order_id"])){
            $this->prepareSimpleOrderKey();
        }

    }

    public function updateRecurringMasKeys(Mage_Payment_Model_Recurring_Profile $recurringProfile)
    {
        $orderItemInfo = $recurringProfile->getData('order_item_info');
        $xpaymentPrepareOrder = Mage::getSingleton('checkout/session')->getData('xpayment_prepare_order');
        $xpaymentPrepareOrder['recurring_mas'][$orderItemInfo['product_id']] = $this->getOrderKey();
        Mage::getSingleton('checkout/session')->setData('xpayment_prepare_order', $xpaymentPrepareOrder);
    }

    public function getPrepareRecurringMasKey(Mage_Payment_Model_Recurring_Profile $recurringProfile)
    {
        $xpaymentPrepareOrder = Mage::getSingleton('checkout/session')->getData('xpayment_prepare_order');
        if ($xpaymentPrepareOrder && isset($xpaymentPrepareOrder['recurring_mas'])) {
            $orderItemInfo = $recurringProfile->getData('order_item_info');
            $prodId = $orderItemInfo['product_id'];
            if (isset($xpaymentPrepareOrder['recurring_mas'][$prodId])) {
                return $xpaymentPrepareOrder['recurring_mas'][$prodId];
            }
        }
        return false;
    }


    /**
     * @return mixed
     */
    public function prepareSimpleOrderKey()
    {
        $storeId = Mage::app()->getStore()->getStoreId();
        $prepareOrderId = Mage::getSingleton('eav/config')
            ->getEntityType('order')
            ->fetchNewIncrementId($storeId);

        $xpaymentPrepareOrder = Mage::getSingleton('checkout/session')->getData('xpayment_prepare_order');
        $xpaymentPrepareOrder['prepare_order_id'] = $prepareOrderId;

        Mage::getSingleton('checkout/session')->setData('xpayment_prepare_order', $xpaymentPrepareOrder);
        return $prepareOrderId;

    }


    /**
     * This function set 'place_display' flag for feature x-payment form.
     * Xpayment Prepare Order Mas(xpayment_prepare_order):
     * - prepare_order_id (int)
     * - xpayment_response
     * - token
     * - place_display
     * return
     * @return bool or int
     */
    public function setIframePlaceDisplaySettings()
    {
        $useIframe = Mage::getStoreConfig('payment/xpayments/use_iframe');
        if ($useIframe) {
            $xpaymentPrepareOrder = Mage::getSingleton('checkout/session')->getData('xpayment_prepare_order');
            $xpaymentPrepareOrder['place_display'] = Mage::getStoreConfig('payment/xpayments/placedisplay');
            Mage::getSingleton('checkout/session')->setData('xpayment_prepare_order', $xpaymentPrepareOrder);
        }
    }

    /**
     * check  'xpayment' config settigs
     * @return bool
     */
    public function isIframePaymentPlaceDisplay()
    {
        $xpaymentPrepareOrder = Mage::getSingleton('checkout/session')->getData('xpayment_prepare_order');
        if (isset($xpaymentPrepareOrder['place_display']) && ($xpaymentPrepareOrder['place_display'] == 'payment')) {
            return true;
        }
        return false;
    }

    /**
     * This function return saved card data from X-Payments response.
     * @return bool or array
     */
    public function getXpCardData($quoteId = null)
    {
        if (is_null($quoteId)) {
            $currentCart = Mage::getModel('checkout/cart')->getQuote();
            $quoteId = $currentCart->getEntityId();
        }
        $cartModel = Mage::getModel('sales/quote')->load($quoteId);
        $cardData = $cartModel->getXpCardData();
        if (!empty($cardData)) {
            $cardData = unserialize($cardData);
            return $cardData;
        }
        return false;
    }

    /**
     * This function save user card data to Quote
     * @param null $quoteId
     * @param array $cardData
     * @return array|bool|mixed
     */

    public function saveXpCardData($quoteId = null,$cardData = array())
    {
        if (is_null($quoteId)) {
            $currentCart = Mage::getModel('checkout/cart')->getQuote();
            $quoteId = $currentCart->getEntityId();
        }
        $cartModel = Mage::getModel('sales/quote')->load($quoteId);
        $cardData = serialize($cardData);
        $cartModel->setXpCardData($cardData);

        $cartModel->save();
    }

    /**
     * This function return 'token' for X-Payments i-frame Url.
     * Xpayment Prepare Order Mas(xpayment_prepare_order):
     * - prepare_order_id (int)
     * - xpayment_response
     * - token
     * return
     * @return bool or int
     */
    public function getIframeToken()
    {
        $xpaymentPrepareOrder = Mage::getSingleton('checkout/session')->getData('xpayment_prepare_order');
        if ($xpaymentPrepareOrder && isset($xpaymentPrepareOrder['token']) && !empty($xpaymentPrepareOrder['token'])) {
            return $xpaymentPrepareOrder['token'];
        } else {
            $api = Mage::getModel('xpaymentsconnector/payment_cc');
            $result = $api->sendIframeHandshakeRequest();
            $xpaymentPrepareOrder['token'] = $result['response']['token'];
            Mage::getSingleton('checkout/session')->setData('xpayment_prepare_order', $xpaymentPrepareOrder);
            return $xpaymentPrepareOrder['token'];
        }

    }

    /**
     * This function sets a type for prepared order
     * Xpayment Prepare Order Mas(xpayment_prepare_order):
     * - prepare_order_id (int)
     * - xpayment_response
     * - type (string)
     * - is_recurring
     */
    public function setPrepareOrderType()
    {
        $xpaymentPrepareOrder = Mage::getSingleton('checkout/session')->getData('xpayment_prepare_order');
        $result = $this->checkIssetRecurringOrder();
        if ($result['isset']) {
            $xpaymentPrepareOrder['type'] = self::RECURRING_ORDER_TYPE;
        } else {
            $xpaymentPrepareOrder['type'] = self::SIMPLE_ORDER_TYPE;
        }

        Mage::getSingleton('checkout/session')->setData('xpayment_prepare_order', $xpaymentPrepareOrder);
    }

    /**
     * This function checks a type for prepared order
     * Xpayment Prepare Order Mas(xpayment_prepare_order):
     * - prepare_order_id (int)
     * - xpayment_response
     * - type (string)
     * - is_recurring
     * @return bool
     */
    public function checkIsRecurringPrepareOrderType()
    {
        $xpaymentPrepareOrder = Mage::getSingleton('checkout/session')->getData('xpayment_prepare_order');
        if ($xpaymentPrepareOrder && isset($xpaymentPrepareOrder['type']) && !empty($xpaymentPrepareOrder['type'])) {
            ($xpaymentPrepareOrder['type'] == self::RECURRING_ORDER_TYPE) ? true : false;
            return ($xpaymentPrepareOrder['type'] == self::RECURRING_ORDER_TYPE) ? true : false;
        } else {
            return false;
        }
    }

    /**
     * Unset prepare order params
     * @param array $unsetParams
     */
    public function unsetXpaymentPrepareOrder($unsetParams = array())
    {
        if (!empty($unsetParams)) {
            $xpaymentPrepareOrder = Mage::getSingleton('checkout/session')->getData('xpayment_prepare_order');
            foreach ($unsetParams as $param) {
                if (is_array($xpaymentPrepareOrder) && isset($xpaymentPrepareOrder[$param])) {
                    unset($xpaymentPrepareOrder[$param]);
                }
            }
            Mage::getSingleton('checkout/session')->setData('xpayment_prepare_order', $xpaymentPrepareOrder);
            return;
        }

        Mage::getSingleton('checkout/session')->unsetData('xpayment_prepare_order');
    }

    /**
     * Save X-Payments response data after card data send.
     * - xpayment_response
     * @param array $responseData
     */
    public function savePaymentResponse($responseData)
    {
        $xpaymentPrepareOrder = Mage::getSingleton('checkout/session')->getData('xpayment_prepare_order');
        $xpaymentPrepareOrder['xpayment_response'] = $responseData;
        Mage::getSingleton('checkout/session')->setData('xpayment_prepare_order', $xpaymentPrepareOrder);
    }

    /**
     * Save all allowed payments for current checkout session in store.
     * - allowed_payments
     * @param array $methods
     */
    public function setAllowedPaymentsMethods($methodsInstances)
    {
        $xpaymentPrepareOrder = Mage::getSingleton('checkout/session')->getData('xpayment_prepare_order');
        $xpaymentPrepareOrder['allowed_payments'] = $methodsInstances;
        $methods = array();
        foreach ($methodsInstances as $methodInstance) {
            $methods[]['method_code'] = $methodInstance->getCode();
        }
        $xpaymentPrepareOrder['allowed_payments'] = $methods;
        Mage::getSingleton('checkout/session')->setData('xpayment_prepare_order', $xpaymentPrepareOrder);
    }

    /**
     * get all allowed payments for current checkout session in store.
     * - allowed_payments
     */
    public function getAllowedPaymentsMethods()
    {
        $xpaymentPrepareOrder = Mage::getSingleton('checkout/session')->getData('xpayment_prepare_order');
        if ($xpaymentPrepareOrder && isset($xpaymentPrepareOrder['allowed_payments']) && !empty($xpaymentPrepareOrder['allowed_payments'])) {
            return $xpaymentPrepareOrder['allowed_payments'];
        }
        return false;
    }


    public function getReviewButtonTemplate($name, $block)
    {
        $quote = Mage::getSingleton('checkout/session')->getQuote();
        $useIframe = Mage::getStoreConfig('payment/xpayments/use_iframe');
        $xpCcMethodCode = Mage::getModel('xpaymentsconnector/payment_cc')->getCode();
        if ($quote) {
            $payment = $quote->getPayment();
            if ($payment && $payment->getMethod() == $xpCcMethodCode && $useIframe) {
                return $name;
            }
        }

        if ($blockObject = Mage::getSingleton('core/layout')->getBlock($block)) {
            return $blockObject->getTemplate();
        }

        return '';
    }

    public function isNeedToSaveUserCard()
    {
        $result = (bool)Mage::getSingleton('checkout/session')->getData('user_card_save');
        return $result;
    }

    public function userCardSaved(){
        Mage::getSingleton('checkout/session')->setData('user_card_save',false);
    }

    public function isRegisteredUser()
    {
        $currentCustomerState = Mage::getSingleton('checkout/type_onepage')->getCheckoutMethod();
        $registerMethod = Mage_Checkout_Model_Type_Onepage::METHOD_REGISTER;
        $customerMethod = Mage_Checkout_Model_Type_Onepage::METHOD_CUSTOMER;
        if (($currentCustomerState == $registerMethod) || ($currentCustomerState == $customerMethod)) {
            return true;
        } else {
            return false;
        }
    }

    public function isXpaymentsMethod($currentPaymentCode)
    {
        $xpaymentPaymentCode = Mage::getModel('xpaymentsconnector/payment_cc')->getCode();
        $saveCardsPaymentCode = Mage::getModel('xpaymentsconnector/payment_savedcards')->getCode();
        $prepaidpayments = Mage::getModel('xpaymentsconnector/payment_prepaidpayments')->getCode();
        $usePaymetCodes = array($xpaymentPaymentCode, $saveCardsPaymentCode, $prepaidpayments);
        if (in_array($currentPaymentCode, $usePaymetCodes)) {
            return true;
        } else {
            return false;
        }
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

        if ($this->itemsQty > 1) {
            // update order key
            $unsetParams = array('prepare_order_id');
            $this->unsetXpaymentPrepareOrder($unsetParams);
            $this->prepareOrderKey();
        }
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
                //unset order Key
                $unsetParams = array('prepare_order_id');
                $this->unsetXpaymentPrepareOrder($unsetParams);
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

    public function checkIssetRecurringOrder()
    {
        $checkoutSession = Mage::getSingleton('checkout/session');
        $quoteItems = $checkoutSession->getQuote()->getAllItems();
        $result = array();

        foreach ($quoteItems as $quoteItem) {
            if ($quoteItem) {
                $product = $quoteItem->getProduct();
                $issetRecurringOreder = (bool)$product->getIsRecurring();
                if ($issetRecurringOreder) {
                    $result['isset'] = $issetRecurringOreder;
                    $result['quote_item'] = $quoteItem;
                    return $result;
                }
            }
        }
        $result['isset'] = false;

        return $result;
    }

    public function checkIssetSimpleOrder()
    {
        $checkoutSession = Mage::getSingleton('checkout/session');
        $quoteItems = $checkoutSession->getQuote()->getAllItems();

        foreach ($quoteItems as $quoteItem) {
            if ($quoteItem) {
                $product = $quoteItem->getProduct();
                $issetRecurringOreder = (bool)$product->getIsRecurring();
                if (!$issetRecurringOreder) {
                    return true;
                }
            }
        }

        return false;
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
        $startDateTime = $infoBuyRequest['recurring_profile_start_datetime'];
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
                    $orderAmountData['grand_total'] = floatval(Mage::getStoreConfig('xpaymentsconnector/settings/xpay_minimum_payment_recurring_amount'));
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
                                list($status, $response) = $xpaymentCCModel->requestPaymentInfo($cardData['txnId']);
                                if (
                                    $status
                                    && in_array($response['status'], array(Cdev_XPaymentsConnector_Model_Payment_Cc::AUTH_STATUS, Cdev_XPaymentsConnector_Model_Payment_Cc::CHARGED_STATUS))
                                ) {
                                    if(!is_null($recurringProfile->getInitAmount())){
                                        //create order
                                        $orderId = $this->createOrder($recurringProfile, $isFirstRecurringOrder = true, $orderAmountData);
                                        //update order
                                        $xpaymentCCModel->updateOrderByXpaymentResponse($orderId, $cardData['txnId']);
                                    }

                                    Mage::getSingleton('checkout/session')->setData('user_card_save', true);
                                    $xpaymentCCModel->saveUserCard($cardData, Cdev_XPaymentsConnector_Model_Usercards::RECURRING_CARD);
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
        $dateTimeStamp = strtotime($productAdditionalInfo['recurring_profile_start_datetime']);

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

                $defaultMinimumPayment = floatval(Mage::getStoreConfig('xpaymentsconnector/settings/xpay_minimum_payment_recurring_amount'));
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
        $this->unsetXpaymentPrepareOrder();
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

    public function setRecurringProductDiscount()
    {
        $quote = Mage::getSingleton('checkout/session')->getQuote();
        $items = $quote->getAllVisibleItems();
        foreach ($items as $item) {
            if($item->getIsNominal()){
                $discount = $item->getDiscountAmount();
                $profile = $item->getProduct()->getRecurringProfile();
                $profile['discount_amount'] = $discount;
                $item->getProduct()->setRecurringProfile($profile)->save();
            }
        }
    }

    public function getIframeUrl()
    {

        $xpaymentFormData = Mage::helper('payment')->getMethodInstance('xpayments')->getFormFields();
        $xpaymentFormUrl = Mage::helper('payment')->getMethodInstance('xpayments')->getUrl();

        $this->prepareOrderKey();
        $token = $this->getIframeToken();
        $iframeUrlDataArray = array('target' => $xpaymentFormData['target'], 'token' => $token);
        $iframeUrl = $xpaymentFormUrl . "?" . http_build_query($iframeUrlDataArray);
        return $iframeUrl;
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
     * Save custom calculating initial fee amount
     * @param Mage_Sales_Model_Quote_Item $product
     */
    public function updateRecurringQuoteItem(Mage_Sales_Model_Quote_Item $quoteItem)
    {
        $product = $quoteItem->getProduct();
        if ($product->getIsRecurring()) {
            $recurringProfile = $product->getRecurringProfile();
            $initAmount = $recurringProfile['init_amount'];
            if(!is_null($initAmount)){
                $qty = $quoteItem->getQty();
                $totalInitAmount = $qty * $initAmount;

                if (isset($recurringProfile['init_amount']) &&
                    !empty($recurringProfile['init_amount']) &&
                    $recurringProfile['init_amount'] > 0
                ) {

                    $quoteItemData = $quoteItem->getData();
                    if(array_key_exists('xp_recurring_initial_fee',$quoteItemData)){
                        $quoteItem->setXpRecurringInitialFee($totalInitAmount);
                        $initialFeeTax = $this->calculateTaxForProductCustomPrice($product,$totalInitAmount);
                        if($initialFeeTax){
                            $quoteItem->setInitialfeeTaxAmount($initialFeeTax);
                        }

                        $quoteItem->save();
                    }
                }
            }
        }

    }

    public function updateAllRecurringQuoteItem()
    {
        $quote = Mage::getModel('checkout/cart')->getQuote();
        $quoteItems = $quote->getAllVisibleItems();
        foreach ($quoteItems as $quoteItem) {
            $this->updateRecurringQuoteItem($quoteItem);
        }

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
     * @param array $xpCardData
     * @return string
     */
    public function prepareCardDataString($xpCardData)
    {
        $xpCardDataStr = '';
        if (!empty($xpCardData)) {
            $last4 = (isset($xpCardData['last4'])) ? $xpCardData['last4'] : $xpCardData['last_4_cc_num'];

            $xpCardDataStr = $this->__('%s******%s',
                $xpCardData['first6'],
                $last4
            );
            if (!empty($xpCardData['expire_month']) && !empty($xpCardData['expire_year'])) {
                $xpCardDataStr .= $this->__(' ( %s/%s )', $xpCardData['expire_month'], $xpCardData['expire_year']);
            }
        }

        return $xpCardDataStr;
    }

}
