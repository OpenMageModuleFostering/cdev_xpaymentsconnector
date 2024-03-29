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
 * @author     Qualiteam Software <info@x-cart.com>
 * @category   Cdev
 * @package    Cdev_XPaymentsConnector
 * @copyright  (c) 2010-present Qualiteam software Ltd <info@x-cart.com>. All rights reserved
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * "Prepaid Payments (X-Payments)" info block
 */

class Cdev_XPaymentsConnector_Block_Info_Prepaidpayments extends Mage_Payment_Block_Info
{

    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('xpaymentsconnector/info/prepaidpayments.phtml');
    }

    /**
     * @return array
     */
    public function getCardData()
    {
        $admSession = Mage::getSingleton('adminhtml/session');
        $xpPrepaidPaymentsCard = $admSession->getData('xp_prepaid_payments');
        $cardData = Mage::getModel('xpaymentsconnector/usercards')->load($xpPrepaidPaymentsCard)->getData();

        return $cardData;
    }

    /**
     * @return array
     */
    public function getOrderCardData($orderId)
    {
        $orderCardData = unserialize(Mage::getModel('sales/order')->load($orderId)->getData("xp_card_data"));
        return $orderCardData;
    }
}





