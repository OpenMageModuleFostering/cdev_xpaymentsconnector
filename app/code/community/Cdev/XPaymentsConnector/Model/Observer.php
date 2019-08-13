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
 * Class Cdev_XPaymentsConnector_Model_Observer
 */
class Cdev_XPaymentsConnector_Model_Observer extends Mage_CatalogInventory_Model_Observer
{
    protected $_current_customer_id = null;

    /** 
     * Process event before invoice is saved
     *
     * @param Varien_Event_Observer $observer 
     *
     * @return void
     */
    public function orderInvoiceSaveBefore($observer)
    {
        $invoice = $observer->getEvent()->getInvoice();
        $order = $invoice->getOrder();

        $isXpc = Mage::helper('settings_xpc')->isXpcMethod(
            $order->getPayment()->getMethodInstance()->getCode()
        );

        if ($isXpc) {
            $txnid = $order->getData('xpc_txnid');
            $invoice->setTransactionId($txnid);
        }
    }

    /**
     * Process event when invoice is canceled
     *
     * @param Varien_Event_Observer $observer
     *
     * @return void
     */
    public function invoiceVoid($observer)
    {
        $invoice = $observer->getInvoice();
        $order = $invoice->getOrder();

        $isXpc = Mage::helper('settings_xpc')->isXpcMethod(
            $order->getPayment()->getMethodInstance()->getCode()
        );

        if ($isXpc) {

            // Cancel payment in X-Payments
            Mage::helper('api_xpc')->requestPaymentVoid(
                $order->getData('xpc_txnid'),
                $invoice->getGrandTotal()
            );
        }
    }

    /**
     * Process event when order is deleted
     *
     * @param Varien_Event_Observer $observer
     *
     * @return void
     */
    public function orderDeleteBefore($observer)
    {
        $order = $observer->getOrder();

        if (
            !$order
            || empty($order->getData('xpc_txnid'))
        ) {
            return;
        }

        // Get associated quote ID
        $quoteId = Mage::getModel('xpaymentsconnector/quote_xpcdata')
            ->load($order->getData('xpc_txnid'), 'txn_id')
            ->getData('quote_id');

        // Delete all XPC data records for quote
        Mage::getModel('xpaymentsconnector/quote_xpcdata')->deleteByQuoteId($quoteId);
    }

    /**
     * Process event when quote is deleted
     *
     * @param Varien_Event_Observer $observer
     *
     * @return void
     */
    public function quoteDeleteBefore($observer)
    {
        $quote = $observer->getQuote();

        if (
            !$quote
            || empty($quote->getId())
        ) {
            return;
        }

        // Delete all XPC data records for quote
        Mage::getModel('xpaymentsconnector/quote_xpcdata')->deleteByQuoteId($quote->getId());
    }

