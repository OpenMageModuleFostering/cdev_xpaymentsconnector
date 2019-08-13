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
 * X-Payments Connector help link on the left side 
 * 
 * @package Cdev_XPaymentsConnector
 * @see     ____class_see____
 * @since   1.0.0
 */
class Cdev_XPaymentsConnector_Block_Adminhtml_Settings_Help extends Mage_Adminhtml_Block_Template
{
    /**
     * Constructor
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->setTemplate('xpaymentsconnector/settings/help.phtml');
    }

    /**
     * Get Contact Us link
     *
     * @return string
     */
    public function getContactUsUrl()
    {
        return 'http://www.x-payments.com/contact-us.html?utm_source=mage_shop&utm_medium=link&utm_campaign=mage_shop_link';
    }
}
