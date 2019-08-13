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
 * X-Payments Connector Zero auth (card setup) tab 
 */
class Cdev_XPaymentsConnector_Block_Adminhtml_Settings_Tab_ZeroAuth 
    extends Cdev_XPaymentsConnector_Block_Adminhtml_Settings_Tab 
    implements Mage_Adminhtml_Block_Widget_Tab_Interface
{
    /**
     * Current tab
     */
    protected $tab = Cdev_XPaymentsConnector_Helper_Settings_Data::TAB_ZERO_AUTH;

    /**
     * Determines whether to display the tab
     * Add logic here to decide whether you want the tab to display
     *
     * @return bool
     */
    public function canShowTab()
    {
        return !$this->isWelcomeMode()
            && $this->isCanSaveCards();
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
            'updateButton',
            $this->getLayout()->createBlock('adminhtml/widget_button')
                ->setData(
                    array(
                        'type'  => 'submit',
                        'label' => Mage::helper('adminhtml')->__('Update'),
                        'class' => 'task'
                    )
                )
        );
    }

    /**
     * Get list of payment methods which allow saving cards
     *
     * @return array 
     */
    public function getPaymentMethodsList()
    {
        $settings = Mage::helper('settings_xpc');

        $list = array(
            $settings::ZERO_AUTH_DISABLED => Mage::helper('adminhtml')->__('Do not use Save credit card setup'),
        );

        for ($xpcSlot = 1; $xpcSlot <= $settings::MAX_SLOTS; $xpcSlot++) {

            if ($settings->getPaymentConfig('active', $xpcSlot)) {

                $confid = $settings->getPaymentConfig('confid', $xpcSlot);

                $paymentConf = Mage::getModel('xpaymentsconnector/paymentconfiguration')->load($confid);

                if ('Y' == $paymentConf->getData('save_cards')) {
                    $list[$confid] = $settings->getPaymentConfig('title', $xpcSlot);
                }    
            }
        }

        return $list;
    }

    /**
     * Check if this payment configuration is used for the Zero Auth
     *
     * @return bool
     */
    public function isZeroAuthConfId($confid)
    {
        return $confid == Mage::helper('settings_xpc')->getXpcConfig('xpay_zero_auth_confid');
    }

    /**
     * Get amount
     *
     * @return string
     */
    protected function getAmount()
    {
        $settings = Mage::helper('settings_xpc');

        return $settings->preparePrice(
            $settings->getXpcConfig('xpay_zero_auth_amount')
        );
    }
}
