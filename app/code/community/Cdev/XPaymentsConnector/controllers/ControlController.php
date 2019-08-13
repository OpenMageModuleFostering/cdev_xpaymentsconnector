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
 * X-Payment connector management page controller 
 * 
 * @package Cdev_XPaymentsConnector
 * @see     ____class_see____
 * @since   1.0.0
 */
class Cdev_XPaymentsConnector_ControlController extends Mage_Adminhtml_Controller_Action
{
    /**
     * General action 
     * 
     * @return void
     * @access public
     * @see    ____func_see____
     * @since  1.0.0
     */
    public function indexAction()
    {
        $this->_title($this->__('System'))->_title($this->__('X-Payments connector control'));

        $this->loadLayout();

        $this->_setActiveMenu('system');
        $this->_addBreadcrumb(Mage::helper('adminhtml')->__('System'), Mage::helper('adminhtml')->__('System'));
        $this->_addBreadcrumb(Mage::helper('adminhtml')->__('X-Payments connector control'), Mage::helper('adminhtml')->__('X-Payments connector control'));

        $block = $this->getLayout()->createBlock('xpaymentsconnector/control');
        $this->_addContent($block);

        $this->renderLayout();
    }

    /**
     * Test connection to X-Payments
     * 
     * @return void
     * @access public
     * @see    ____func_see____
     * @since  1.0.0
     */
    public function testAction()
    {
        $session = Mage::getSingleton('adminhtml/session');

        $model = Mage::getModel('xpaymentsconnector/payment_cc');

        try {
            if ($model->sendTestRequest()) {
                $session->addSuccess(Mage::helper('adminhtml')->__('The test transaction has been completed successfully.'));

            } else {
                $session->addError(Mage::helper('adminhtml')->__('Test transaction failed. Please check the X-Payment Connector settings and try again. If all options is ok review your X-Payments settings and make sure you have properly defined shopping cart properties.'));
            }

        } catch (Exception $e) {
            $session->addException($e, 'Test transaction failed. Please check the X-Payment Connector settings and try again. If all options is ok review your X-Payments settings and make sure you have properly defined shopping cart properties.');
        }

        $this->_redirect('*/*/index');
    }

    /**
     * Request and import payment configurations from X-Payments
     * 
     * @return void
     * @access public
     * @see    ____func_see____
     * @since  1.0.0
     */
    public function requestAction()
    {
        $session = Mage::getSingleton('adminhtml/session');

        $model = Mage::getModel('xpaymentsconnector/payment_cc');

        $this->deleteAllPaymentConfigurations();
        $store = Mage::app()->getStore(null)->getCode();
        Mage::getConfig()->saveConfig('stores/' . $store . '/payment/xpayments/active', 0);

        try {
            $list = $model->requestPaymentMethods();
            if ($list) {
                foreach ($list as $data) {
                    $pc = Mage::getModel('xpaymentsconnector/paymentconfiguration')->setData(
                        array(
                            'confid'         => $data['id'],
                            'name'           => $data['name'],
                            'module'         => $data['moduleName'],
                            'auth_exp'       => $data['authCaptureInfo']['authExp'],
                            'capture_min'    => $data['authCaptureInfo']['captMinLimit'],
                            'capture_max'    => $data['authCaptureInfo']['captMaxLimit'],
                            'hash'           => $data['settingsHash'],
                            'is_auth'        => $data['transactionTypes']['auth'],
                            'is_capture'     => $data['transactionTypes']['capture'],
                            'is_void'        => $data['transactionTypes']['void'],
                            'is_refund'      => $data['transactionTypes']['refund'],
                            'is_part_refund' => $data['transactionTypes']['refundPart'],
                            'is_accept'      => $data['transactionTypes']['accept'],
                            'is_decline'     => $data['transactionTypes']['decline'],
                            'is_get_info'    => $data['transactionTypes']['getInfo'],
                            
                        )
                    );

                    $pc->save();

                }

                $session->addSuccess(Mage::helper('adminhtml')->__('Payment methods have been successfully imported.'));

            } elseif (is_array($list)) {
                $session->addError(Mage::helper('adminhtml')->__('There are no payment configurations for this store.'));
            } else {
                $session->addError(Mage::helper('adminhtml')->__('An error has occured during requesting payment methods from X-Payments. See log files for details.'));
            }

        } catch (Exception $e) {
            $session->addException($e, 'An error has occured during requesting payment methods from X-Payments. See log files for details.');
        }

        $this->_redirect('*/*/index');
    }

    /**
     * Clear imported payment configurations
     * 
     * @return void
     * @access public
     * @see    ____func_see____
     * @since  1.0.0
     */
    public function clearAction()
    {
        $this->deleteAllPaymentConfigurations();
        $store = Mage::app()->getStore(null)->getCode();
        Mage::getConfig()->saveConfig('stores/' . $store . '/payment/xpayments/active', 0);

        Mage::getSingleton('adminhtml/session')->addSuccess(
            Mage::helper('adminhtml')->__('The list of imported payment configurations has been cleared. X-Payments connector payment method has been disabled.')
        );

        $this->_redirect('*/*/index');
    }


    /**
     * Delete all payment configurations 
     * 
     * @return void
     * @access protected
     * @see    ____func_see____
     * @since  1.0.0
     */

    protected function deleteAllPaymentConfigurations()
    {
        try {
            $list = Mage::getModel('xpaymentsconnector/paymentconfiguration')->getCollection();
            if ($list) {
                foreach ($list as $item) {
                    $item->delete();
                }
            }

        } catch (Exception $e) {
            Mage::log(
                sprintf('Couldn\'t delete payment configuration. [%s]', var_export($item, true)), 
                Zend_Log::ERR
            );
        }
    }

    protected function _isAllowed()
    {
        return Mage::getSingleton('admin/session')->isAllowed('system/control');
    }
}
