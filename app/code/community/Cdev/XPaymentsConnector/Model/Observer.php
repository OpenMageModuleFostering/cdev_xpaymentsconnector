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
 * Class Cdev_XPaymentsConnector_Model_Observer
 */

class Cdev_XPaymentsConnector_Model_Observer extends Mage_CatalogInventory_Model_Observer
{

    public function preDispatchCheckout($observer)
    {

        Mage::getSingleton("checkout/session")->unsetData("user_card_save");

        //unset x-payment form place settings
        $unsetParams = array("place_display");
        Mage::helper("xpaymentsconnector")->unsetXpaymentPrepareOrder($unsetParams);

        //set recurring product discount
        $isRecurrnigProduct = Mage::helper("xpaymentsconnector")->checkIsRecurringOrder();
        if ($isRecurrnigProduct) {
            Mage::helper("xpaymentsconnector")->setRecurringProductDiscount();
            $useStartDateParam = Mage::helper("xpaymentsconnector")->checkStartDateData();
            if ($useStartDateParam["success"]) {
                $currency_symbol = Mage::app()->getLocale()->currency(Mage::app()->getStore()->getCurrentCurrencyCode())->getSymbol();
                $noticeMessage = Mage::helper("xpaymentsconnector")
                    ->__("To create a subscription that uses an X-Payments method, your card  will be charged a minimal payment - %s%s",
                        $useStartDateParam["minimal_payment_amount"], $currency_symbol);
                Mage::getSingleton("core/session")->addNotice($noticeMessage);
            }
        }

    }

    public function paymentMethodIsActive($observer)
    {
        $event = $observer->getEvent();
        $method = $event->getMethodInstance();
        $result = $event->getResult();
        $saveCardsPaymentCode = Mage::getModel("xpaymentsconnector/payment_savedcards")->getCode();
        $prepaidpayments = Mage::getModel("xpaymentsconnector/payment_prepaidpayments")->getCode();

        if (($method->getCode() == $saveCardsPaymentCode) || ($method->getCode() == $prepaidpayments)) {
            $quote = $event->getQuote();
            if ($quote) {
                $customerId = $quote->getData("customer_id");
                $isBalanceCard = Cdev_XPaymentsConnector_Model_Usercards::SIMPLE_CARD;
                if ($method->getCode() == $prepaidpayments) {
                    $isBalanceCard = Cdev_XPaymentsConnector_Model_Usercards::BALANCE_CARD;
                }
                if ($customerId) {
                    $cardsCount = Mage::getModel("xpaymentsconnector/usercards")
                        ->getCollection()
                        ->addFieldToFilter('user_id', $customerId)
                        ->addFieldToFilter('usage_type', $isBalanceCard)
                        ->count();
                    if ($cardsCount == 0) {
                        $result->isAvailable = false;
                    }
                } else {
                    $result->isAvailable = false;
                }
            }
        }
    }


    /**
     * Dispatch: checkout_type_onepage_save_order_after
     * @param $observer
     */

    public function updateOrder($observer)
    {
        $event = $observer->getEvent();
        $order = $event->getOrder();
        $refId = Mage::helper("xpaymentsconnector")->getOrderKey();
        $paymentMethod = $order->getPayment()->getMethodInstance()->getCode();
        $xpaymentPaymentCode = Mage::getModel("xpaymentsconnector/payment_cc")->getCode();
        $saveCardsPaymentCode = Mage::getModel("xpaymentsconnector/payment_savedcards")->getCode();

        if ($paymentMethod == $xpaymentPaymentCode) {
            $order->setCanSendNewEmailFlag(false);
            $useIframe = Mage::getStoreConfig('payment/xpayments/use_iframe');

            $request = Mage::helper("xpaymentsconnector")->getXpaymentResponse();


            if ($useIframe) {
                /*update order table*/
                $order->setData('xpc_txnid', $request['txnId']);
                $order->setData("xp_card_data", serialize($request));
                $order->save();

                $result = Mage::getModel("xpaymentsconnector/payment_cc")->updateOrderByXpaymentResponse($order->getId(), $request['txnId']);
                if ($result["success"]) {
                    /* save credit card to user profile */
                    $response = Mage::helper("xpaymentsconnector")->getXpaymentResponse();
                    Mage::getModel("xpaymentsconnector/payment_cc")->saveUserCard($response);
                    /* end (save payment card) */
                } else {
                    Mage::getSingleton("checkout/session")->addError($result["error_message"]);
                }
            }

        } elseif ($paymentMethod == $saveCardsPaymentCode) {
            $order->setCanSendNewEmailFlag(false);
            $response = Mage::getModel("xpaymentsconnector/payment_cc")->sendAgainTransactionRequest($order->getId());
            if ($response["success"]) {
                $result = Mage::getModel("xpaymentsconnector/payment_cc")->updateOrderByXpaymentResponse($order->getId(), $response["response"]['transaction_id']);
                if (!$result["success"]) {
                    Mage::getSingleton("checkout/session")->addError($result["error_message"]);
                }
            } else {
                Mage::getSingleton("checkout/session")->addError($response["error_message"]);
            }
        }


    }


