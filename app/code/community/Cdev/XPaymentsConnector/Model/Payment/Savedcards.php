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
 * 'Use saved credit cards (X-Payments)'
 */
class Cdev_XPaymentsConnector_Model_Payment_Savedcards extends Cdev_XPaymentsConnector_Model_Payment_Abstract 
    implements  Mage_Payment_Model_Recurring_Profile_MethodInterface
{
    /**
     * Unique internal payment method identifier
     **/
    protected $_code = 'savedcards';

    protected $_isGateway               = false;

    protected $_defaultLocale           = 'en';

    protected $_paymentMethod           = 'cc';
   
    protected $_canUseCheckout          = true;
    protected $_canUseInternal          = true;
    protected $_canUseForMultishipping  = false;

    protected $_canCapture              = true; 
    protected $_canCapturePartial       = true;
    protected $_canRefund               = true;
    protected $_canRefundInvoicePartial = true;


    /**
     * Payment method info block
     */
    protected $_infoBlockType = 'xpaymentsconnector/info_savedcards';

    /**
     * Payment method form block
     */
    protected $_formBlockType = 'xpaymentsconnector/form_savedcards';

    public $firstTransactionSuccess = true;

    /**
     * Get saved card data from order
     *
     * @return array
     */
    private function getOrderCardData()
    {
        return Mage::helper('xpaymentsconnector')->getOrderXpcCardData(
            $this->getOrder()
        );
    }    

    /**
     * Get payment configuration model
     *
     * @return Cdev_XPaymentsConnector_Model_Paymentconfiguration
     */
    public function getPaymentConfiguration()
    {
        $data = $this->getOrderCardData();

        return Mage::getModel('xpaymentsconnector/paymentconfiguration')
            ->load($data['confid']);
    }

    /**
     * Get internal XPC method code
     * (number from xpayments1, xpayments1, etc)
     *
     * @return int
     */
    public function getXpcSlot()
    {
        return Mage::helper('settings_xpc')->getXpcSlotByConfid(
            $this->getPaymentConfiguration()->getData('confid')
        );
    }

    /**
     * Process payment by saved card
     *
     * @param Cdev_XPaymentsConnector_Model_Quote $quote Quote (for checkout or backend order)
     *
     * @return Cdev_XPaymentsConnector_Transport_ApiResponse
     */
    public function processPayment(Cdev_XPaymentsConnector_Model_Quote $quote)
    {
        $entityId = $quote->getEntityId();

        if ($quote->isBackendOrderQuote()) {
            $isBackend = true;
            $refId = $quote->getXpcData()->getData('backend_orderid');
            $amount = $quote->getBackendOrder()->getGrandTotal();
        } else {
            $isBackend = false;
            $refId = $quote->getData('reserved_order_id');
            $amount = $quote->getGrandTotal();
        }

        $description = 'Order #' . $refId;

        $preparedCart = Mage::helper('cart_xpc')->prepareCart($quote, $refId);

        $cardData = $this->getOrderCardData();

        // Data to send to X-Payments
        $data = array(
            'txnId'       => $cardData['txnId'],
            'refId'       => $refId,
            'amount'      => $amount,
            'description' => $description,
            'cart'        => $preparedCart,
            'callbackUrl' => Mage::helper('xpaymentsconnector')->getCallbackUrl($entityId, $this->getXpcSlot(), $this->getStoreId(), false),
        );

        $response = Mage::helper('api_xpc')->requestPaymentRecharge($data);

        return $response;
    }

    /**
     * Check method availability
     *
     * @param Mage_Sales_Model_Quote $quote Quote
     *
     * @return boolean
     */
    public function isAvailable($quote = null)
    {
        $result = false;

        if (
            parent::isAvailable($quote)
            && $quote
            && !empty($quote->getCustomer()->getEmail())
        ) {

            $email = $quote->getCustomer()->getEmail();
            $list = Mage::getModel('customer/customer')
                ->getCollection()
                ->addFieldToFilter('email', $email);

            foreach ($list as $customer) {

                $cardsCount = Mage::getModel('xpaymentsconnector/usercards')
                    ->getCollection()
                    ->addFieldToFilter('user_id', $customer->getEntityId())
                    ->addFieldToFilter('usage_type', Cdev_XPaymentsConnector_Model_Usercards::SIMPLE_CARD)
                    ->count();

                if ($cardsCount) {
                    $result = true;
                    break;
                }
            } 
        }

        return $result;
    }

    /**
     * Get redirect URL to process recharge
     *
     * @return string
     */
    public function getOrderPlaceRedirectUrl()
    {
        $request = Mage::app()->getRequest()->getParam('payment');

        $session = Mage::getSingleton('checkout/session');

        try {

            // Check card from checkout
            if (empty($request['xp_payment_card'])) {
                throw new Exception('Wrong card id');
            }

            $cardId = $request['xp_payment_card'];
            $customerId = $session->getQuote()->getCustomerId();

            if (empty($customerId)) {

                // Occasionally customer can be undefined at checkout session.
                // Try taking it from the customer session.
                $customer = Mage::getSingleton('customer/session')->getCustomer();
                $customerId = $customer->getEntityId();
            }

            $card = Mage::getModel('xpaymentsconnector/usercards')->load($cardId);

            // Make sure this card belongs to the current customer
            if ($card->getUserId() != $customerId) {
                throw new Exception('Wrong card id');
            }

            $session->setXpcSaveCardId($cardId);

        } catch (Exception $exception) {

            // Save error to display
            $session->addError($exception->getMessage());

            // And throw it further
            throw $exception;
        }
 
        $url = Mage::getUrl('xpaymentsconnector/processing/recharge');

        return $url;
    }
}
