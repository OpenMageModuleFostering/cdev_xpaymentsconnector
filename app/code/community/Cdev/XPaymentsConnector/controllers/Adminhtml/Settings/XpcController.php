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
 * X-Payment connector management page controller 
 * 
 * @package Cdev_XPaymentsConnector
 * @see     ____class_see____
 * @since   1.0.0
 */
class Cdev_XPaymentsConnector_Adminhtml_Settings_XpcController extends Mage_Adminhtml_Controller_Action
{
    /**
     * Some error messages
     */
    const ERROR_EMPTY_IMPORTED_METHODS = 'Payment methods import failed. Make sure you’ve activated your payment configurations and assigned them to this store in X-Payments dashboard.';
    const ERROR_INVALID_BUNDLE = 'Invalid configuration bundle';

    /**
     * This is the remembered data about payment configurations during re-import
     */
    private $remember = array();

    /**
     * Data POST-ed to the controller for update payment metods action
     */
    private $updateData = array();

    /**
     * Payment method data POST-ed to the controller for update payment metods action
     */
    private $methodData = array();

    /**
     * Here we count the active payment configurations during the update payment metods action.
     * Value should not exceed Cdev_XPaymentsConnector_Helper_Settings_Data::MAX_SLOTS
     */
    private $activeCount = 0;

    /**
     * If at least one active payment configurations is configured to save cards
     */
    private $saveCardsActive = false;
    
    /**
     * Default data for payment configuration (e.g. just imported)
     */
    private $defaultPaymentData = array(
        'title' => 'Credit card (X-Payments)',
        'sort_order' => '0',
        'allowspecific' => '0',
        'specificcountry' => '',
        'use_authorize' => '0',
        'use_initialfee_authorize' => '0',
    );

    /**
     * Check if X-Payments Connector configuration is allowed for user
     *
     * @return bool
     */
    protected function _isAllowed()
    {
        return Mage::getSingleton('admin/session')->isAllowed('system/xpaymentsconnector/configuration');
    }

    /**
     * General action 
     * 
     * @return void
     */
    public function indexAction()
    {
        $this->_title($this->__('System'))->_title($this->__('X-Payments Connector settings'));

        Mage::helper('settings_xpc')->setRecheckFlag();

        $this->loadLayout();
        $block = $this->getLayout()->createBlock('xpaymentsconnector/adminhtml_settings_xpc');
        $this->_addContent($block);

        $this->renderLayout();
    }

    /**
     * Deploy configuration bundle action
     * Auto (re)import payment methods
     *
     * @return void
     */
    public function deployAction()
    {
        $redirect = '*/*/index';

        try {

            $settings = Mage::helper('settings_xpc');

            $settings->setXpcConfig('xpay_conf_bundle', '');
            $settings->setXpcConfig('xpay_api_version', '');

            $bundle = Mage::app()->getRequest()->getParam('bundle');
            $bundle = strval($bundle); 

            // Check submitted bundle
            if (empty($settings->decodeBundle($bundle))) {
                $settings->setXpcConfig('xpay_is_configured', false, true);
                throw new Exception(self::ERROR_INVALID_BUNDLE);
            }

            // Save bundle and reload config
            $settings->setXpcBundle($bundle);

            // Force recheck configuration
            $settings->setRecheckFlag();

            if ($settings->isConfigured()) {
                $this->importPaymentMethods();
                $this->autoActivateZeroAuth();
                $this->addSuccessTopMessage('Configuration bundle has been deployed successfully');
            } else {
                $redirect = '*/*/index/tab/connection';
            }


        } catch (Exception $e) {

            $this->addErrorTopMessage($e->getMessage());
            $redirect = '*/*/index/tab/connection';
        }

        $this->_redirect($redirect);
    }

    /**
     * Update settings action 
     *
     * @return void
     */
    public function updateAction()
    {
        $helper = Mage::helper('settings_xpc');

        // Save IP address 
        $ip = strval(Mage::app()->getRequest()->getParam('ip_address'));
        $helper->setXpcConfig('xpay_allowed_ip_addresses', $ip);

        // Save use iframe
        $useIframe = (bool)Mage::app()->getRequest()->getParam('use_iframe');
        $helper->setXpcConfig('xpay_use_iframe', $useIframe);

        // Save iframe place
        $iframePlace = Mage::app()->getRequest()->getParam('iframe_place');
        if (in_array($iframePlace, array('payment', 'review'))) {
            $helper->setXpcConfig('xpay_iframe_place', $iframePlace);
        }

        // Save force HTTP option
        $forceHttp = (bool)Mage::app()->getRequest()->getParam('force_http');
        $helper->setXpcConfig('xpay_force_http', $forceHttp);

        $this->_redirect('*/*/index/tab/connection');
    }