    /**
     * @param Varien_Event_Observer $observer
     */
    public function orderSuccessAction($observer)
    {
        Mage::helper("xpaymentsconnector")->unsetXpaymentPrepareOrder();
    }


    /**
     * @param Varien_Event_Observer $observer
     */
    public function postdispatchAdminhtmlSalesOrderCreateSave($observer)
    {
        $quote = Mage::getSingleton('adminhtml/session_quote')->getQuote();
        $incrementId = $quote->getData("reserved_order_id");
        $order = Mage::getModel('sales/order')->load($incrementId, 'increment_id');
        $paymentMethod = $order->getPayment()->getMethodInstance()->getCode();
        $admSession = Mage::getSingleton('adminhtml/session');
        $saveCardsPaymentCode = Mage::getModel("xpaymentsconnector/payment_savedcards")->getCode();
        $prepaidpayments = Mage::getModel("xpaymentsconnector/payment_prepaidpayments")->getCode();

        if ($paymentMethod == $saveCardsPaymentCode) {
            $orderId = $order->getId();
            $grandTotal = $quote->getData("grand_total");
            $this->adminhtmlSendSaveCardsPaymentTransaction($orderId,$grandTotal);
        } elseif ($paymentMethod == $prepaidpayments) {
            $xpPrepaidPaymentsCard = $admSession->getData("xp_prepaid_payments");
            $currentUserCard = Mage::getModel("xpaymentsconnector/usercards")->load($xpPrepaidPaymentsCard);
            $order->setData("xpc_txnid", $currentUserCard->getData("txnId"));
            $order->getPayment()->setTransactionId($currentUserCard->getData("txnId"));
            $order->getPayment()->setLastTransId($currentUserCard->getData("txnId"));
            $order->setData("xp_card_data", serialize($currentUserCard->getData()));
            $order->setState(
                Mage_Sales_Model_Order::STATE_PROCESSING,
                (bool)Mage_Sales_Model_Order::STATE_PROCESSING,
                Mage::helper('xpaymentsconnector')->__('Customer has been redirected to X-Payments.')
            );
            $order->save();
        }
        $admSession->unsetData("xp_payment_card");
        $admSession->unsetData("xp_prepaid_payments");

    }


    /**
     * Send transaction to X-Payment for 'order edit' event
     * @param Varien_Event_Observer $observer
     */
    public function postdispatchAdminhtmlSalesOrderEditSave($observer)
    {
        $adminhtmlSessionQuote = Mage::getSingleton('adminhtml/session_quote');
        $parentOrder = $adminhtmlSessionQuote->getOrder();
        $incrementId = $parentOrder->getRelationChildRealId();

        if($incrementId){
            $order = Mage::getModel('sales/order')->load($incrementId, 'increment_id');
            $paymentMethod = $order->getPayment()->getMethodInstance()->getCode();
            $saveCardsPaymentCode = Mage::getModel('xpaymentsconnector/payment_savedcards')->getCode();

            if ($paymentMethod == $saveCardsPaymentCode && $order) {
                $orderId = $order->getId();
                $grandTotal = $order->getGrandTotal();
                $parentOrderGrandTotal = $parentOrder->getGrandTotal();

                if($grandTotal > $parentOrderGrandTotal){
                    $checkOrderAmount = false;
                    $recalculcateGrandTotal = $grandTotal-$parentOrderGrandTotal;
                    $this->adminhtmlSendSaveCardsPaymentTransaction($orderId,$recalculcateGrandTotal,$checkOrderAmount);
                }
            }
        }
    }

