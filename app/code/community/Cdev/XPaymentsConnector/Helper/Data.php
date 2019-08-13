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
 * @author     Qualiteam Software info@qtmsoft.com
 * @category   Cdev
 * @package    Cdev_XPaymentsConnector
 * @copyright  (c) 2010-2016 Qualiteam software Ltd <info@x-cart.com>. All rights reserved
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

    const STATE_XPAYMENTS_PENDING_PAYMENT = 'xp_pending_payment';

    const XPAYMENTS_LOG_FILE = 'xpayments.log';
    const RECURRING_ORDER_TYPE = 'recurring';
    const SIMPLE_ORDER_TYPE = 'simple';

    // TODO: change both names in the database!!!!!!!!

    /**
     * Attribute name to store temporary X-Payments data in Quote model
     */
    const XPC_DATA = 'xp_card_data';

    /**
     * Attribute name to store checkout data in Quote model
     */
    const CHECKOUT_DATA = 'xp_callback_approve';

    /**
     * Placeholder for empty email (something which will pass X-Payments validation)
     */
    const EMPTY_USER_EMAIL = 'user@example.com';

    /**
     * Placeholder for not available cart data
     */
    const NOT_AVAILABLE = 'N/A';

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
     * Check if Idev OneStepCheckout module is enablled and activated
     *
     * @return bool
     */
    public function checkOscModuleEnabled()
    {
        $modules = Mage::getConfig()->getNode('modules')->children();
        $modules = (array)$modules;

        $result = false;

        if (isset($modules['Idev_OneStepCheckout'])) {

            $module = $modules['Idev_OneStepCheckout'];

            if ($module->active) {

                $result = (bool)Mage::getStoreConfig(
                    'onestepcheckout/general/rewrite_checkout_links',
                    Mage::app()->getStore()
                );
            }

        }

        return $result;
    }

    /**
     * Check if Firecheckout module is enablled and activated
     *
     * @return bool
     */
    public function checkFirecheckoutModuleEnabled()
    {
        $modules = Mage::getConfig()->getNode('modules')->children();
        $modules = (array)$modules;

        $result = false;

        if (isset($modules['TM_FireCheckout'])) {

            $module = $modules['TM_FireCheckout'];

            if ($module->active) {

                $result = (bool)Mage::getStoreConfig(
                    'firecheckout/general/enabled',
                    Mage::app()->getStore()
                );
            }

        }

        return $result;
    }


    /**
     * Get place to display iframe. Review or payment step of checkout
     *
     * @return string "payment" or "review"
     */
    public function getIframePlaceDisplay()
    {
        $place = Mage::getStoreConfig('payment/xpayments/placedisplay');

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
     * Check if iframe should be used or not
     * 
     * @return bool 
     */
    public function isUseIframe()
    {
        return $this->checkOscModuleEnabled()
            || $this->checkFirecheckoutModuleEnabled()
            || Mage::getStoreConfig('payment/xpayments/use_iframe');
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
        if ($this->isUseIframe()) {
            $xpaymentPrepareOrder = Mage::getSingleton('checkout/session')->getData('xpayment_prepare_order');
            $xpaymentPrepareOrder['place_display'] = $this->getIframePlaceDisplay();
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

    /**
     * @param $name
     * @param $block
     * @return string
     */
    public function getReviewButtonTemplate($name, $block)
    {
        $quote = Mage::getSingleton('checkout/session')->getQuote();
        $useIframe = $this->isUseIframe();
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

    /**
     * @return bool
     */
    public function isNeedToSaveUserCard()
    {
        $result = (bool)Mage::getSingleton('checkout/session')->getData('user_card_save');
        return $result;
    }

    public function userCardSaved(){
        Mage::getSingleton('checkout/session')->setData('user_card_save',false);
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
     * @param $currentPaymentCode
     * @return bool
     */
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

    /**
     * @return bool
     */
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
     * @param $name
     * @param $block
     * @return mixed
     */
    public function getCheckoutSuccessTemplate($name, $block)
    {
        if (Mage::helper('core')->isModuleEnabled('Vsourz_Ordersuccess')) {
            if ($blockObject = Mage::getSingleton('core/layout')->getBlock($block)) {
                return $blockObject->getTemplate();
            }
        } else {
            return $name;
        }
    }

    /**
     * Prepare state
     * 
     * @param array $data Address data
     *
     * @return string
     */
    protected function prepareState($data)
    {
        $state = self::NOT_AVAILABLE;

        if (!empty($data['region_id'])) {

            $region = Mage::getModel('directory/region')->load($data['region_id']);

            if (
                $region
                && $region->getCode()
            ) {
                $state = $region->getCode();
            }
        }

        return $state;
    }

    /**
     * Prepare street (Address lines 1 and 2)
     *
     * @param array $data Address data
     *
     * @return string
     */
    protected function prepareStreet($data)
    {
        $street = self::NOT_AVAILABLE;

        if (!empty($data['street'])) {

            $street = $data['street'];

            if (is_array($street)) {
                $street = array_filter($street);
                $street = implode("\n", $street);
            }
        }

        return $street;
    }

    /**
     * Prepare address for initial payment request
     *
     * @param Mage_Sales_Model_Quote $quote Quote
     * @param Mage_Customer_Model_Customer $customer Customer
     * @param $type Address type, Billing or Shipping
     *
     * @return array
     */
    protected function prepareAddress(Mage_Sales_Model_Quote $quote = null, Mage_Customer_Model_Customer $customer = null, $type = 'Billing')
    {
        $getAddress = 'get' . $type . 'Address';
        $getDefaultAddress = 'getDefault' . $type . 'Address';

        $customerAddress = $customerDefaultAddress = $quoteAddress = array();

        if ($quote) {

            $customer = $quote->getCustomer();

            if ($quote->$getAddress()) {
                $quoteAddress = $quote->$getAddress()->getData();
            }
        }

        if ($customer) {

            $customerAddress = $customer->getData();

            if ($customer->$getDefaultAddress()) {
                $customerDefaultAddress = $customer->$getDefaultAddress()->getData();
            }
        }

        $data = array_merge(
            array_filter($customerAddress),
            array_filter($customerDefaultAddress),
            array_filter($quoteAddress)
        );

        $result = array(
            'firstname' => !empty($data['firstname']) ? $data['firstname'] : self::NOT_AVAILABLE,
            'lastname'  => !empty($data['lastname']) ? $data['lastname'] : self::NOT_AVAILABLE,
            'address'   => $this->prepareStreet($data),
            'city'      => !empty($data['city']) ? $data['city'] : self::NOT_AVAILABLE,
            'state'     => $this->prepareState($data),
            'country'   => !empty($data['country_id']) ? $data['country_id'] : 'XX', // WA fix for MySQL 5.7 with strict mode
            'zipcode'   => !empty($data['postcode']) ? $data['postcode'] : self::NOT_AVAILABLE,
            'phone'     => !empty($data['telephone']) ? $data['telephone'] : '',
            'fax'       => '',
            'company'   => '',
            'email'     => !empty($data['email']) ? $data['email'] : self::EMPTY_USER_EMAIL,
        );

        return $result;
    }

    /**
     * Format price in 1234.56 format
     *
     * @param mixed $price
     *
     * @return string
     */
    protected function preparePrice($price)
    {
        return number_format($price, 2, '.', '');
    }

    /**
     * Prepare totals item: shipping, tax, discount
     *
     * @param array $totals Cart totals
     * @param string $key Totals item key
     *
     * @return string
     */
    protected function prepareTotalsItem($totals, $key)
    {
        $value = 0;

        if (
            isset($totals[$key])
            && is_object($totals[$key])
            && method_exists($totals[$key], 'getValue')
        ) {
            $value = abs($totals[$key]->getValue());
        }

        return $this->preparePrice($value);
    }


    /**
     * Prepare simple items for initial payment request
     *
     * @param Mage_Sales_Model_Quote $quote
     * @param array &$result
     *
     * @return void 
     */
    protected function prepareSimpleItems(Mage_Sales_Model_Quote $quote, &$result)
    {
        $quote->collectTotals();
        $totals = $quote->getTotals();

        $result['totalCost'] = $this->preparePrice($quote->getGrandTotal());
        $result['shippingCost'] = $this->prepareTotalsItem($totals, 'shipping');
        $result['taxCost'] = $this->prepareTotalsItem($totals, 'tax');
        $result['discount'] = $this->prepareTotalsItem($totals, 'discount');

        $cartItems = $quote->getAllVisibleItems();

        foreach ($cartItems as $item) {

            if ($item->getIsNominal()) {
                continue;
            }

            $productId = $item->getProductId();
            $product = Mage::getModel('catalog/product')->load($productId);

            $result['items'][] = array(
                'sku'      => $product->getData('sku'),
                'name'     => $product->getData('name'),
                'price'    => $this->preparePrice($product->getPrice()),
                'quantity' => intval($item->getQty()),
            );
        }
    }

    /**
     * Prepare recurring items for initial payment request
     *
     * @param Mage_Sales_Model_Quote $quote
     * @param array &$result
     *
     * @return void
     */
    protected function prepareRequringItems(Mage_Sales_Model_Quote $quote, &$result)
    {
        $issetRecurringProduct = $this->checkIssetRecurringOrder();

        $quoteItem = $issetRecurringProduct['quote_item'];
        $product = $quoteItem->getProduct();

        $item = $quote->getItemByProduct($product);

        $recurringProfile = $product->getRecurringProfile();

        $startDateParams = $this->checkStartDateDataByProduct($product, $item);
        $startDateParams = $startDateParams[$product->getId()];

        $shipping = $issetRecurringProduct['quote_item']->getData('shipping_amount');
        $discount = abs($issetRecurringProduct['quote_item']->getData('discount_amount'));

        $quantity = $quoteItem->getQty();

        if ($startDateParams['success']) {

            $minimalPayment = $startDateParams['minimal_payment_amount'];

            $tax = !empty($recurringProfile['init_amount'])
                ? $quoteItem->getData('initialfee_tax_amount')
                : 0;

            $totalCost = $minimalPayment + $tax + $shipping - $discount;

        } else {

            $minimalPayment = 0;

            $tax = $quoteItem->getData('initialfee_tax_amount') + $quoteItem->getData('tax_amount');

            $totalCost = $quoteItem->getData('nominal_row_total');
        }

        $recurringPrice = $product->getPrice();

        if (!empty($recurringProfile['init_amount'])) {
            $recurringPrice += $quoteItem->getXpRecurringInitialFee() / $quantity;
        }

        $price = $minimalPayment
            ? $minimalPayment / $quantity
            : $recurringPrice;

        $result['items'][] = array(
            'sku'      => $product->getData('sku'),
            'name'     => $product->getData('name'),
            'price'    => $this->preparePrice($price),
            'quantity' => intval($quantity),
        );

        $result['totalCost'] = $this->preparePrice($totalCost);
        $result['shippingCost'] = $this->preparePrice($shipping);
        $result['taxCost'] = $this->preparePrice($tax);
        $result['discount'] = $this->preparePrice($discount);
    }

    /**
     * Prepare items for initial payment request
     *
     * @param Mage_Sales_Model_Quote $quote
     * @param array &$result
     *
     * @return void 
     */
    protected function prepareItems(Mage_Sales_Model_Quote $quote, &$result)
    {
        $issetSimpleProducts = $this->checkIssetSimpleOrder();
        $issetRecurringProduct = $this->checkIssetRecurringOrder();

        if ($issetRecurringProduct['isset'] && !$issetSimpleProducts) {
            $this->prepareRequringItems($quote, $result);
        } else {
            $this->prepareSimpleItems($quote, $result);
        }
    }

    /**
     * Prepare cart for initial payment request
     *
     * @param Mage_Sales_Model_Quote $quote
     * @param string $refId Reference to the order
     *
     * @return array
     */
    public function prepareCart(Mage_Sales_Model_Quote $quote, $refId = false)
    {
        $customer = $quote->getCustomer();

        if (
            !$customer->getData('email')
            || !$customer->getData('entity_id')
        ) {
            $login = 'Anonymous customer (Quote ID #' . $quote->getId() . ')';
        } else {
            $login = $customer->getData('email') . ' (User ID #' . $customer->getData('entity_id') . ')';
        }

        if ($refId) {
            $description = 'Order #' . $refId;
        } else {
            $description = 'Quote #' . $quote->getId();
        }

        $result = array(
            'login'                => $login,
            'billingAddress'       => $this->prepareAddress($quote, null, 'Billing'),
            'shippingAddress'      => $this->prepareAddress($quote, null, 'Shipping'),    
            'items'                => array(),
            'currency'             => $quote->getData('quote_currency_code'),
            'shippingCost'         => 0.00,
            'taxCost'              => 0.00,
            'discount'             => 0.00,
            'totalCost'            => 0.00,
            'description'          => $description,
            'merchantEmail'        => Mage::getStoreConfig('trans_email/ident_sales/email'),
            'forceTransactionType' => '',
        );

        $this->prepareItems($quote, $result);

        return $result;
    }

    /**
     * Prepare cart for initial payment request
     *
     * Mage_Customer_Model_Customer $customer Customer
     *
     * @return array
     */
    public function prepareFakeCart(Mage_Customer_Model_Customer $customer)
    {
        $refId = 'authorization';

        $description = 'Authorization'; // TODO: add reference to customer?

        $price = $this->preparePrice(Mage::getStoreConfig('xpaymentsconnector/settings/xpay_minimum_payment_recurring_amount'));

        $result = array(
            'login'                => $customer->getData('email') . ' (User ID #' . $customer->getData('entity_id') . ')',
            'billingAddress'       => $this->prepareAddress(null, $customer, 'Billing'),
            'shippingAddress'      => $this->prepareAddress(null, $customer, 'Shipping'),
            'items'                => array(
                array(
                    'sku'      => 'CardSetup',
                    'name'     => 'CardSetup',
                    'price'    => $price,
                    'quantity' => '1',
                ),
            ),
            'currency'             => Mage::getModel('xpaymentsconnector/payment_cc')->getCurrency(), 
            'shippingCost'         => 0.00,
            'taxCost'              => 0.00,
            'discount'             => 0.00,
            'totalCost'            => $price,
            'description'          => $description,
            'merchantEmail'        => Mage::getStoreConfig('trans_email/ident_sales/email'),
            'forceTransactionType' => '',
        );

        return $result;
    }

    /**
     * Get callback URL for initial payment request 
     *
     * @param string $refId    Reference ID
     * @param string $entityId Quote ID or Cutomer ID
     * @param bool   $zeroAuth Is it zero auth request
     *
     * @return array
     */
    public function getCallbackUrl($refId, $entityId, $zeroAuth = false)
    {
        $params = array(
            '_secure' => true,
            '_nosid'  => true,
        );

        if ($zeroAuth) {

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
     * @param string $refId    Reference ID
     * @param string $entityId Quote ID or Cutomer ID
     * @param bool   $zeroAuth Is it zero auth request
     *
     * @return array
     */
    public function getReturnUrl($refId, $entityId, $zeroAuth = false)
    {
        $params = array(
            '_secure' => true,
            '_nosid'  => true,
        );

        if ($zeroAuth) {

            $params['customer_id'] = $entityId;

            $url = Mage::getUrl('xpaymentsconnector/customer/cardadd', $params);

        } else {

            $params['quote_id'] = $entityId;

            $url = Mage::getUrl('xpaymentsconnector/processing/return', $params);
        }

        return $url;
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
        $result = array();

        $key = $type . '_address_id';

        if (isset($data[$key])) {

            // Address data from the address book
            $address = Mage::getModel('customer/address')->load($data[$key]);
            $result += array_filter($address->getData());
        }

        if (isset($data[$type])) {

            // Addrress data from checkout
            $result += array_filter($data[$type]);
        }

        return $result;
    }

    /**
     * Save checkout data
     *
     * @param Mage_Sales_Model_Quote $quote
     * @param array $data Some checkout data
     *
     * @return void
     */
    public function saveCheckoutData($quote, $data)
    {
        $this->writeLog('Save checkout data for Quote #' . $quote->getEntityId(), $data);

        $data = serialize($data);
        $quote->setData(self::CHECKOUT_DATA, $data);
        $quote->save();
    }

    /**
     * Load data saved at checkou
     *
     * @param Mage_Sales_Model_Quote $quote
     * 
     * @return array
     */
    public function loadCheckoutData(Mage_Sales_Model_Quote $quote)
    {
        return unserialize($quote->getData(self::CHECKOUT_DATA));
    }

    /**
     * Save some temporary X-Payments data to Quote
     *
     * @param Mage_Sales_Model_Quote $quote
     * @param array $data Some data
     *
     * @return void
     */
    public function saveQuoteXpcData(Mage_Sales_Model_Quote $quote, $data = array())
    {
        $data = serialize($data);
        $quote->setData(self::XPC_DATA, $data);
        $quote->save();
    }

    /**
     * Clear temporary X-Payments data from Quote (just a wrapper)
     *
     * @param Mage_Sales_Model_Quote $quote
     *
     * @return void
     */
    public function clearQuoteXpcData(Mage_Sales_Model_Quote $quote)
    {
        return $this->saveQuoteXpcData($quote);
    }

    /**
     * Append some temporary X-Payments data to Quote
     *
     * @param Mage_Sales_Model_Quote $quote
     * @param array $appendData Some data to append
     *
     * @return void
     */
    public function appendQuoteXpcData(Mage_Sales_Model_Quote $quote, $appendData = array())
    {
        $data = $this->loadQuoteXpcData($quote);

        $data += $appendData;

        $this->saveQuoteXpcData($quote, $data);
    }

    /**
     * Load temporary X-Payments data from Quote 
     *
     * @param Mage_Sales_Model_Quote $quote
     *
     * @return array
     */
    public function loadQuoteXpcData(Mage_Sales_Model_Quote $quote)
    {
        $data = unserialize($quote->getData(self::XPC_DATA));

        if (!is_array($data)) {
            $data = array();
        }

        return $data;
    }

    /**
     * Get token from quote 
     *
     * @param Mage_Sales_Model_Quote $quote
     *
     * @return string or false 
     */
    public function getQuoteXpcDataToken(Mage_Sales_Model_Quote $quote)
    {
        $data = $this->loadQuoteXpcData($quote);

        return !empty($data['token'])
            ? $data['token']
            : false;
    }

    /**
     * Process data saved at checkout
     *
     * @param Mage_Sales_Model_Quote $quote
     *
     * @return void
     */
    protected function processCheckoutData(Mage_Sales_Model_Quote $quote)
    {
        // Grab data saved at checkout
        $data = $this->loadCheckoutData($quote);

        // Add billing address data from checkout
        $quote->getBillingAddress()->addData($this->getCheckoutAddressData($data, 'billing'));

        if (
            isset($data['billing']['use_for_shipping'])
            && (bool)$data['billing']['use_for_shipping']
        ) {

            // Overwrite shipping address data with billing one
            $data['shipping'] = $data['billing'];

            if (
                $this->checkOscModuleEnabled()
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
        $quote->getShippingAddress()->addData($this->getCheckoutAddressData($data, 'shipping'));

        if (!$this->checkOscModuleEnabled()) {

            // TODO: Understand what's wrong with it for OSC.

            // Save shipping method
            if (!empty($data['shipping_method'])) {
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
     * Prepare quote for customer registration and customer order submit
     *
     * @return Mage_Checkout_Model_Type_Onepage
     */
    protected function createNewCustomer(Mage_Sales_Model_Quote $quote)
    {
        $billing    = $quote->getBillingAddress();
        $shipping   = $quote->isVirtual() ? null : $quote->getShippingAddress();

        //$customer = Mage::getModel('customer/customer');
        $customer = $quote->getCustomer();
        /* @var $customer Mage_Customer_Model_Customer */
        $customerBilling = $billing->exportCustomerAddress();
        $customer->addAddress($customerBilling);
        $billing->setCustomerAddress($customerBilling);
        $customerBilling->setIsDefaultBilling(true);
        if ($shipping && !$shipping->getSameAsBilling()) {
            $customerShipping = $shipping->exportCustomerAddress();
            $customer->addAddress($customerShipping);
            $shipping->setCustomerAddress($customerShipping);
            $customerShipping->setIsDefaultShipping(true);
        } else {
            $customerBilling->setIsDefaultShipping(true);
        }

        Mage::helper('core')->copyFieldset('checkout_onepage_quote', 'to_customer', $quote, $customer);
        $customer->setPassword($customer->decryptPassword($quote->getPasswordHash()));
        $quote->setCustomer($customer)
            ->setCustomerId(true);

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
    public function isCreateNewCustomer(Mage_Sales_Model_Quote $quote, $includeLoginMethod = false)
    {
        $result = false;

        if ($this->checkOscModuleEnabled()) {

            // For One Step Checkout module
            $data = $this->loadCheckoutData($quote);

            $result = isset($data['create_account']) 
                && (bool)$data['create_account'];

        } elseif ($this->checkFirecheckoutModuleEnabled()) {

            // For Firecheckout module
            $data = $this->loadCheckoutData($quote);

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
     * @param Mage_Sales_Model_Quote $quote
     *
     * @return string
     */
    public function funcPlaceOrder(Mage_Sales_Model_Quote $quote)
    {
        $refId = false;

        try {

            // Process data saved at checkout
            $this->processCheckoutData($quote);

            if ($this->isCreateNewCustomer($quote)) {
            
                // Prepare data for customer who's registered at checkout
                $customer = $this->createNewCustomer($quote);
                $customer->save();

                $this->appendQuoteXpcData($quote, array('address_saved' => true));
            }

            // Set payment method (maybe not necessary. Just in case)
            $quote->collectTotals()->getPayment()->setMethod('xpayments');

            // Place order
            $service = Mage::getModel('sales/service_quote', $quote);
            $service->submitAll();

            $order = $service->getOrder();

            $quote->setIsActive(false)->save();

            $cardData = $quote->getData(self::XPC_DATA);
            $order->setData(self::XPC_DATA, $cardData);

            $cardData = unserialize($cardData);

            $order->setData('xpc_txnid', $cardData['txnId']);

            $order->save();

            $refId = $order->getIncrementId();

            $this->writeLog('Placed order #' . $refId, $cardData);

        } catch (Exception $e) {

            $this->writeLog('Unable to create order: ' . $e->getMessage(), $e->getTraceAsString());

            // Save error message in quote
            $this->appendQuoteXpcData(
                $quote,
                array(
                    'xpc_message' => $e->getMessage(),
                )
            );
        }

        return $refId;
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
            '_nosid' => true,
            '_secure' => true
        );

        return Mage::getUrl($path, $params);
    }

    /**
     * Get some JSON data for javascript at checkout
     * 
     * @return string 
     */
    public function getXpcJsonData()
    {
        // Display iframe on the review step (after payment step) or not
        $isDisplayOnReviewStep = 'review' == Mage::helper('xpaymentsconnector')->getIframePlaceDisplay();

        $xpOrigin = parse_url(Mage::getModel('xpaymentsconnector/payment_cc')->getConfig('xpay_url'));
        $xpOrigin = $xpOrigin['scheme'] . '://' . $xpOrigin['host'];

        $data = array(
            'url' => array(

                'redirectIframe'           => $this->getXpcCheckoutUrl('xpaymentsconnector/processing/redirectiframe'),

                'redirectIframeUnsetOrder' => $this->getXpcCheckoutUrl(
                    'xpaymentsconnector/processing/redirectiframe', 
                    array(
                        'unset_xp_prepare_order' => 1
                    )
                ),

                'setMethodRegister'        => $this->getXpcCheckoutUrl(
                    'xpaymentsconnector/processing/redirectiframe', 
                    array(
                        'unset_xp_prepare_order' => 1, 
                        'checkout_method' => 'register'
                    )
                ),

                'setMethodGuest'           => $this->getXpcCheckoutUrl(
                    'xpaymentsconnector/processing/redirectiframe',
                    array(
                        'unset_xp_prepare_order' => 1,
                        'checkout_method' => 'guest'
                    )
                ),

                'saveCheckoutData'         => $this->getXpcCheckoutUrl('xpaymentsconnector/processing/save_checkout_data'),

                'changeMethod'             => $this->getXpcCheckoutUrl('checkout/cart/'),
    
                'checkAgreements'          => $this->getXpcCheckoutUrl('xpaymentsconnector/processing/check_agreements'), 
            ),
            'xpOrigin'            => $xpOrigin,    
            'displayOnReviewStep' => $isDisplayOnReviewStep,
            'useIframe'           => $this->isUseIframe(),
            'isOneStepCheckout'   => $this->checkOscModuleEnabled(),
            'isFirecheckout'      => $this->checkFirecheckoutModuleEnabled(),   
            'height'              => 0,
        );

        return json_encode($data, JSON_FORCE_OBJECT);
    }

    /**
     * Clear init data and something about "unset prepared order"
     *
     * @return void 
     */
    public function resetInitData($quote = null)
    {
        $unsetParams = array('token');
        $this->unsetXpaymentPrepareOrder($unsetParams);

        if (!$quote) {
            // Get quote from session
            $quote = Mage::getSingleton('checkout/session')->getQuote();
        }

        $this->clearQuoteXpcData($quote);
    }

    /**
     * Find last order by X-Payments transaction ID
     *
     * @param string $txnId
     *
     * @return Mage_Sales_Model_Order
     */
    public function getOrderByTxnId($txnId)
    {
        return Mage::getModel('sales/order')->getCollection()
            ->addAttributeToSelect('*')
            ->addFieldToFilter('xpc_txnid', $txnId)
            ->getLastItem();
    }

    /**
     * Write log
     *
     * @param string $title Log title 
     * @param mixed  $data  Data to log
     *
     * @return void
     */
    public function writeLog($title, $data = '')
    {
        if (!is_string($data)) {
            $data = var_export($data, true);
        }

        $message = PHP_EOL . date('Y-m-d H:i:s') . PHP_EOL
            . $title . PHP_EOL
            . $data . PHP_EOL
            . Mage::helper('core/url')->getCurrentUrl() . PHP_EOL
            . '--------------------------' . PHP_EOL 
            . PHP_EOL; 

        Mage::log($message, null, self::XPAYMENTS_LOG_FILE, true);
    }
}
