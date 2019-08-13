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
 * Grid column rendering for'X-Payments order url'
 */
class Cdev_XPaymentsConnector_Block_Adminhtml_Customer_Edit_Renderer_Txnid extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Action
{
    /**
     * Render row
     *
     * @param Varien_Object $row
     *
     * @return string
     */
    public function render(Varien_Object $row)
    {
        $txnid = $row->getData($this->getColumn()->getIndex());

        $params = array(
            'target' => 'payment',
            'txnid'  => $txnid,
        );

        $url = Mage::helper('settings_xpc')->getAdminUrl()
            . http_build_query($params);

        return '<a href="'. $url . '" target="_blank" >' . $txnid . '</a>';
    }
}
