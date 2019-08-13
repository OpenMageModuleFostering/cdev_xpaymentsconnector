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
 * @author     Qualiteam Software info@qtmsoft.com
 * @category   Cdev
 * @package    Cdev_XPaymentsConnector
 * @copyright  (c) 2010-2016 Qualiteam software Ltd <info@x-cart.com>. All rights reserved
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
     * @var array
     */
    private $_configurationErrorList = array();

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
     * Prepare layout
     * 
     * @return void
     */
    protected function _prepareLayout()
    {
        parent::_prepareLayout();

        $this->setChild(
            'requestButton',
            $this->getLayout()->createBlock('adminhtml/widget_button')
                ->setData(
                    array(
                        'type'  => 'submit',
                        'label' => Mage::helper('adminhtml')->__('Import payment methods from X-Payments'),
                        'class' => 'task'
                    )
                )
        );

        $this->setChild(
            'clearButton',
            $this->getLayout()->createBlock('adminhtml/widget_button')
                ->setData(
                    array(
                        'type'  => 'submit',
                        'label' => Mage::helper('adminhtml')->__('Clear'),
                        'class' => 'task'
                    )
                )
        );
    }

    /**
     * Check - payment configuration is requested or not
     * 
     * @return boolean
     */
    public function isMethodsRequested()
    {
        return 0 < count($this->getPaymentMethods());
    }

    /**
     * Get requested payment configurations
     * 
     * @return array 
     */
    public function getPaymentMethods()
    {
        $list = Mage::getModel('xpaymentsconnector/paymentconfiguration')->getCollection();

        return !empty($list) ? $list : array();
    }

    /**
     * Check - is payment configurations are already imported into DB or not
     * 
     * @return boolean
     */
    public function isMethodsAlreadyImported()
    {
        // TODO: Same as isMethodsRequested()
        return 0 < count($this->getPaymentMethods());
    }

    /**
     * Get system requiremenets errors list
     * 
     * @return array
     */
    public function getRequiremenetsErrors()
    {
        $api = Mage::getModel('xpaymentsconnector/payment_cc');

        $result = $api->checkRequirements();

        $list = array();
        if ($result & $api::REQ_CURL) {
            $list[] = 'PHP extension cURL is not installed on your server';
        }

        if ($result & $api::REQ_OPENSSL) {
            $list[] = 'PHP extension OpenSSL is not installed on your server';
        }

        if ($result & $api::REQ_DOM) {
            $list[] = 'PHP extension DOM is not installed on your server';
        }

        return $list;
    }

    /**
     * Get module configuration errors list
     * 
     * @return array
     */
    public function getConfigurationErrors()
    {
        if (empty($this->_configurationErrorList)) {
            $api = Mage::getModel('xpaymentsconnector/payment_cc');

            $result = $api->getConfigurationErrors();

            $list = array();

            if ($result & $api::CONF_CART_ID) {
                $list[] = 'Store ID is empty or has an incorrect value';
            }

            if ($result & $api::CONF_URL) {
                $list[] = 'X-Payments URL is empty or has an incorrect value';
            }

            if ($result & $api::CONF_PUBLIC_KEY) {
                $list[] = 'Public key is empty';
            }

            if ($result & $api::CONF_PRIVATE_KEY) {
                $list[] = 'Private key is empty';
            }

            if ($result & $api::CONF_PRIVATE_KEY_PASS) {
                $list[] = 'Private key password is empty';
            }

            $this->_configurationErrorList = $list;
        }

        return $this->_configurationErrorList;
    }

    /**
     * Get System/X-Payments connector link
     *
     * @return string
     */
    public function getSystemConfigXpcUrl()
    {
        return $this->getUrl('adminhtml/system_config/edit/section/xpaymentsconnector/');
    }

    /**
     * Get System/X-Payments connector link
     *
     * @return string
     */
    public function getTrialDemoUrl()
    {
        return 'http://www.x-payments.com/trial-demo.html?utm_source=mage_shop&utm_medium=link&utm_campaign=mage_shop_link';
    }

    /**
     * Get User manual link
     *
     * @return string
     */
    public function getUserManualUrl()
    {
        return 'http://help.x-cart.com/index.php?title=X-Payments:User_manual#Online_Stores';
    }
    
    /**
     * Get video link
     *
     * @return string
     */
    public function getVideoUrl()
    {
        return 'https://www.youtube.com/embed/2VRR0JW23qc';
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
     * Get description
     *
     * @return string
     */
    public function getDescription()
    {
        $description = 'Give your customers – and yourself – peace of mind with this payment processing module
            that guarantees compliance with PCI security mandates, significantly reduces the risk of
            data breaches and ensures you won’t be hit with a fine of up to $500,000 for non-compliance.
            Safely and conveniently store customers credit card information to use for new orders, reorders
            or recurring payments.';

        return $this->__($description);
    }

}
