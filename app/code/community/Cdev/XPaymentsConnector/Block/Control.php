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
 * X-Payments connector control page block
 * 
 * @package Cdev_XPaymentsConnector
 * @see     ____class_see____
 * @since   1.0.0
 */
class Cdev_XPaymentsConnector_Block_Control extends Mage_Adminhtml_Block_Template
{
    /**
     * @var array
     */
    private $_configurationErrorList = array();

    /**
     * Constructor
     * 
     * @return void
     * @access public
     * @see    ____func_see____
     * @since  1.0.0
     */
    public function __construct()
    {
        parent::__construct();
        $this->setTemplate('xpaymentsconnector/control.phtml');
    }

    /**
     * Prepare layout
     * 
     * @return void
     * @access protected
     * @see    ____func_see____
     * @since  1.0.0
     */
    protected function _prepareLayout()
    {
        parent::_prepareLayout();

        $this->setChild(
            'testButton',
            $this->getLayout()->createBlock('adminhtml/widget_button')
                ->setData(
                    array(
                        'type'  => 'submit',
                        'label' => Mage::helper('adminhtml')->__('Test module'),
                        'class' => 'task'
                       )
                )
        );

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
     * @access public
     * @see    ____func_see____
     * @since  1.0.0
     */
    public function isMethodsRequested()
    {
        return 0 < count($this->getPaymentMethods());
    }

    /**
     * Get requested payment configurations
     * 
     * @return array 
     * @access public
     * @see    ____func_see____
     * @since  1.0.0
     */
    public function getPaymentMethods()
    {
        $list = Mage::getModel('xpaymentsconnector/paymentconfiguration')->getCollection();

        return ($list && count($list)) ? $list : array();
    }

    /**
     * Check - is payment configurations is already imported into DB or not
     * 
     * @return boolean
     * @access public
     * @see    ____func_see____
     * @since  1.0.0
     */
    public function isMethodsAlreadyImported()
    {
        return 0 < count($this->getPaymentMethods());
    }

    /**
     * Get system requiremenets errors list
     * 
     * @return array
     * @access public
     * @see    ____func_see____
     * @since  1.0.0
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
     * @access public
     * @see    ____func_see____
     * @since  1.0.0
     */
    public function getConfigurationErrors()
    {
        if(empty($this->_configurationErrorList)){
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

}