    /**
     * Send transaction from the admin panel
     * @param int $orderId
     * @param float $grandTotal
     * @param bool $checkOrderAmount
     */
    protected function adminhtmlSendSaveCardsPaymentTransaction($orderId,$grandTotal,$checkOrderAmount = true)
    {
        $admSession = Mage::getSingleton('adminhtml/session');
        $xpCreditCards = $admSession->getData("xp_payment_card");

        $response = Mage::getModel("xpaymentsconnector/payment_cc")->sendAgainTransactionRequest($orderId, $xpCreditCards, $grandTotal);
        if ($response["success"]) {
            Mage::getModel("xpaymentsconnector/payment_cc")->updateOrderByXpaymentResponse($orderId, $response["response"]['transaction_id'],$checkOrderAmount);
        } else {
            Mage::getSingleton("adminhtml/session")->addError($response["error_message"]);
        }

    }

    public function predispatchAdminhtmlSalesOrderCreateSave($observer)
    {
        $quote = Mage::getSingleton('adminhtml/session_quote')->getQuote();
        $paymentMethod = $quote->getPayment()->getMethodInstance()->getCode();
        $prepaidpayments = Mage::getModel("xpaymentsconnector/payment_prepaidpayments")->getCode();
        if ($paymentMethod == $prepaidpayments) {
            $admSession = Mage::getSingleton('adminhtml/session');
            $xpPrepaidPaymentsCard = $admSession->getData("xp_prepaid_payments");
            $currentUserCard = Mage::getModel("xpaymentsconnector/usercards")->load($xpPrepaidPaymentsCard);
            $grandTotal = $quote->getData("grand_total");
            $cardAmount = $currentUserCard->getAmount();
            if ($cardAmount < $grandTotal) {
                $errorMessage = Mage::helper("xpaymentsconnector")
                    ->__("You can't make an order using card (**%s) worth over %s",
                        $currentUserCard->getData("last_4_cc_num"),
                        Mage::helper('core')->currency($cardAmount));
                $admSession->addError($errorMessage);
                /*redirect to last page*/
                Mage::app()->getResponse()->setRedirect($_SERVER['HTTP_REFERER']);
                Mage::app()->getResponse()->sendResponse();
                exit;
            } else {
                $cardBalance = $cardAmount - $grandTotal;
                if ($cardBalance == 0) {
                    $currentUserCard->delete();
                } else {
                    $currentUserCard->setAmount($cardBalance)->save();
                }

            }
        }
    }

    public function adminhtmlSavePaymentCard()
    {
        $payment = Mage::app()->getRequest()->getPost("payment");
        $saveCardsPaymentCode = Mage::getModel("xpaymentsconnector/payment_savedcards")->getCode();
        $prepaidpayments = Mage::getModel("xpaymentsconnector/payment_prepaidpayments")->getCode();

        if ($payment) {
            if ($payment["method"] == $saveCardsPaymentCode) {
                if ($payment["xp_payment_card"]) {
                    $admSession = Mage::getSingleton('adminhtml/session');
                    $admSession->setData("xp_payment_card", $payment["xp_payment_card"]);
                }
            } elseif ($payment["method"] == $prepaidpayments) {
                if ($payment["xp_prepaid_payments"]) {
                    $admSession = Mage::getSingleton('adminhtml/session');
                    $admSession->setData("xp_prepaid_payments", $payment["xp_prepaid_payments"]);
                }
            }
        }
    }

    public function unsetXpaymentSelectedCard()
    {
        $admSession = Mage::getSingleton('adminhtml/session');
        $admSession->unsetData("xp_payment_card");
        $admSession->unsetData("xp_prepaid_payments");
    }

    public function orderInvoiceSaveBefore($observer)
    {
        $invoice = $observer->getEvent()->getInvoice();
        $order = $invoice->getOrder();
        $paymentCode = $order->getPayment()->getMethodInstance()->getCode();
        if (Mage::helper("xpaymentsconnector")->isXpaymentsMethod($paymentCode)) {
            $txnid = $order->getData("xpc_txnid");
            $invoice->setTransactionId($txnid);
        }
    }

    public function invoiceVoid($observer)
    {
        $invoice = $observer->getInvoice();
        $order = $invoice->getOrder();
        $paymentCode = $order->getPayment()->getMethodInstance()->getCode();
        if (Mage::helper("xpaymentsconnector")->isXpaymentsMethod($paymentCode)) {
            $data = array(
                'txnId' => $order->getData("xpc_txnid"),
                'amount' => number_format($invoice->getGrandTotal(), 2, '.', ''),
            );
            Mage::getModel("xpaymentsconnector/payment_cc")->authorizedTransactionRequest('void', $data);
        }
    }

