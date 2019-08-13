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
 * Grid column rendering for'Card Type'
 */

class Cdev_XPaymentsConnector_Block_Adminhtml_Customer_Edit_Renderer_Cardnumber extends  Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Action
{

    public function render(Varien_Object $row){

        $row = $row->getData();
        $cardNumber = $this->__('%s******%s',
            $row['first6'],
            $row['last_4_cc_num']
        );
        if (!is_null($row['expire_month']) && !is_null($row['expire_year'])) {
            $cardNumber .= $this->__(' ( %s/%s )', $row['expire_month'], $row['expire_year']);
        }
        return "<span>$cardNumber</span>";
    }
}