    /**
     * Set value for the Active param
     *
     * @param Cdev_XPaymentsConnector_Model_Paymentconfiguration $paymentConf Payment configuration
     *
     * @return Cdev_XPaymentsConnector_Model_Paymentconfiguration 
     */
    private function setActiveParamValue(Cdev_XPaymentsConnector_Model_Paymentconfiguration $paymentConf)
    {
        $confId = $paymentConf->getConfid();

        if (
            !isset($this->updateData['active'][$confId])
            || 'Y' != $this->updateData['active'][$confId]
            || $this->activeCount > Cdev_XPaymentsConnector_Helper_Settings_Data::MAX_SLOTS
        ) {
            $active = 'N';
        } else {
            $active = 'Y';
            $this->activeCount++;
        }

        return $paymentConf->setActive($active);
    }

    /**
     * Set value for the Save cards param
     *
     * @param Cdev_XPaymentsConnector_Model_Paymentconfiguration $paymentConf Payment configuration
     *
     * @return Cdev_XPaymentsConnector_Model_Paymentconfiguration
     */
    private function setSaveCardsParamValue(Cdev_XPaymentsConnector_Model_Paymentconfiguration $paymentConf)
    {
        $confId = $paymentConf->getConfid();

        if (
            !isset($this->updateData['savecard'][$confId])
            || 'Y' != $this->updateData['savecard'][$confId]
            || 'Y' != $paymentConf->getCanSaveCards()
        ) {
            $saveCards = 'N';
        } else {
            $saveCards = 'Y';
            if ($paymentConf->getActive() == 'Y') {
                $this->saveCardsActive = true;
            }
        }

        return $paymentConf->setSaveCards($saveCards);
    }

    /**
     * Get default payment method data
     *
     * @param mixed $paymentConf Payment configuration
     *
     * @return array
     */
    private function getDefaultPaymentData($paymentConf = null)
    {
        $result = $this->defaultPaymentData;

        if ($paymentConf instanceof Cdev_XPaymentsConnector_Model_Paymentconfiguration) {

            $result['title'] = 'Credit card (' . $paymentConf->getData('name') . ')';

        } elseif (
            is_array($paymentConf)
            && !empty($paymentConf['name'])
        ) {

            $result['title'] = 'Credit card (' . $paymentConf['name'] . ')';
        }

        return $result;
    }

    /**
     * Set value for the payment method data param
     *
     * @param Cdev_XPaymentsConnector_Model_Paymentconfiguration $paymentConf Payment configuration
     *
     * @return Cdev_XPaymentsConnector_Model_Paymentconfiguration
     */
    private function setPaymentMethodDataParamValue(Cdev_XPaymentsConnector_Model_Paymentconfiguration $paymentConf)
    {
        $confId = $paymentConf->getConfid();

        if (isset($this->methodData[$confId])) {

            $pmd = $this->methodData[$confId];

            if (
                isset($pmd['specificcountry'])
                && is_array($pmd['specificcountry'])
                && $pmd['allowspecific']
            ) {
                $pmd['specificcountry'] = implode(',', $pmd['specificcountry']);
            } else {
                $pmd['specificcountry'] = '';
            }

        } else {

            $pmd = $this->getDefaultPaymentData($paymentConf);
        }

        return $paymentConf->setPaymentMethodData(serialize($pmd));
    }

    /**
     * Process data for payment method associated with the payment configuration
     *
     * @param Cdev_XPaymentsConnector_Model_Paymentconfiguration $paymentConf Payment configuration
     *
     * @return void
     */
    private function processPaymentConfMethod(Cdev_XPaymentsConnector_Model_Paymentconfiguration $paymentConf)
    {
        if ('Y' == $paymentConf->getActive()) {

            $confId = $paymentConf->getConfid();
            $data = unserialize($paymentConf->getPaymentMethodData());

            $data += array(
                'confid' => $confId,
                'active' => 1,
            );

            foreach ($data as $name => $value) {
                Mage::helper('settings_xpc')->setPaymentConfig($name, $value, $this->activeCount);
            }
        }
    }