    public function createOrdersByCustomerSubscriptions($observer)
    {
        $xpaymentsHelper = Mage::helper("xpaymentsconnector");
        $recurringProfileList = Mage::getModel('sales/recurring_profile')
            ->getCollection()
            ->addFieldToFilter("state", Mage_Sales_Model_Recurring_Profile::STATE_ACTIVE);
        if ($recurringProfileList->getSize() > 0) {
            foreach ($recurringProfileList as $profile) {
                $startDateTime = strtotime($profile->getStartDatetime());
                $lastSuccessTransactionDate = strtotime($profile->getXpSuccessTransactionDate());
                $lastActionDate = ($startDateTime > $lastSuccessTransactionDate) ? $startDateTime : $lastSuccessTransactionDate;

                $profilePeriodValue = $xpaymentsHelper->getCurrentBillingPeriodTimeStamp($profile);
                $newTransactionDate = $lastActionDate + $profilePeriodValue;

                $currentDateObj = new Zend_Date(time());
                $currentDateStamp = $currentDateObj->getTimestamp();

                //var_dump("current = ".date("Y-m-d H:m:s",$currentDateStamp),"start = ".date("Y-m-d H:m:s",$startDateTime),"last = ".date("Y-m-d H:m:s",$lastActionDate),"new = ".date("Y-m-d H:m:s",$newTransactionDate),"profile_id = ".$profile->getProfileId());die;

                $timePassed = $currentDateStamp - $lastActionDate;

                if ($timePassed >= $profilePeriodValue) {
                    // check by count of success transaction
                    $currentSuccessCycles = $profile->getXpCountSuccessTransaction();
                    $periodMaxCycles = $profile->getPeriodMaxCycles();

                    if ($periodMaxCycles >= $currentSuccessCycles) {
                        $orderItemInfo = $profile->getData("order_item_info");
                        if (!is_array($orderItemInfo)) {
                            $orderItemInfo = unserialize($orderItemInfo);
                        }
                        $grandTotal = $orderItemInfo["nominal_row_total"];
                        $initialFeeAmount = $orderItemInfo["recurring_initial_fee"];

                        // add discount for grand total amount
                        $useDiscount = current($profile->getChildOrderIds()) > 0;
                        $discountAmount = ($useDiscount) ? $orderItemInfo["discount_amount"] : 0;

                        $orderId = $xpaymentsHelper->createOrder($profile);
                        $cardData = $xpaymentsHelper->getProfileOrderCardData($profile);

                        $response = Mage::getModel("xpaymentsconnector/payment_cc")->sendAgainTransactionRequest($orderId, NULL, $grandTotal - $initialFeeAmount + $discountAmount, $cardData);

                        if ($response["success"]) {
                            $result = Mage::getModel("xpaymentsconnector/payment_cc")->updateOrderByXpaymentResponse($orderId, $response["response"]['transaction_id']);
                            $xpaymentsHelper->updateCurrentBillingPeriodTimeStamp($profile, $result["success"], $newTransactionDate);
                            if (!$result["success"]) {
                                Mage::log($result["error_message"], null, $xpaymentsHelper::XPAYMENTS_LOG_FILE, true);
                            }

                        } else {
                            $xpaymentsHelper->updateCurrentBillingPeriodTimeStamp($profile, $response["success"], $newTransactionDate);
                            Mage::log($response["error_message"], null, $xpaymentsHelper::XPAYMENTS_LOG_FILE, true);
                        }

                    } else {
                        // Subscription is completed
                        $profile->cancel();

                    }
                }

            }

        }


    }

    /**
     * Add redirect for buying recurring product by xpayments method(without iframe)
     * @param $observer
     */
    public function addRedirectForXpaymentMethod($observer)
    {
        $profiles = $observer->getData("recurring_profiles");
        if (!empty($profiles)) {
            $profile = current($profiles);
            $currentPaymentMethodCode = $profile->getData("method_code");
            $xpaymentPaymentCode = Mage::getModel("xpaymentsconnector/payment_cc")->getCode();
            $useIframe = Mage::getStoreConfig('payment/xpayments/use_iframe');
            if (($currentPaymentMethodCode == $xpaymentPaymentCode) && !$useIframe) {
                $redirectUrl = Mage::getUrl('xpaymentsconnector/processing/redirect', array('_secure' => true));
                Mage::getSingleton("checkout/session")->setRedirectUrl($redirectUrl);
            }

        }

    }

    /**
     * Set discount for recurring product(for ajax cart item quantity update)
     * Remove X-Payments token
     * @param $observer
     */
    public function updateCartItem($observer)
    {
        $unsetParams = array("token");
        Mage::helper("xpaymentsconnector")->unsetXpaymentPrepareOrder($unsetParams);

        //set recurring product discount
        Mage::helper("xpaymentsconnector")->setRecurringProductDiscount();
    }

