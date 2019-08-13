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
 * Payment success block
 * 
 * @package Cdev_XPaymentsConnector
 * @see     ____class_see____
 * @since   1.0.0
 */
class Cdev_XPaymentsConnector_Block_Success extends Mage_Core_Block_Abstract
{
    /**
     * Get block contecnt as HTML
     *
     * @return string
     * @access protected
     * @see    ____func_see____
     * @since  1.0.0
     */
    protected function _toHtml()
    {
        $styleUrl = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_JS)."xpayment/settings.css";

        $successUrl = Mage::getUrl('*/*/success', array('_nosid' => true));
        $successUrlHtml = '<a href="'.$successUrl.'">'.$this->__('here').'</a>';
        return '<html>'
			. '<head>'
            ."<link href=".$styleUrl." type='text/css' rel='stylesheet'> "
            . '<meta http-equiv="refresh" content="0; URL=' . $successUrl . '" />'
			. '</head>'
            ."<div class='b-loader-wrap'><div id='ajax-loader'></div></div>"
            . '<body>'
            ."<div id='wait-message'>"
            . '<p><strong>' . $this->__('Your payment has been successfully processed by our shop system.') . '</strong></p>'
            . '<p>' . $this->__('Please click %s if you are not redirected automatically.',$successUrlHtml) . '</p>'
             ."</div>"
            . '</body>'
			. '</html>';
    }
}