    /**
     * Deactivate unused or "extra" payment methods, including the "Saved cards" one
     *
     * @param int $startId Slot index of the XPC payment method to start with
     *
     * @return void
     */
    private function deactivatePaymentMethods($startSlot = 1)
    {
        $settings = Mage::helper('settings_xpc');

        // Deactivate 
        for ($xpcSlot = $startSlot; $xpcSlot <= Cdev_XPaymentsConnector_Helper_Settings_Data::MAX_SLOTS; $xpcSlot++) {
            $settings->setPaymentConfig('active', 0, $xpcSlot);
        }

        // Deactivate "Saved cards" payment method
        $settings->setSavedCardsConfig('active', false);
    }

    /**
     * Process data for "Saved cards" payment method
     *
     * @return void
     */
    private function processSavedCardsMethod()
    {
        $settings = Mage::helper('settings_xpc');

        $settings->setSavedCardsConfig(
            'title',
            Mage::app()->getRequest()->getParam('savedcards_title')
        );

        $settings->setSavedCardsConfig(
            'sort_order',
            Mage::app()->getRequest()->getParam('savedcards_sort_order')
        );

        $settings->setSavedCardsConfig('active', $this->saveCardsActive);
    }

    /**
     * Subaction (mode) to update the payment methods
     *
     * @return void
     */
    private function updatePaymentMethods()
    {
        $list = Mage::getModel('xpaymentsconnector/paymentconfiguration')->getCollection();

        $this->saveCardsActive = false;
        $this->activeCount = 0;

        $this->updateData = Mage::app()->getRequest()->getParam('payment_methods');
        $this->methodData = Mage::app()->getRequest()->getParam('payment_method_data');

        foreach ($list as $paymentConf) {

            // Save data for payment configuration
            $this->setActiveParamValue($paymentConf);
            $this->setSaveCardsParamValue($paymentConf);
            $this->setPaymentMethodDataParamValue($paymentConf);
            $paymentConf->save();

            // Update payment method
            $this->processPaymentConfMethod($paymentConf);
        }

        // Deactivate "extra" payment methods
        $this->deactivatePaymentMethods($this->activeCount + 1);

        // Update saved cards payment method
        $this->processSavedCardsMethod();
    }

    /**
     * Update payment methods action
     *
     * @return void
     */
    public function paymentmethodsAction()
    {
        $mode = Mage::app()->getRequest()->getParam('mode');

        try {

            if ('update' == $mode) {

                $this->updatePaymentMethods();
                $this->addSuccessTopMessage('Payment methods updated successfully');

            } elseif ('import' == $mode) {

                $this->importPaymentMethods();
                $this->addSuccessTopMessage('Payment methods import successful');
            }

            $this->autoActivateZeroAuth();

        } catch (Exception $e) {

            $this->addSuccessTopMessage($e->getMessage());
        }

        $this->_redirect('*/*/index');
    }

    /**
     * Update zero auth action
     *
     * @return void
     */
    public function zeroauthAction()
    {
        $settings = Mage::helper('settings_xpc');

        $result = false;

        // Save configuration ID
        $confid = Mage::app()->getRequest()->getParam('zero_auth_confid');
        $settings->setXpcConfig('xpay_zero_auth_confid', $confid);

        // Save description
        $description = strval(Mage::app()->getRequest()->getParam('zero_auth_description'));
        if ($description) {
            $settings->setXpcConfig('xpay_zero_auth_description', $description);
            $result = true;
        }
        
        // Save amount
        $amount = Mage::app()->getRequest()->getParam('zero_auth_amount');
        if (is_numeric($amount)) {
            $amount = round($amount, 2);
            $settings->setXpcConfig('xpay_zero_auth_amount', $amount);
            $result = true;
        }

        if ($result) {
            $this->addSuccessTopMessage('Settings updated');
        } else {
            $this->addErrorTopMessage('Settings not updated');
        }

        $this->_redirect('*/*/index/tab/zero_auth');
    }

    /**
     * Clear ipmorted payment methods
     *
     * @return void
     */
    private function clearPaymentMethods()
    {
        $list = Mage::getModel('xpaymentsconnector/paymentconfiguration')->getCollection();

        if ($list) {
            foreach ($list as $pc) {

                $this->remember[$pc->getConfid()] = array(
                    'name'       => $pc->getName(), 
                    'module'     => $pc->getModule(),
                    'hash'       => $pc->getHash(),
                    'save_cards' => $pc->getSaveCards(),
                    'active'     => $pc->getActive(),
                    'data'       => $pc->getPaymentMethodData(),
                );

                $pc->delete();
            }
        }

        // Deactivate all payment methods
        $this->deactivatePaymentMethods();
    }

