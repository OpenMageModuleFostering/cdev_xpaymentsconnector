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
 * X-Payments Connector Connection settings tab
 */
class Cdev_XPaymentsConnector_Block_Adminhtml_Settings_Tab_Connection 
    extends Cdev_XPaymentsConnector_Block_Adminhtml_Settings_Tab 
    implements Mage_Adminhtml_Block_Widget_Tab_Interface
{
    /**
     * Current tab
     */
    protected $tab = Cdev_XPaymentsConnector_Helper_Settings_Data::TAB_CONNECTION;

    /**
     * List of errors
     */
    private $errorList = null;

    /**
     * Determines whether to display the tab
     * Add logic here to decide whether you want the tab to display
     *
     * @return bool
     */
    public function canShowTab()
    {
        return true;
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
            'deployButton',
            $this->getLayout()->createBlock('adminhtml/widget_button')
                ->setData(
                    array(
                        'type'  => 'submit',
                        'label' => Mage::helper('adminhtml')->__('Deploy'),
                        'class' => 'task'
                    )
                )
        );

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
     * Check if options for Iframe should be displayed
     *
     * @return bool
     */
    public function isDisplayIframeOptions()
    {
        return !Mage::helper('settings_xpc')->checkOscModuleEnabled()
            && !Mage::helper('settings_xpc')->checkFirecheckoutModuleEnabled();
    }

    /**
     * Get module configuration errors list
     *
     * @return array
     */
    public function getErrorList()
    {
        if (is_array($this->errorList)) {
            return $this->errorList;
        }

        $settings = Mage::helper('settings_xpc');

        if (!$settings->checkRequirements()) {

            $this->errorList = $settings->getRequirementsErrors();
        
        } elseif ($settings->getXpcConfig('xpay_conf_bundle')) {

            $this->errorList = $settings->getConfigurationErrors(true);

        } else {

            // Do not display configuration errors if bundle is empty
            $this->errorList = array();
        }

        return $this->errorList;
    }
}
