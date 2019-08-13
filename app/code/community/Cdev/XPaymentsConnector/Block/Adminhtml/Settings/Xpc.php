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
 * X-Payments Connector settings page block
 * 
 * @package Cdev_XPaymentsConnector
 * @see     ____class_see____
 * @since   1.0.0
 */
class Cdev_XPaymentsConnector_Block_Adminhtml_Settings_Xpc extends Mage_Adminhtml_Block_Template
{
    /**
     * Constructor
     * 
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        $this->setTemplate('xpaymentsconnector/settings/xpc.phtml');
    }

    /**
     * Get button data
     *
     * @return array
     */
    private function getButtonData($name, $mode, $class = 'task')
    {
        return array(
            'type'         => 'submit',
            'element_name' => 'mode',
            'value'        => $mode,
            'label'        => Mage::helper('adminhtml')->__($name),
            'class'        => $class,
            'onclick'      => 'javascript: submitPaymentMethodsForm(this);',
        );
    }

    /**
     * Prepare layout
     * 
     * @return void
     */
    protected function _prepareLayout()
    {
        parent::_prepareLayout();

        $this->setChild(
            'welcomeSection',
            $this->getLayout()
                ->createBlock('xpaymentsconnector/adminhtml_settings_tab_welcome')
                ->setTemplate('xpaymentsconnector/settings/tabs/welcome.phtml')
        );

        $this->setChild(
            'connectionSection',
            $this->getLayout()
                ->createBlock('xpaymentsconnector/adminhtml_settings_tab_connection')
                ->setTemplate('xpaymentsconnector/settings/tabs/connection.phtml')
        );

        $this->setChild(
            'paymentMethodsSection',
            $this->getLayout()
                ->createBlock('xpaymentsconnector/adminhtml_settings_tab_paymentMethods')
                ->setTemplate('xpaymentsconnector/settings/tabs/payment_methods.phtml')
        );

        $this->setChild(
            'zeroAuthSection',
            $this->getLayout()
                ->createBlock('xpaymentsconnector/adminhtml_settings_tab_zeroAuth')
                ->setTemplate('xpaymentsconnector/settings/tabs/zero_auth.phtml')
        );

        $this->setChild(
            'updateButton',
            $this->getLayout()->createBlock('adminhtml/widget_button')
                ->setData(
                    $this->getButtonData('Save config', 'update', 'save')
                )
        );

        $this->setChild(
            'importButton',
            $this->getLayout()->createBlock('adminhtml/widget_button')
                ->setData(
                    $this->getButtonData('Re-import payment methods', 'import', 'gray')
                )
        );
    }

    /**
     * Check if it's welcome mode.
     * I.e. module is not configured, payment methods are not imported, etc.
     *
     * @return bool
     */
    protected function isWelcomeMode()
    {
        return !Mage::helper('settings_xpc')->isConfigured();
    }

    /**
     * Check if at least one of the payment configurations can be used for saving cards
     *
     * @return bool
     */
    public function isCanSaveCards()
    {
        return Mage::helper('settings_xpc')->isCanSaveCards();
    }

    /**
     * Check if sticky panel should be shown initially
     *
     * @return bool
     */
    public function isShowStickyPanel()
    {
        $tabId = Mage::app()->getRequest()->getParam('tab');

        return $tabId != Cdev_XPaymentsConnector_Helper_Settings_Data::TAB_CONNECTION
            && $tabId != Cdev_XPaymentsConnector_Helper_Settings_Data::TAB_ZERO_AUTH;
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

    /**
     * Get Add new payment method URL
     *
     * @return array
     */
    public function getAddNewPaymentMethodUrl()
    {
        return Mage::helper('settings_xpc')->getAdminUrl()
            . '?target=payment_confs';
    }
}
