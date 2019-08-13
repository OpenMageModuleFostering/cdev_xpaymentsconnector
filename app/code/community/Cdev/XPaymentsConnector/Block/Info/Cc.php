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
 * @author     Qualiteam Software info@qtmsoft.com
 * @category   Cdev
 * @package    Cdev_XPaymentsConnector
 * @copyright  (c) 2010-2016 Qualiteam software Ltd <info@x-cart.com>. All rights reserved
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Payment additional info block
 * 
 * @package Cdev_XPaymentsConnector
 * @see     ____class_see____
 * @since   1.0.0
 */
class Cdev_XPaymentsConnector_Block_Info_Cc extends Mage_Payment_Block_Info
{
    /**
     * Constructor
     * 
     * @return void
     * @access protected
     * @see    ____func_see____
     * @since  1.0.0
     */
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('xpaymentsconnector/info/cc.phtml');
    }

    /**
     * Get payment method code 
     * 
     * @return string
     * @access public
     * @see    ____func_see____
     * @since  1.0.0
     */
    public function getMethodCode()
    {
        return $this->getInfo()->getMethodInstance()->getCode();
    }

    /**
     * Get PDF version
     * 
     * @return string
     * @access public
     * @see    ____func_see____
     * @since  1.0.0
     */
    public function toPdf()
    {
        $this->setTemplate('xpaymentsconnector/pdf/info.phtml');

        return $this->toHtml();
    }

    /**
     * Get X-Payments payment URL 
     * 
     * @return string
     * @access public
     * @see    ____func_see____
     * @since  1.0.0
     */
    public function getXPaymentURL()
    {
        return preg_replace('/\/+$/Ss', '', $this->getMethod()->getConfig('xpay_url'))
            . '/admin.php?target=payment&amp;txnid=' . $this->getInfo()->getLastTransId();
    }

    /**
     * Get masked card data as a string
     *
     * @return string
     */
    public function getCardData()
    {
        $order = $this->getInfo()->getMethodInstance()->getOrder();

        $data = unserialize($order->getXpCardData());

        $result = Mage::helper('xpaymentsconnector')->prepareCardDataString($data, true);

        return $result;
    }

}
