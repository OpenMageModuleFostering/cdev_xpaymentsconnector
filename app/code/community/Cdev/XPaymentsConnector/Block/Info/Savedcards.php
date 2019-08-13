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
 * @author     Qualiteam Software info@qtmsoft.com
 * @category   Cdev
 * @package    Cdev_XPaymentsConnector
 * @copyright  (c) 2010-2016 Qualiteam software Ltd <info@x-cart.com>. All rights reserved
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Credit card generic payment info
 */

class Cdev_XPaymentsConnector_Block_Info_Savedcards extends Mage_Payment_Block_Info
{
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('xpaymentsconnector/info/savedcards.phtml');
    }

    /**
     * @return mixed
     */
    public function getAdminXpPaymentCard()
    {
        $admSession = Mage::getSingleton('adminhtml/session');
        $adminhtmlPaymentCardNumber = $admSession->getData('xp_payment_card');

        return $adminhtmlPaymentCardNumber;
    }

    /**
     * @param null $adminhtmlPaymentCardNumber
     * @return array|mixe
     */
    public function getCardData($adminhtmlPaymentCardNumber = NULL)
    {
        $cardData = array();
        $xpUserCardsModel = Mage::getModel('xpaymentsconnector/usercards');
        if (is_null($adminhtmlPaymentCardNumber)) {
            $quote = Mage::getSingleton('checkout/session')->getQuote();
            $paymentCardNumber = $quote->getPayment()->getData('xp_payment_card');
            $cardData = $xpUserCardsModel->load($paymentCardNumber)->getData();
        } else {
            $cardData = $xpUserCardsModel->load($adminhtmlPaymentCardNumber)->getData();
        }
        $xpCardDataStr = Mage::helper('xpaymentsconnector')->prepareCardDataString($cardData);

        return $xpCardDataStr;
    }

    /**
     * @param $orderId
     * @return string
     */
    public function getOrderCardData($orderId)
    {
        $orderCardData = unserialize(Mage::getModel('sales/order')->load($orderId)->getData('xp_card_data'));
        $xpCardDataStr = Mage::helper('xpaymentsconnector')->prepareCardDataString($orderCardData);

        return $xpCardDataStr;
    }
}
