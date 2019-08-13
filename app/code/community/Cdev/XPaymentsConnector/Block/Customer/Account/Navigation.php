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
 * Remove account link "My Payment Cards"
 */
class Cdev_XPaymentsConnector_Block__Customer_Account_Navigation extends Mage_Customer_Block_Account_Navigation
{
    /**
     * @return $this
     */
    public function removeLink()
    {
        $IsSaveCardsPaymentActive = (bool)Mage::getStoreConfig('payment/savedcards/active');
        if (!$IsSaveCardsPaymentActive) {
            unset($this->_links["customer_usercards"]);
        }
        return $this;
    }

    /**
     * @return mixed
     */
    protected function _toHtml()
    {
        $this->removeLink();
        return parent::_toHtml();
    }
}