    /**
     * Import payment methods
     *
     * @return void
     */
    private function importPaymentMethods()
    {
        $settings = Mage::helper('settings_xpc');

        // Clear existing payment configurations, but save their data.
        // Disable all X-Payments payment methods including the "Saved cards" one.
        $this->clearPaymentMethods();

        // Obtain payment methods from X-Payments
        $list = Mage::helper('api_xpc')->requestPaymentMethods();

        if (empty($list)) {
            throw new Exception(self::ERROR_EMPTY_IMPORTED_METHODS);
        }

        foreach ($list as $data) {

            $paymentConfigurationData = $this->fillPaymentConfigurationData($data);

            // Save payment configuration data
            Mage::getModel('xpaymentsconnector/paymentconfiguration')
                ->setData($paymentConfigurationData)
                ->save();

            // Re-active payment method if it was active before re-import
            if ('Y' == $paymentConfigurationData['active']) {
            
                try {

                    $xpcSlot = $settings->getXpcSlotByConfid($data['id']);
                    $settings->setPaymentConfig('active', true, $xpcSlot);

                } catch (Exception $e) {

                    // Just in case.
                    // This payment configuration is not associated with payment method.
                } 
            }
        }
    }

    /**
     * Fill payment configuration data
     *
     * @param array $data Payment configuration data passed from X-Payments
     *
     * @return array
     */
    private function fillPaymentConfigurationData($data)
    {
        $confId = $data['id'];

        $pcData = array(
            'confid'         => $confId,
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
            'can_save_cards' => $data['canSaveCards'],
            'save_cards'     => $data['canSaveCards'], // Auto-enable recharges if possible
            'currency'       => $data['currency'],
            'active'         => 0,
            'payment_method_data' => serialize($this->getDefaultPaymentData($data)),
        );

        if (isset($this->remember[$confId])) {

            $remember = $this->remember[$confId];

            if (
                $remember['name'] == $pcData['name']
                || $remember['module'] == $pcData['module']
                || $remember['hash'] == $pcData['hash']
            ) {

                // Restore save cards flag
                $pcData['save_cards'] = $remember['save_cards'];

                // Resore active flag
                $pcData['active'] = $remember['active'];

                // Resore payment method data
                $pcData['payment_method_data'] = $remember['data'];
                                
                // Remembered data matches the imported one
                $this->remember[$confId]['match'] = true;

            } else {

                // Remembered data doesn't match the imported one
                $this->remember[$confId]['match'] = false;
            }

        } else {

            // This is a new/unknown payment configuration
            $this->remember[$confId] = array(
                'match' => false,
            );
        }

        return $pcData;
    }
    
    /**
     * Automaticaly activate zero-auth if necessary 
     *
     * @return void
     */
    private function autoActivateZeroAuth()
    {
        $settings = Mage::helper('settings_xpc');

        if ($settings->isZeroAuthMethodDisabled()) {
            return;
        }

        $list = Mage::getModel('xpaymentsconnector/paymentconfiguration')->getCollection();

        $zeroAuthConfId = false;

        // Search for payment configuration suitable for zero auth
        foreach ($list as $paymentConf) {

            if (
                'Y' == $paymentConf->getData('active')
                && 'Y' == $paymentConf->getData('save_cards')
            ) {
                $zeroAuthConfId = $paymentConf->getData('confid');
                break;
            }
        }

        // Auto enable zero auth
        if ($zeroAuthConfId) {
            $settings->setXpcConfig('xpay_zero_auth_confid', $zeroAuthConfId);
        }

    }

    /** 
     * Add success message to session
     *
     * @param string $message Message
     *
     * @return void
     */
    private function addSuccessTopMessage($message)
    {
        Mage::getSingleton('adminhtml/session')->addSuccess(
            $this->__($message)
        );
    }

    /**
     * Add success message to session
     *
     * @param string $message Message
     *
     * @return void
     */
    private function addErrorTopMessage($message)
    {
        Mage::getSingleton('adminhtml/session')->addError(
            $this->__($message)
        );
    }
}
