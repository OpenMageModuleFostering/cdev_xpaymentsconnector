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
 * Payment form block for the backend order
 */
class Cdev_XPaymentsConnector_Block_Adminhtml_Sales_Order_Pay extends Mage_Adminhtml_Block_Template
{
    /** 
     * Payment form fields
     */
    private $fields = null;

    /**
     * Set fields for payment form
     *
     * @param array $fields Payment for fields
     *
     * @return Mage_Adminhtml_Block_Template
     */
    public function setFields($fields)
    {
        $this->fields = $fields;

        return $this;
    }

    /**
     * Get fields for payment form
     *
     * @return array 
     */
    public function getFields()
    {
        return $this->fields;
    }

    /**
     * Get form action (URL)
     *
     * @return string
     */
    public function getFormAction()
    {
        return Mage::helper('settings_xpc')->getPaymentUrl();
    }

    /**
     * Constructor
     *  
     * @return void
     */
    protected function _construct()
    {
        $this->setTemplate('xpaymentsconnector/order/pay.phtml');
    }
}
