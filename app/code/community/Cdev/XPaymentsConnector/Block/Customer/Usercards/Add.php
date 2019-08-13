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
 * Block to add new cards to the list (frontend)
 */
class Cdev_XPaymentsConnector_Block_Customer_Usercards_Add extends Mage_Core_Block_Template
{
    /**
     * Error message
     */
    private $error = null;

    /**
     * Default billing address
     */
    private $defaultBillingAddress = null;

    /**
     * Get current customer profile
     *
     * @return Mage_Customer_Model_Customer
     */
    private function getCustomer()
    {
        return Mage::getSingleton('customer/session')->getCustomer();
    }

    /**
     * Set error message
     *
     * @param string $error Error message
     *
     * @return void
     */
    public function setError($error)
    {
        $this->error = $error;
    }

    /**
     * Get error message
     *
     * @return string
     */
    public function getError()
    {
        return $this->error;
    }

    /**
     * Get default biling addrress
     *
     * @return
     */
    private function getDefaultBillingAddress()
    {
        if (is_null($this->defaultBillingAddress)) {
            $this->defaultBillingAddress = $this->getCustomer()->getDefaultBillingAddress();
        }

        return $this->defaultBillingAddress;
    }

    /**
     * Get default address HTML code
     *
     * @return string
     */
    public function getDefaultAddressHtml()
    {
        if ($this->getDefaultBillingAddress()) {
            $address = $this->getDefaultBillingAddress()->format('html');
        } else {
            $address = '';
        }        

        return $address;
    }

    /**
     * Get iframe URL
     *
     * @return string
     */
    public function getIframeUrl()
    {
        $params = array(
            '_secure' => !Mage::helper('settings_xpc')->getXpcConfig('xpay_force_http'),
        );

        return Mage::getUrl('xpaymentsconnector/customer/iframe', $params);
    }

    /**
     * Get "Back" URL (payment cards page)
     *
     * @return string
     */
    public function getBackUrl()
    {
        if ($this->getRefererUrl()) {

            $url = $this->getRefererUrl();

        } else {

            $params = array(
                '_secure' => !Mage::helper('settings_xpc')->getXpcConfig('xpay_force_http'),
            );

            $url = Mage::getUrl('xpaymentsconnector/customer/usercards', $params);
        }

        return $url;
    }

    /**
     * Get edit address URL
     *
     * @return string
     */
    public function getEditAddressUrl()
    {
        if ($this->getDefaultBillingAddress()) {

            $params = array(
                '_secure' => !Mage::helper('settings_xpc')->getXpcConfig('xpay_force_http'), 
                'id'      => $this->getDefaultBillingAddress()->getId(),
            );

            $url = Mage::getUrl('customer/address/edit', $params);

        } else {

            $url = Mage::getUrl('customer/address/edit');
        }

        return $url;
    }

    /**
     * Get JSON data for X-Payments iframe
     *
     * @return string
     */
    public function getXpcData()
    {
        $data = array(
            'url' => array(
                'redirect'      => $this->getIframeUrl(),
                'changeAddress' => $this->getEditAddressUrl(),
                'paymentCards'  => $this->getBackUrl(),
            ),
            'origins' => Mage::helper('settings_xpc')->getAllowedOrigins(),
        );

        return json_encode($data, JSON_FORCE_OBJECT);
    }
}