    public function createOrdersByCustomerSubscriptions($observer)
    {
        $xpaymentsHelper = Mage::helper('xpaymentsconnector');
        $recurringProfileList = Mage::getModel('sales/recurring_profile')
            ->getCollection()
            ->addFieldToFilter('state', array('neq' => Mage_Sales_Model_Recurring_Profile::STATE_CANCELED));

        $userTransactionHasBeenSend = false;
        if ($recurringProfileList->getSize() > 0) {
            foreach ($recurringProfileList as $profile) {
                $customerId = $profile->getCustomerId();
                if($this->_current_customer_id != $customerId){
                    $this->_current_customer_id = $customerId;
                    $userTransactionHasBeenSend = false;
                }

                if(!$userTransactionHasBeenSend){
                    if ($profile->getState() == Mage_Sales_Model_Recurring_Profile::STATE_PENDING) {
                            $cardData = $xpaymentsHelper->getProfileOrderCardData($profile);
                            $orderAmountData = $xpaymentsHelper->preparePayDeferredOrderAmountData($profile);
                            if(!empty($orderAmountData)){
                                $xpaymentsHelper->resendPayDeferredRecurringTransaction($profile, $orderAmountData, $cardData);
                                $userTransactionHasBeenSend = true;
                                continue;
                            }else{
                                $profile->activate();
                            }
                    }

                    if ($profile->getState() == Mage_Sales_Model_Recurring_Profile::STATE_ACTIVE) {
                        $startDateTime = strtotime($profile->getStartDatetime());
                        $lastSuccessTransactionDate = strtotime($profile->getXpSuccessTransactionDate());
                        $lastActionDate = ($startDateTime > $lastSuccessTransactionDate) ? $startDateTime : $lastSuccessTransactionDate;

                        $profilePeriodValue = $xpaymentsHelper->getCurrentBillingPeriodTimeStamp($profile);
                        $newTransactionDate = $lastActionDate + $profilePeriodValue;

                        $currentDateObj = new Zend_Date(time());
                        $currentDateStamp = $currentDateObj->getTimestamp();

                        /*
                        var_dump('period_stamp ='.$profilePeriodValue / 3600 ."(h)",
                                 "current = ".date("Y-m-d H:m:s",$currentDateStamp),
                                 "start = ".date("Y-m-d H:m:s",$startDateTime),
                                 "last = ".date("Y-m-d H:m:s",$lastActionDate),
                                 "new = ".date("Y-m-d H:m:s",$newTransactionDate),
                                 "profile_id = ".$profile->getProfileId());die;
                        */

                        $timePassed = $currentDateStamp - $lastActionDate;
                        $currentSuccessCycles = $profile->getXpCountSuccessTransaction();

                        $isFirstRecurringProfileProcess = (($currentSuccessCycles == 0) && ($startDateTime < $currentDateStamp))
                            ? true : false;

                        if (($timePassed >= $profilePeriodValue) || $isFirstRecurringProfileProcess) {
                            // check by count of success transaction
                            $periodMaxCycles = $profile->getPeriodMaxCycles();

                            if (($periodMaxCycles > $currentSuccessCycles) || is_null($periodMaxCycles)) {
                                $orderItemInfo = $profile->getData('order_item_info');
                                if (!is_array($orderItemInfo)) {
                                    $orderItemInfo = unserialize($orderItemInfo);
                                }

                                $initialFeeAmount = isset($orderItemInfo['recurring_initial_fee']) ? $orderItemInfo['recurring_initial_fee'] : 0;

                                $isFirstRecurringOrder = false;

                                //calculate grand total
                                $billingAmount  =  $profile->getBillingAmount();
                                $shippingAmount =  $profile->getShippingAmount();
                                $taxAmount = $profile->getTaxAmount();

                                $initialFeeTax = ($profile->getInitiaFeePaid()) ? 0 : 123;
                                // add discount for transaction amount
                                $useDiscount = ($profile->getXpCountSuccessTransaction() > 0) ? false : true;
                                $discountAmount = ($useDiscount) ? $orderItemInfo['discount_amount'] : 0;

                                $transactionAmount = $billingAmount + $shippingAmount + $taxAmount - $discountAmount ;

                                $orderId = $xpaymentsHelper->createOrder($profile,$isFirstRecurringOrder);
                                $cardData = $xpaymentsHelper->getProfileOrderCardData($profile);

                                $response = Mage::getModel('xpaymentsconnector/payment_cc')->sendAgainTransactionRequest($orderId, NULL, $transactionAmount , $cardData);
                                $userTransactionHasBeenSend = true;
                                if ($response['success']) {
                                    $result = Mage::getModel('xpaymentsconnector/payment_cc')->updateOrderByXpaymentResponse($orderId, $response['response']['transaction_id']);
                                    $xpaymentsHelper->updateCurrentBillingPeriodTimeStamp($profile, $result['success'], $newTransactionDate);
                                    if (!$result['success']) {
                                        Mage::log($result['error_message'], null, $xpaymentsHelper::XPAYMENTS_LOG_FILE, true);
                                    }

                                } else {
                                    $xpaymentsHelper->updateCurrentBillingPeriodTimeStamp($profile, $response['success'], $newTransactionDate);
                                    Mage::log($response['error_message'], null, $xpaymentsHelper::XPAYMENTS_LOG_FILE, true);
                                }

                            } else {
                                // Subscription is completed
                                $profile->finished();

                            }
                        }
                    }
                }
            }

        }


    }

    /**
     * Set discount for recurring product(for ajax cart item quantity update)
     * Remove X-Payments token
     * Update initial Fee for recurring product;
     * @param $observer
     */
    public function updateCartItem($observer)
    {
        $xpHelper = Mage::helper('xpaymentsconnector');

        // update InitAmount for recurring products
        $cart = $observer->getCart('quote');
        foreach ($cart->getAllVisibleItems() as $item){
            $product = $item->getProduct();
            if ((bool)$product->getIsRecurring()) {
                $profile = Mage::getModel('payment/recurring_profile')
                    ->setLocale(Mage::app()->getLocale())
                    ->setStore(Mage::app()->getStore())
                    ->importProduct($product);
                if($profile->getInitAmount()){
                    // duplicate as 'additional_options' to render with the product statically
                    $infoOptions = array(array(
                        'label' => $profile->getFieldLabel('start_datetime'),
                        'value' => $profile->exportStartDatetime(true),
                    ));

                    foreach ($profile->exportScheduleInfo($item) as $info) {
                        $infoOptions[] = array(
                            'label' => $info->getTitle(),
                            'value' => $info->getSchedule(),
                        );
                    }

                    $itemOptionModel = Mage::getModel('sales/quote_item_option')
                        ->getCollection()
                        ->addItemFilter($item->getId())
                        ->addFieldToFilter('code','additional_options')
                        ->addFieldToFilter('product_id',$product->getId())
                        ->getFirstItem();

                    $itemOptionModel->setValue(serialize($infoOptions));
                    $itemOptionModel->save();
                }
            }


        }
        //

    }
}
