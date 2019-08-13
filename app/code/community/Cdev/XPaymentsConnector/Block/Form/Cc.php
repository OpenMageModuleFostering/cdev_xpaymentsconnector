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
 * Iframe block at checkout on the payment step 
 */
class Cdev_XPaymentsConnector_Block_Form_Cc extends Mage_Payment_Block_Form
{
    /**
     * Constructor
     *
     * @return void
     */
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('xpaymentsconnector/form/cc.phtml');
    }

    /**
     * Display iframe on the paymment step (before review) or not
     *
     * @return bool
     */
    protected function isVisible()
    {
        return Mage::helper('settings_xpc')->isUseIframe()
            && 'payment' == Mage::helper('settings_xpc')->getIframePlace();
    }
}
