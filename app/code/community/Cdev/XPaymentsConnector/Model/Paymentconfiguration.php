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
 * Payment configuration 
 * 
 * @package Cdev_XPaymentsConnector
 * @see     ____class_see____
 * @since   1.0.0
 */

class Cdev_XPaymentsConnector_Model_Paymentconfiguration extends Mage_Core_Model_Abstract
{
    /**
     * Internal constructor
     * 
     * @return void
     * @access protected
     * @see    ____func_see____
     * @since  1.0.0
     */
    protected function _construct()
    {
        $this->_init('xpaymentsconnector/paymentconfiguration');
    }
}

