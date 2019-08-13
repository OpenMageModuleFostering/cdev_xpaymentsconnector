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

    const STATE_XPAYMENT_PENDING_PAYMENT = "xp_pending_payment";
    const XPAYMENTS_LOG_FILE = "xpayments.log";




    /**
     * This function return 'IncrementId' for feature order.
     * Xpayment Prepare Order Mas(xpayment_prepare_order):
     * - prepare_order_id (int)
     * - xpayment_response
     * - token
     * return
     * @return bool or int
     */
    public function getOrderKey(){
        $xpaymentPrepareOrderData = Mage::getSingleton("checkout/session")->getData("xpayment_prepare_order");
        if($xpaymentPrepareOrderData && isset($xpaymentPrepareOrderData["prepare_order_id"])){
            return $xpaymentPrepareOrderData["prepare_order_id"];
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
        $xpaymentPrepareOrder = Mage::getSingleton("checkout/session")->getData("xpayment_prepare_order");
        if(!isset($xpaymentPrepareOrder["prepare_order_id"])){
            $isRecurringOrder = $this->checkIsRecurringOrder();
            if($isRecurringOrder){
                $useStartDateParam = $this->checkStartDateData();
                if ($useStartDateParam["success"]) {
                    $xpaymentPrepareOrder["prepare_order_id"] = "subscription";
                    Mage::getSingleton("checkout/session")->setData("xpayment_prepare_order",$xpaymentPrepareOrder);
                    return;
                }
            }
            $storeId = Mage::app()->getStore()->getStoreId();
            $prepareOrderId = Mage::getSingleton('eav/config')
                ->getEntityType('order')
                ->fetchNewIncrementId($storeId);

            $xpaymentPrepareOrder["prepare_order_id"] = $prepareOrderId;
            Mage::getSingleton("checkout/session")->setData("xpayment_prepare_order", $xpaymentPrepareOrder);

        }

    }

    /**
     * This function return saved X-Payments response data.
     * Xpayment Prepare Order Mas(xpayment_prepare_order):
     * - prepare_order_id (int)
     * - xpayment_response
     * - token
     * return
     * @return bool or int
     */
    public function getXpaymentResponse(){
        $xpaymentPrepareOrder = Mage::getSingleton("checkout/session")->getData("xpayment_prepare_order");
        if($xpaymentPrepareOrder && isset($xpaymentPrepareOrder["xpayment_response"])){
            return $xpaymentPrepareOrder["xpayment_response"];
        }
        return false;
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
        $xpaymentPrepareOrder = Mage::getSingleton("checkout/session")->getData("xpayment_prepare_order");
        if ($xpaymentPrepareOrder && isset($xpaymentPrepareOrder["token"]) && !empty($xpaymentPrepareOrder["token"])) {
            return $xpaymentPrepareOrder["token"];
        } else {
            $api = Mage::getModel('xpaymentsconnector/payment_cc');
            $result = $api->sendIframeHandshakeRequest();
            $xpaymentPrepareOrder["token"] = $result['response']['token'];
            Mage::getSingleton("checkout/session")->setData("xpayment_prepare_order", $xpaymentPrepareOrder);
            return $xpaymentPrepareOrder["token"];
        }

    }

    /**
     * @param array $unsetParams
     */
    public function unsetXpaymentPrepareOrder($unsetParams = array()){
        if(!empty($unsetParams)){
            $xpaymentPrepareOrder = Mage::getSingleton("checkout/session")->getData("xpayment_prepare_order");
            foreach($unsetParams as $param){
                if( is_array($xpaymentPrepareOrder) && isset($xpaymentPrepareOrder[$param])){
                    unset($xpaymentPrepareOrder[$param]);
                }
            }
            Mage::getSingleton("checkout/session")->setData("xpayment_prepare_order",$xpaymentPrepareOrder);
            return;
        }
        Mage::getSingleton("checkout/session")->unsetData("xpayment_prepare_order");
    }

    /**
     * Save X-Payments response data after card data send.
     * @param array $responseData
     */
    public function savePaymentResponse($responseData = array())
    {
        $xpaymentPrepareOrder = Mage::getSingleton("checkout/session")->getData("xpayment_prepare_order");
        $xpaymentPrepareOrder["xpayment_response"] = $responseData;
        Mage::getSingleton("checkout/session")->setData("xpayment_prepare_order", $xpaymentPrepareOrder);
    }

    public function getReviewButtonTemplate($name, $block)
    {
        $quote = Mage::getSingleton('checkout/session')->getQuote();
        $useIframe =   Mage::getStoreConfig('payment/xpayments/use_iframe');
        if ($quote) {
            $payment = $quote->getPayment();
            if ($payment && $payment->getMethod() == "xpayments" && $useIframe) {
                return $name;
            }
        }

        if ($blockObject = Mage::getSingleton('core/layout')->getBlock($block)) {
            return $blockObject->getTemplate();
        }

        return '';
    }

    public function isNeedToSaveUserCard(){
        $result  = (bool) Mage::getSingleton("checkout/session")->getData("user_card_save");
        return $result;
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

    public function isXpaymentsMethod($currentPaymentCode){
        $xpaymentPaymentCode = Mage::getModel("xpaymentsconnector/payment_cc")->getCode();
        $saveCardsPaymentCode = Mage::getModel("xpaymentsconnector/payment_savedcards")->getCode();
        $prepaidpayments = Mage::getModel("xpaymentsconnector/payment_prepaidpayments")->getCode();
        $usePaymetCodes = array($xpaymentPaymentCode,$saveCardsPaymentCode,$prepaidpayments);
        if (in_array($currentPaymentCode, $usePaymetCodes)){
            return true;
        }else{
            return false;
        }
    }

    /**
     * @param Mage_Payment_Model_Recurring_Profile $recurringProfile
     * @param bool $isFirstRecurringOrder
     * @return int
     */
    public function createOrder(Mage_Payment_Model_Recurring_Profile $recurringProfile,$isFirstRecurringOrder = false){

            $orderItemInfo = $recurringProfile->getData("order_item_info");

            if(!is_array($orderItemInfo)){
                $orderItemInfo = unserialize($orderItemInfo);
            }
            $initialFeeAmount = $orderItemInfo["recurring_initial_fee"];
            $orderSubtotal = ($isFirstRecurringOrder) ? $orderItemInfo["row_total"]+$initialFeeAmount : $orderItemInfo["row_total"];

            //check is set related orders
            $useDiscount = current($recurringProfile->getChildOrderIds()) > 0;
            $discountAmount = ($useDiscount) ? 0 : $orderItemInfo["discount_amount"];

            $taxAmount = $recurringProfile->getData("tax_amount");
            $shippingAmount = $recurringProfile->getData("shipping_amount");

            $productItemInfo = new Varien_Object;
            $productItemInfo->setDiscountAmount($discountAmount);
            $productItemInfo->setPaymentType(Mage_Sales_Model_Recurring_Profile::PAYMENT_TYPE_REGULAR);
            $productItemInfo->setTaxAmount($taxAmount);
            $productItemInfo->setShippingAmount($shippingAmount);
            $productItemInfo->setPrice($orderSubtotal);

            Mage::dispatchEvent('before_recurring_profile_order_create', array('profile' => $recurringProfile,'product_item_info'=>$productItemInfo));
            $order = $recurringProfile->createOrder($productItemInfo);
            if($isFirstRecurringOrder){
                if($orderKey = $this->getOrderKey()){
                    $order->setIncrementId($orderKey);
                }
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
    public function getCurrentBillingPeriodTimeStamp(Mage_Payment_Model_Recurring_Profile $recurringProfile){
        $periodUnit  = $recurringProfile->getData("period_unit"); //day
        $periodFrequency = $recurringProfile->getData("period_frequency");
        //$masPeriodUnits = $recurringProfile->getAllPeriodUnits();
        $billingTimeStamp = 0;
       switch ($periodUnit){
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
               $startDateTime  = strtotime($recurringProfile->getStartDatetime());
               $lastSuccessTransactionDate = strtotime($recurringProfile->getXpSuccessTransactionDate());
               $lastActionDate = ($startDateTime > $lastSuccessTransactionDate) ? $startDateTime : $lastSuccessTransactionDate;
               $nextMonth = mktime(date("H",$lastActionDate), date("i",$lastActionDate) ,date("s",$lastActionDate) , date("m",$lastActionDate)+1, date("d",$lastActionDate), date("Y",$lastActionDate));
               $billingTimeStamp = $nextMonth - $lastActionDate;

               break;
           case Mage_Payment_Model_Recurring_Profile::PERIOD_UNIT_YEAR;
               $startDateTime  = strtotime($recurringProfile->getStartDatetime());
               $lastSuccessTransactionDate = strtotime($recurringProfile->getXpSuccessTransactionDate());
               $lastActionDate = ($startDateTime > $lastSuccessTransactionDate) ? $startDateTime : $lastSuccessTransactionDate;
               $nextYear = mktime(date("H",$lastActionDate), date("i",$lastActionDate) ,date("s",$lastActionDate) , date("m",$lastActionDate), date("d",$lastActionDate), date("Y",$lastActionDate)+1);
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
    public function getProfileOrderCardData(Mage_Payment_Model_Recurring_Profile $recurringProfile){
        $txnId = $recurringProfile->getReferenceId();
        $cardData = Mage::getModel("xpaymentsconnector/usercards")->load($txnId,"txnid");
     return $cardData;
    }

    /**
     * @param Mage_Payment_Model_Recurring_Profile $recurringProfile
     * @param bool $result
     * @param timestamp $newTransactionDate
     */
    public  function updateCurrentBillingPeriodTimeStamp(Mage_Payment_Model_Recurring_Profile $recurringProfile, bool $result,$newTransactionDate = null){
        if(!$result){
            $currentCycleFailureCount = $recurringProfile->getXpCycleFailureCount();
            //add failed attempt
            $currentCycleFailureCount++;
            $recurringProfile->setXpCycleFailureCount($currentCycleFailureCount);
            $maxPaymentFailure = $recurringProfile->getSuspensionThreshold();
            if($currentCycleFailureCount > $maxPaymentFailure){
                $recurringProfile->cancel();
            }
        }else{
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

    public function checkIsRecurringOrder(){
        $checkoutSession = Mage::getSingleton('checkout/session');
        $quoteItem = current($checkoutSession->getQuote()->getAllItems());
        if($quoteItem){
            $isRecurringOreder = (bool) $quoteItem->getProduct()->getIsRecurring();
            return $isRecurringOreder;
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
        $orderItemInfo = $recurringProfile->getData("order_item_info");
        $useIframe = Mage::getStoreConfig('payment/xpayments/use_iframe');
        $infoBuyRequest = unserialize($orderItemInfo["info_buyRequest"]);
        $startDateTime = $infoBuyRequest["recurring_profile_start_datetime"];

        if (!empty($startDateTime)) {
            $dateTimeStamp = strtotime($startDateTime);
            $zendDate = new Zend_Date($dateTimeStamp);
            $currentZendDate = new Zend_Date(time());

            if ($zendDate->getTimestamp() > $currentZendDate->getTimestamp()) {
                //set start date time
                $recurringProfile->setStartDatetime($zendDate->toString(Varien_Date::DATETIME_INTERNAL_FORMAT));

                $initialFeeAmount = $orderItemInfo["recurring_initial_fee"];
                if ($initialFeeAmount) {
                    $grandTotal = $initialFeeAmount;
                } else {
                    $grandTotal = floatval(Mage::getStoreConfig("xpaymentsconnector/settings/xpay_minimum_payment_recurring_amount"));
                }


                $paymentMethodCode = $recurringProfile->getData("method_code");

                $cardData = NULL;
                switch ($paymentMethodCode) {
                    case(Mage::getModel("xpaymentsconnector/payment_savedcards")->getCode()):

                        $paymentCardNumber = $recurringProfile->getQuote()->getPayment()->getData("xp_payment_card");

                        $cardData = Mage::getModel("xpaymentsconnector/usercards")->load($paymentCardNumber);
                        $cardData = $cardData->getData();

                        $response = Mage::getModel("xpaymentsconnector/payment_cc")->sendAgainTransactionRequest(NULL, NULL, $grandTotal, $cardData);


                        $recurringProfile->setReferenceId($cardData["txnId"]);
                        if ($response["success"]) {
                            $recurringProfile->activate();
                        } else {
                            Mage::getSingleton("checkout/session")->addError($response["error_message"]);
                            $recurringProfile->cancel();
                        }
                        return true;

                        break;
                    case(Mage::getModel("xpaymentsconnector/payment_cc")->getCode()):
                        if ($useIframe) {
                            $cardData = $this->getXpaymentResponse();
                            $recurringProfile->setReferenceId($cardData["txnId"]);
                            //check transaction state
                            list($status, $response) = Mage::getModel("xpaymentsconnector/payment_cc")->requestPaymentInfo($cardData["txnId"]);

                            if (
                                $status
                                && in_array($response['status'], array(Cdev_XPaymentsConnector_Model_Payment_Cc::AUTH_STATUS, Cdev_XPaymentsConnector_Model_Payment_Cc::CHARGED_STATUS))
                            ) {
                                Mage::getSingleton("checkout/session")->setData("user_card_save",true);
                                Mage::getModel("xpaymentsconnector/payment_cc")->saveUserCard($cardData, Cdev_XPaymentsConnector_Model_Usercards::RECURRING_CARD);
                                $recurringProfile->activate();
                            } else {
                                $this->addRecurringTransactionError($response);
                                $recurringProfile->cancel();
                            }
                            return true;

                        }else{
                            $cardData = $this->getXpaymentResponse();

                            //check transaction state
                            list($status, $response) = Mage::getModel("xpaymentsconnector/payment_cc")->requestPaymentInfo($cardData["txnId"]);
                            if (
                                $status
                                && in_array($response['status'], array(Cdev_XPaymentsConnector_Model_Payment_Cc::AUTH_STATUS, Cdev_XPaymentsConnector_Model_Payment_Cc::CHARGED_STATUS))
                            ) {
                                // save user card
                                Mage::getSingleton("checkout/session")->setData("user_card_save",true);
                                Mage::getModel("xpaymentsconnector/payment_cc")->saveUserCard($cardData,$usageType = Cdev_XPaymentsConnector_Model_Usercards::RECURRING_CARD);

                                $recurringProfile->setReferenceId($cardData["txnId"]);
                                $recurringProfile->activate();
                                $recurringProfile->save();
                                return true;
                            }else{
                                $recurringProfile->cancel();
                            }
                        }
                        break;
                }

            }
        }
        return false;
    }

    public function checkStartDateData(){
        $result = array();
        $quoteItem = current(Mage::getSingleton("checkout/session")->getQuote()->getAllItems());
        $productAdditionalInfo = unserialize($quoteItem->getProduct()->getCustomOption('info_buyRequest')->getValue());
        $dateTimeStamp = strtotime($productAdditionalInfo["recurring_profile_start_datetime"]);
        if ($dateTimeStamp) {
            $userSetTime = new Zend_Date($productAdditionalInfo["recurring_profile_start_datetime"]);
            $currentZendDate = new Zend_Date(time());
            if ($userSetTime->getTimestamp() > $currentZendDate->getTimestamp()) {
                $result["success"] = true;
                $recurringProfileData = $quoteItem->getProduct()->getData("recurring_profile");
                $initAmount = $recurringProfileData["init_amount"];
                $defaultMinimumPayment = floatval(Mage::getStoreConfig("xpaymentsconnector/settings/xpay_minimum_payment_recurring_amount"));
                $minimumPaymentAmount = ($initAmount) ? $initAmount : $defaultMinimumPayment;
                $result["minimal_payment_amount"] = $minimumPaymentAmount;
                return $result;
            } else {
                $result["success"] = false;
                return $result;
            }
        } else {
            $result["success"] = false;
            return $result;
        }

    }

    public function getRecurringProfileState(){
        $maxPaymentFailureMessage = $this->__('This profile has run out of maximal limit of unsuccessful payment attempts.');

        if (Mage::app()->getStore()->isAdmin()) {
            Mage::getSingleton('adminhtml/session')->addNotice($maxPaymentFailureMessage);
        }else{
            Mage::getSingleton('core/session')->addNotice($maxPaymentFailureMessage);
        }
    }

    public function addRecurringTransactionError($response = array()){
       $this->unsetXpaymentPrepareOrder();
       if(!empty($response)){
           if (!empty($response["error_message"])) {
               $errorMessage = $this->__('%s. The subscription has been canceled.', $response["error_message"]);
               Mage::getSingleton("checkout/session")->addError($errorMessage);
           } else {
               $transactionStatusLabel = Mage::getModel("xpaymentsconnector/payment_cc")->getTransactionStatusLabels();
               $errorMessage = $this->__("Transaction status is '%s'. The subscription has been canceled.", $transactionStatusLabel[$response['status']]);
               Mage::getSingleton("checkout/session")->addError($errorMessage);
           }
       }else{
           $errorMessage = $this->__('The subscription has been canceled.');
           Mage::getSingleton("checkout/session")->addError($errorMessage);
       }
    }

    public function setRecurringProductDiscount(){
        $quote = Mage::getSingleton('checkout/session')->getQuote();
        $items = $quote->getAllVisibleItems();
        foreach($items as $item){
            $discount = $item->getDiscountAmount();
            $profile = $item->getProduct()->getRecurringProfile();
            $profile["discount_amount"] = $discount;
            $item->getProduct()->setRecurringProfile($profile)->save();
        }
    }

}
