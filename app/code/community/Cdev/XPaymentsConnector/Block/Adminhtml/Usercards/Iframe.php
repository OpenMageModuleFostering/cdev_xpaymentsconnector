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
 * Block for add new card iframe
 */
class Cdev_XPaymentsConnector_Block_Adminhtml_Usercards_Iframe extends Mage_Adminhtml_Block_Template
{
    /** 
     * Payment form fields
     */
    private $fields = null;

    /**
     * Error message
     */
    private $error = null;

    /**
     * Return flag
     */
    private $returnFlag = false;

    /**
     * Set fields for payment form
     *
     * @param array $fields Payment for fields
     *
     * @return void
     */
    public function setFields($fields)
    {
        $this->fields = $fields;
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
     * Set error message
     *
     * @param string $error Error message
     *
     * @return void
     */
    public function setError($error)
    {
        $this->error = $error;
    }

    /**
     * Get error message
     *
     * @return string
     */
    public function getError()
    {
        return $this->error;
    }

    /**
     * Set return flag
     *
     * @param bool $returnFlag Return flag
     *
     * @return void
     */
    public function setReturnFlag($returnFlag)
    {
        $this->returnFlag = $returnFlag;
    }

    /**
     * Is it return from X-Payments 
     *
     * @return bool
     */
    public function isReturn()
    {
        return $this->returnFlag;
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
     * Get URL for payment cards tab in the profile
     *
     * @return string
     */
    public function getPaymentCardsUrl()
    {
        $params = array(
            'id'  => $this->getRequest()->getParam('customer_id'),
            'tab' => 'customer_edit_tab_usercards',
        );

        return Mage::helper('adminhtml')->getUrl('adminhtml/customer/edit', $params);
    }

    /**
     * Constructor
     *  
     * @return void
     */
    protected function _construct()
    {
        $this->setTemplate('xpaymentsconnector/usercards/iframe.phtml');
    }
}
