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
 * Add new card block
 */
class Cdev_XPaymentsConnector_Block_Adminhtml_Usercards_Add extends Mage_Adminhtml_Block_Template
{
    /** 
     * Current customer profile
     */
    private $customer = null;

    /**
     * Error message
     */
    private $error = null;

    /**
     * Current address ID
     */
    private $addressId = null;

    /**
     * Address list
     */
    private $addressList = null;

    /**
     * Set current customer profile
     *
     * @param Mage_Customer_Model_Customer 
     *
     * @return void
     */
    public function setCustomer($customer)
    {
        $this->customer = $customer;
    }

    /**
     * Get current customer profile 
     *
     * @return Mage_Customer_Model_Customer 
     */
    public function getCustomer()
    {
        return $this->customer;
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
     * Set current customer profile
     *
     * @param int $addressId Billing address ID
     *
     * @return void
     */
    public function setAddressId($addressId)
    {
        if (!$this->isEmptyAddressList()) {

            if (!in_array($addressId, array_keys($this->getAddressList()))) {
                $addressId = key($this->getAddressList());
            }

            $this->addressId = $addressId;
        }
    }

    /**
     * Get current address ID 
     *
     * @return int
     */
    public function getAddressId()
    {
        return $this->addressId;
    }

    /**
     * Check if there is no addresses in customer's profile
     *
     * @return bool
     */
    public function isEmptyAddressList()
    {
        return empty($this->getAddressList());
    }

    /**
     * Check if there is only one address in customer's profile
     *
     * @return bool
     */
    public function isSingleAddress()
    {
        return 1 == count($this->getAddressList());
    }

    /**
     * Get single address from the customer's profile
     *
     * @return string
     */
    public function getSingleAddress()
    {
        $addresses = $this->getAddressList();

        return array_shift($addresses);
    }

    /**
     * Get list of all addresses from the customer's profile
     *
     * @return array
     */
    public function getAddressList()
    {
        if (!is_null($this->addressList)) {
            return $this->addressList;
        }

        $this->addressList = array();

        foreach ($this->getCustomer()->getAddresses() as $address) {

            $item = array(
                $address->getData('firstname') . ' ' . $address->getData('lastname'),
                $address->getData('postcode') . ' '. $address->getData('street') . ' ' . $address->getData('city'),
                $address->getData('region'),
                Mage::getModel('directory/country')->load($address->getData('country_id'))->getName()
            );

            $this->addressList[$address->getEntityId()] = implode(', ', $item);
        }

        return $this->addressList;
    }

    /** 
     * Check if this address ID used currently
     *
     * @param int $addressId Address ID
     *
     * @return bool
     */
    public function isCurrentAddress($addressId)
    {
        return $this->getAddressId() == $addressId;
    }

    /**
     * Get URL of the address book
     *
     * @return string
     */
    public function getAddressBookUrl()
    {
        $params = array(
            'id'  => $this->getCustomer()->getId(),
            'tab' => 'customer_info_tabs_addresses',
        );

        return Mage::helper('adminhtml')->getUrl('adminhtml/customer/edit', $params);
    }

    /**
     * Get change address URL
     *
     * @return string
     */
    public function getChangeAddressUrl()
    {
        $params = array(
            'customer_id'  => $this->getCustomer()->getId(),
            'address_id' => 'ADDRESSID',
        );

        return Mage::helper('adminhtml')->getUrl('adminhtml/addnewcard/index', $params);
    }

    /**
     * Get iframe URL
     *
     * @return string
     */
    public function getIframeUrl()
    {
        $params = array(
            'customer_id'  => $this->getCustomer()->getId(),
            'address_id' => $this->getAddressId(),
        );

        return Mage::helper('adminhtml')->getUrl('adminhtml/addnewcard/iframe', $params);
    }

    /**
     * Get URL for payment cards tab in the profile
     *
     * @return string
     */
    public function getPaymentCardsUrl()
    {
        $params = array(
            'id'  => $this->getRequest()->getParam('customer_id'),
            'tab' => 'customer_edit_tab_usercards',
        );
        
        return Mage::helper('adminhtml')->getUrl('adminhtml/customer/edit', $params);
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
                'changeAddress' => $this->getChangeAddressUrl(),
                'paymentCards'  => $this->getPaymentCardsUrl(),
            ),
            'origins' => Mage::helper('settings_xpc')->getAllowedOrigins(),
        );

        return json_encode($data, JSON_FORCE_OBJECT);
    }

    /**
     * Constructor
     *  
     * @return void
     */
    protected function _construct()
    {
        $this->setTemplate('xpaymentsconnector/usercards/add.phtml');
    }
}