    /**
     * Remove X-Payments token
     * @param $observer
     */
    public function checkoutCartAdd($observer)
    {
        $unsetParams = array("token");
        Mage::helper("xpaymentsconnector")->unsetXpaymentPrepareOrder($unsetParams);
    }

    /**
     * Remove X-Payments token and prepare order number.
     * @param $observer
     */
    public function postdispatchCartDelete($observer)
    {
        $unsetParams = array("token", "prepare_order_id");
        Mage::helper("xpaymentsconnector")->unsetXpaymentPrepareOrder($unsetParams);
    }


    /**
     * Set 'place_display' flag for feature x-payment form.
     * @param $observer
     */
    public function predispatchSaveShippingMethod()
    {
        Mage::helper("xpaymentsconnector")->setIframePlaceDisplaySettings();
    }

    /**
     * Remove X-Payments token after update shipping method
     * @param $observer
     */
    public function postdispatchSaveShippingMethod($observer)
    {
        $isPaymentPlaceDisplayFlag = Mage::helper("xpaymentsconnector")->isIframePaymentPlaceDisplay();
        if (!$isPaymentPlaceDisplayFlag) {
            $unsetParams = array("token");
            Mage::helper("xpaymentsconnector")->unsetXpaymentPrepareOrder($unsetParams);
        }
    }

    public function postDispatchSavePayment($observer)
    {
        $quote = Mage::getSingleton('checkout/session')->getQuote();
        $paymentMethodCode = $quote->getPayment()->getMethodInstance()->getCode();
        $xpaymentPaymentCode = Mage::getModel("xpaymentsconnector/payment_cc")->getCode();

        $isXpayments = Mage::helper("xpaymentsconnector")->isXpaymentsMethod($paymentMethodCode);
        if ($isXpayments) {
            Mage::helper("xpaymentsconnector")->prepareOrderKey();
        }
        if ($paymentMethodCode == $xpaymentPaymentCode) {
            $saveCard = Mage::app()->getRequest()->getPost("savecard");
            if ($saveCard) {
                Mage::getSingleton("checkout/session")->setData("user_card_save", $saveCard);
            }
        } else {
            Mage::getSingleton("checkout/session")->unsetData("user_card_save");
        }
    }

    /**
     * Set discount for recurring product
     * @param $observer
     */
    public function preDispatchCartIndex($observer)
    {
        $unsetXpPrepareOrder = Mage::app()->getRequest()->getParam("unset_xp_prepare_order");
        if (isset($unsetXpPrepareOrder)) {
            $unsetParams = array("token");
            Mage::helper("xpaymentsconnector")->unsetXpaymentPrepareOrder($unsetParams);
        }


        //set recurring product discount
        Mage::helper("xpaymentsconnector")->setRecurringProductDiscount();

    }


    /**
     * Send xp transaction from 'XP Order State tab'
     * @param $observer
     */
    public function adminhtmlSalesOrderView($observer){
        $xpTransactionData = Mage::app()->getRequest()->getPost();
        if(!empty($xpTransactionData)){

            if(!empty($xpTransactionData)
                && isset($xpTransactionData['xpaction'])
                && isset($xpTransactionData["xpc_txnid"])
                && isset($xpTransactionData["transaction_amount"])
            ){

                $xpaymentModel = Mage::getModel("xpaymentsconnector/payment_cc");
                $data = array(
                    'txnId' => $xpTransactionData["xpc_txnid"],
                    'amount' => number_format($xpTransactionData["transaction_amount"], 2, '.', ''),
                );
                $result = array();
                switch ($xpTransactionData['xpaction']) {
                    case 'refund':
                        $result = $xpaymentModel->authorizedTransactionRequest('refund', $data);
                        break;
                    case 'capture':
                        $result = $xpaymentModel->authorizedTransactionRequest('capture', $data);
                        break;
                    case 'void':
                        $result = $xpaymentModel->authorizedTransactionRequest('void', $data);
                        break;
                }

                if(empty($result['error_message'])){
                    $message =   Mage::helper("xpaymentsconnector")->__("Transaction '%s' to order (%s)  was successful!",
                    $xpTransactionData['xpaction'],
                    $xpTransactionData['orderid']);
                    Mage::getSingleton('adminhtml/session')->addSuccess($message);
                } else{
                    Mage::getSingleton('adminhtml/session')->addError($result['error_message']);
                }

            }
        }
    }

}