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

class Cdev_XPaymentsConnector_Model_Quote extends Mage_Sales_Model_Quote
{
    /**
     * Slot index of the XPC payment method
     */
    private $xpcSlot = null;

    /**
     * Set slot index of the XPC payment method 
     *
     * @param int $xpcSlot Slot index of the XPC payment method
     *
     * @return Cdev_XPaymentsConnector_Model_Quote 
     */
    public function setXpcSlot($xpcSlot)
    {
        $xpcSlot = Mage::helper('settings_xpc')->checkXpcSlot($xpcSlot);

        $this->xpcSlot = $xpcSlot;

        return $this;
    }

    /**
     * Get slot index of the XPC payment method
     *
     * @return int 
     */
    public function getXpcSlot()
    {
        return Mage::helper('settings_xpc')->checkXpcSlot($this->xpcSlot);
    }

    /**
     * Get Quote xpc data
     *
     * @return Cdev_XPaymentsConnector_Model_Quote_XpcData
     */
    public function getXpcData()
    {
        $model = Mage::getModel('xpaymentsconnector/quote_xpcdata')
            ->getCollection()
            ->addFieldToFilter('quote_id', $this->getEntityId())
            ->addFieldToFilter('xpc_slot', $this->getXpcSlot())
            ->getFirstItem();

        if (!$model->getQuoteId()) {
            // Fill "primary key" for the new entity
            $model->setQuoteId($this->getEntityId())
                ->setXpcSlot($this->getXpcSlot())
                ->save();
        }

        return $model;
    }

    /**
     * Check if this quote is for backend order or not
     *
     * @return bool
     */
    public function isBackendOrderQuote()
    {
        return (bool)$this->getXpcData()->getData('backend_orderid');
    }

    /**
     * Get backend order from Quote (if any)
     *
     * @return Mage_Sales_Model_Order
     */
    public function getBackendOrder()
    {
        $orderId = $this->getXpcData()->getData('backend_orderid');

        return Mage::getModel('sales/order')->load($orderId, 'increment_id');
    }

    /**
     * Get quote or its backend order customer
     *
     * @return Mage_Customer_Model_Customer 
     */
    public function getCustomer()
    {
        if ($this->isBackendOrderQuote()) {

            $customer = Mage::getModel('customer/customer')->load(
                $this->getBackendOrder()->getCustomerId()
            );

        } else {

            $customer = parent::getCustomer();
        }

        return $customer;
    }

    /**
     * Get all visible items for quote or its backend order
     *
     * @return array
     */
    public function getAllVisibleItems()
    {
        if ($this->isBackendOrderQuote()) {

            $items = $this->getBackendOrder()->getAllVisibleItems();

        } else {

            $items = parent::getAllVisibleItems();
        }

        return $items;    
    }

    /**
     * Get all items for quote or its backend order
     *
     * @return array
     */
    public function getAllItems()
    {
        if ($this->isBackendOrderQuote()) {

            $items = $this->getBackendOrder()->getAllItems();

        } else {

            $items = parent::getAllItems();
        }

        return $items;
    }

    /**
     * Get quote or its backend order item for the recurring product. If any.
     *
     * @return Mage_Sales_Model_Quote_Item or false
     */
    public function getRecurringItem()
    {
        $result = false;

        foreach ($this->getAllItems() as $item) {

            if (
                $item
                && $item->getProduct()
                && $item->getProduct()->getIsRecurring()
            ) {

                $result = $item;
                break;
            }
        }

        return $result;
    }

    /**
     * Get quote or its backend order currency
     *
     * @return array
     */
    public function getCurrency()
    {
        if ($this->isBackendOrderQuote()) {

            $currency = $this->getBackendOrder()->getData('order_currency_code');

        } else {

            $currency = $this->getData('quote_currency_code');
        }

        return $currency;
    }

    /**
     * Check if billing address should be saved in address book
     *
     * @return bool
     */
    public function isSaveBillingAddressInAddressBook()
    {
        // Grab data saved at checkout
        $data = @unserialize($this->getXpcData()->getData('checkout_data'));

        return $this->getBillingAddress()->getData('save_in_address_book')
            || !empty($data['billing']['save_in_address_book']);
    }

    /**
     * Check if shipping address should be saved in address book
     *
     * @return bool
     */
    public function isSaveShippingAddressInAddressBook()
    {
        // Grab data saved at checkout
        $data = @unserialize($this->getXpcData()->getData('checkout_data')); 

        return (
            $this->getShippingAddress()->getData('save_in_address_book')
            && !$this->getShippingAddress()->getData('same_as_billing')
        ) || (
            !empty($data['shipping']['save_in_address_book'])
            && empty($data['shipping']['same_as_billing'])
        );
    }

    /**
     * Get all addresses
     *
     * @return array
     */
    public function getAllAddresses()
    {
        $addresses = parent::getAllAddresses();

        if (
            count($addresses) > 2
            && (
                Mage::helper('settings_xpc')->checkFirecheckoutModuleEnabled()
                || Mage::helper('settings_xpc')->checkIwdModuleEnabled()
            )
        ) {

            // Remove "extra" addresses for the Firecheckout module
            // See XP-659
            // And for the IWD checkout module/Venedor theme

            $shippingFound = $billingFound = false;

            foreach ($addresses as $key => $address) {

                if ($address::TYPE_BILLING == $address->getAddressType()) {

                    if (!$billingFound) {
                        $billingFound = true;
                    } else {
                        unset($addresses[$key]);
                    }

                } elseif ($address::TYPE_SHIPPING == $address->getAddressType()) {

                    if (!$shippingFound) {
                        $shippingFound = true;
                    } else {
                        unset($addresses[$key]);
                    }

                } else {

                    unset($addresses[$key]);
                }
            }
        }

        return $addresses;
    }

    /**
     * Save exception message to the log and throw it
     *    
     * @param string $message Message
     * @param string $paramm Some parameter
     *
     * @return void
     */
    private function throwException($message, $param = null)
    {
        Mage::helper('xpaymentsconnector')->writeLog($message, $param);

        throw new Exception($message);
    }
}
