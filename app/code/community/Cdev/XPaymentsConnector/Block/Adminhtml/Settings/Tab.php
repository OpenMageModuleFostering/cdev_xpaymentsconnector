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
 * X-Payments Connector tabs abstract class 
 */
abstract class Cdev_XPaymentsConnector_Block_Adminhtml_Settings_Tab extends Mage_Adminhtml_Block_Template
{
    /** 
     * Current tab
     */
    protected $tab = null;

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
     * Retrieve the label used for the tab relating to this block
     *
     * @return string
     */
    public function getTabLabel()
    {
        $tabs = Mage::helper('settings_xpc')->getTabs();

        return $this->__($tabs[$this->tab]);
    }

    /**
     * Retrieve the title used by this tab
     *
     * @return string
     */
    public function getTabTitle()
    {
        $tabs = Mage::helper('settings_xpc')->getTabs();

        return $this->__($tabs[$this->tab]);
    }

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
     * Stops the tab being hidden
     *
     * @return bool
     */
    public function isHidden()
    {
        return !$this->canShowTab();
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
}
