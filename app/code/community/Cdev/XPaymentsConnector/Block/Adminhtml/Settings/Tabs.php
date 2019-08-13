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
 * Tab widget for X-Payments Connector settings 
 */
class Cdev_XPaymentsConnector_Block_Adminhtml_Settings_Tabs extends Mage_Adminhtml_Block_Widget_Tabs
{
    /**
     * List of tab IDs
     */
    private $tabIDs = array();

    /**
     * Convert Tab ID
     *
     * @param string $tabId
     *
     * @return string
     */
    private function convertTabId($tabId)
    {
        return 'settings_xpc_tabs_' . str_replace('_', '', $tabId) . '_section';
    }

    /**
     * Constructor
     * 
     * @return void
     */
    public function __construct()
    {
        parent::__construct();

        $this->setId('settings_xpc_tabs');
        $this->setDestElementId('settings_xpc');

        foreach (Mage::helper('settings_xpc')->getTabs() as $tabId => $name) {
            $this->tabIDs[$tabId] = $this->convertTabId($tabId);
        }
    }

    /**
     * Get active tab ID
     *
     * @return string
     */
    public function getActiveTabId()
    {
        $tabId = Mage::app()->getRequest()->getParam('tab');

        if (
            !empty($tabId)
            && array_key_exists($tabId, $this->tabIDs)
        ) {

            $tabId = $this->tabIDs[$tabId];

        } elseif (Mage::helper('settings_xpc')->isConfigured()) {

            $tabId = $this->tabIDs[Cdev_XPaymentsConnector_Helper_Settings_Data::TAB_PAYMENT_METHODS];

        } else {

            $tabId = $this->tabIDs[Cdev_XPaymentsConnector_Helper_Settings_Data::TAB_WELCOME];
        }

        return $tabId;
    }
}
