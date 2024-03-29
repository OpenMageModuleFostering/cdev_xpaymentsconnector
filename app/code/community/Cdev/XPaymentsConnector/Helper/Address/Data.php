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
 * Helper for addresses 
 */

class Cdev_XPaymentsConnector_Helper_Address_Data extends Cdev_XPaymentsConnector_Helper_Abstract 
{
    /**
     * Placeholder for empty email (something which will pass X-Payments validation)
     */
    const EMPTY_USER_EMAIL = 'user@example.com';

    /**
     * Placeholder for not available cart data
     */
    const NOT_AVAILABLE = 'N/A';

    /**
     * Billing and shipping address names
     */
    const BILLING_ADDRESS = 'Billing';
    const SHIPPING_ADDRESS = 'Shipping';

    /**
     * Prepare state
     *
     * @param array $data Address data
     *
     * @return string
     */
    private function prepareState($data)
    {
        $state = self::NOT_AVAILABLE;

        if (!empty($data['region_id'])) {

            $region = Mage::getModel('directory/region')->load($data['region_id']);

            if (
                $region
                && $region->getCode()
            ) {
                $state = $region->getCode();
            }
        }

        return $state;
    }

    /**
     * Prepare street (Address lines 1 and 2)
     *
     * @param array $data Address data
     *
     * @return string
     */
    private function prepareStreet($data)
    {
        $street = self::NOT_AVAILABLE;

        if (!empty($data['street'])) {

            $street = $data['street'];

            if (is_array($street)) {
                $street = array_filter($street);
                $street = implode("\n", $street);
            }
        }

        return $street;
    }

    /**
     * Prepare address for initial payment request (internal)
     *
     * @param Cdev_XPaymentsConnector_Model_Quote $quote Quote
     * @param Mage_Customer_Model_Customer $customer Customer
     * @param $type Address type, Billing or Shipping
     *
     * @return array
     */
    private function prepareAddress(Cdev_XPaymentsConnector_Model_Quote $quote = null, Mage_Customer_Model_Customer $customer = null, $type = self::BILLING_ADDRESS) 
    {
        $getAddress = 'get' . $type . 'Address';
        $getDefaultAddress = 'getDefault' . $type . 'Address';

        $customerAddress = $customerDefaultAddress = $quoteAddress = $orderAddress = array();

        if ($quote) {

            $customer = $quote->getCustomer();

            if ($quote->$getAddress()) {
                $quoteAddress = $quote->$getAddress()->getData();
            }

            if ($quote->isBackendOrderQuote()) {
                $orderAddress = $quote->getBackendOrder()->$getAddress()->getData();
            }
        }

        if ($customer) {

            $customerAddress = $customer->getData();

            if ($customer->$getDefaultAddress()) {
                $customerDefaultAddress = $customer->$getDefaultAddress()->getData();
            }
        }

        $data = array_merge(
            array_filter($customerAddress),
            array_filter($customerDefaultAddress),
            array_filter($quoteAddress),
            array_filter($orderAddress)
        );

        $result = array(
            'firstname' => !empty($data['firstname']) ? $data['firstname'] : self::NOT_AVAILABLE,
            'lastname'  => !empty($data['lastname']) ? $data['lastname'] : self::NOT_AVAILABLE,
            'address'   => $this->prepareStreet($data),
            'city'      => !empty($data['city']) ? $data['city'] : self::NOT_AVAILABLE,
            'state'     => $this->prepareState($data),
            'country'   => !empty($data['country_id']) ? $data['country_id'] : 'XX', // WA fix for MySQL 5.7 with strict mode
            'zipcode'   => !empty($data['postcode']) ? $data['postcode'] : self::NOT_AVAILABLE,
            'phone'     => !empty($data['telephone']) ? $data['telephone'] : '',
            'fax'       => '',
            'company'   => '',
            'email'     => !empty($data['email']) ? $data['email'] : self::EMPTY_USER_EMAIL,
        );

        return $result;
    }

    /**
     * Prepare billing address from quote for initial payment request
     *
     * @param Mage_Sales_Model_Quote $quote Quote
     *
     * @return array
     */
    public function prepareQuoteBillingAddress(Cdev_XPaymentsConnector_Model_Quote $quote)
    {
        return $this->prepareAddress($quote, null, self::BILLING_ADDRESS);
    }

    /**
     * Prepare shipping address from quote for initial payment request
     *
     * @param Cdev_XPaymentsConnector_Model_Quote $quote Quote
     *
     * @return array
     */
    public function prepareQuoteShippingAddress(Cdev_XPaymentsConnector_Model_Quote $quote)
    {
        return $this->prepareAddress($quote, null, self::SHIPPING_ADDRESS);
    }

    /**
     * Prepare billing address from customer for initial payment request
     *
     * @param Mage_Sales_Model_Customer $customer Customer
     *
     * @return array
     */
    public function prepareCustomerBillingAddress(Mage_Customer_Model_Customer $customer)
    {
        return $this->prepareAddress(null, $customer, self::BILLING_ADDRESS);
    }

    /**
     * Prepare shipping address from customer for initial payment request
     *
     * @param Mage_Sales_Model_Customer $customer Customer
     *
     * @return array
     */
    public function prepareCustomerShippingAddress(Mage_Customer_Model_Customer $customer)
    {
        return $this->prepareAddress(null, $customer, self::SHIPPING_ADDRESS);
    }
}
