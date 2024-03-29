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

class Cdev_XPaymentsConnector_Model_Sales_Recurring_Profile extends Mage_Sales_Model_Recurring_Profile 
{
    /**
     * Finished state
     */
    const STATE_FINISHED    = 'finished';

    /**
     * Initialize the workflow reference
     *
     * @return void
     */
    protected function _initWorkflow()
    {
        if (is_null($this->_workflow)) {

            $this->_workflow = array(
                'unknown'   => array('pending', 'active', 'suspended', 'canceled'),
                'pending'   => array('active', 'canceled'),
                'active'    => array('suspended', 'canceled', 'finished'),
                'suspended' => array('active', 'canceled'),
                'canceled'  => array(),
                'expired'   => array(),
                'finished'  => array()
            );
        }
    }

    /**
     * Create and return new order item based on profile item data and $itemInfo
     * for regular payment
     *
     * @param Varien_Object $itemInfo
     *
     * @return Mage_Sales_Model_Order_Item
     */
    protected function _getRegularItem($itemInfo)
    {
        $price = $itemInfo->getPrice() 
            ? $itemInfo->getPrice() 
            : $this->getBillingAmount();

        $rowTotal = $price;
        $baseRowTotal = $price;
        $rowTotalInclTax = $price;
        $baseRowTotalInclTax = $price;
        $originalPrice = $price;

        if (!is_null($itemInfo->getRowTotal())) {
            $rowTotal = $itemInfo->getRowTotal();
        }

        if (!is_null($itemInfo->getBaseRowTotal())) {
            $baseRowTotal = $itemInfo->getBaseRowTotal();
        }

        if (!is_null($itemInfo->getRowTotalInclTax())) {
            $rowTotalInclTax = $itemInfo->getRowTotalInclTax();
        }

        if (!is_null($itemInfo->getBaseRowTotalInclTax())) {
            $baseRowTotalInclTax = $itemInfo->getBaseRowTotalInclTax();
        }

        if (!is_null($itemInfo->getOriginalPrice())) {
            $originalPrice = $itemInfo->getOriginalPrice();
        }

        $shippingAmount = $itemInfo->getShippingAmount() 
            ? $itemInfo->getShippingAmount() 
            : $this->getShippingAmount();

        $taxAmount = $itemInfo->getTaxAmount() 
            ? $itemInfo->getTaxAmount() 
            : $this->getTaxAmount();

        $orderItemInfo = $this->getOrderItemInfo();
        if (!is_array($orderItemInfo)) {
            $orderItemInfo = unserialize($orderItemInfo);
        }

        $item = Mage::getModel('sales/order_item')
            ->setData($orderItemInfo)
            ->setQtyOrdered($orderItemInfo['qty'])
            ->setBaseOriginalPrice($orderItemInfo['price'])
            ->setPrice($price)
            ->setBasePrice($price)
            ->setRowTotal($rowTotal)
            ->setBaseRowTotal($baseRowTotal)
            ->setRowTotalInclTax($rowTotalInclTax)
            ->setBaseRowTotalInclTax($baseRowTotalInclTax)
            ->setOriginalPrice($originalPrice)
            ->setTaxAmount($taxAmount)
            ->setShippingAmount($shippingAmount)
            ->setId(null);

        return $item;
    }

    /**
     * Determine nearest possible profile start date
     *
     * @param Zend_Date $minAllowed
     *
     * @return Mage_Payment_Model_Recurring_Profile
     */
    public function setNearestStartDatetime(Zend_Date $minAllowed = null)
    {
        if (Mage::helper('settings_xpc')->isXpcMethod($this->getMethodCode())) {

            $date = is_null($minAllowed)
                ? new Zend_Date(time())
                : $minAllowed;
            
            $this->setStartDatetime($date->toString(Varien_Date::DATETIME_INTERNAL_FORMAT));

        } else {

            parent::setNearestStartDatetime($minAllowed);
        }

        return $this;
    }

    /**
     * Create order
     *
     * @return Mage_Sales_Model_Order
     */
    public function createOrder()
    {
        $items = array();
        $itemInfoObjects = func_get_args();

        $billingAmount = 0;
        $shippingAmount = 0;
        $taxAmount = 0;
        $isVirtual = 1;
        $weight = 0;
        $discountAmount = 0;
        $qty = 0;

        foreach ($itemInfoObjects as $itemInfo) {

            $item = $this->_getItem($itemInfo);

            $price = $item->getPrice();
            if(!is_null($itemInfo->getRowTotal())){
                $price = $itemInfo->getRowTotal();
            }

            $billingAmount += $price;
            $shippingAmount += $itemInfo->getShippingAmount();

            $taxAmount += $item->getTaxAmount();

            if ($itemInfo->getQty()) {
                $qty = $itemInfo->getQty();
            }

            $weight += $item->getWeight();

            if (!$item->getIsVirtual()) {
                $isVirtual = 0;
            }

            if (!is_null($itemInfo->getDiscountAmount())) {
                $discountAmount -= $itemInfo->getDiscountAmount();
                $item->setOriginalDiscountAmount($itemInfo->getDiscountAmount());
                $item->setBaseOriginalDiscountAmount($itemInfo->getDiscountAmount());
                $item->setRecurringDiscount($itemInfo->getDiscountAmount());
                $item->setDiscountAmount($itemInfo->getDiscountAmount());
                $item->setBaseDiscountAmount($itemInfo->getDiscountAmount());
            }

            $items[] = $item;
        }

        $grandTotal = $billingAmount + $shippingAmount + $taxAmount + $discountAmount;

        $order = Mage::getModel('sales/order');

        $billingAddressInfo = $this->getBillingAddressInfo();
        if (!is_array($billingAddressInfo)) {
            $billingAddressInfo = unserialize($billingAddressInfo);
        }
        $billingAddress = Mage::getModel('sales/order_address')
            ->setData($billingAddressInfo)
            ->setId(null);

        $shippingInfo = $this->getShippingAddressInfo();
        if (!is_array($shippingInfo)) {
            $shippingInfo = unserialize($shippingInfo);
        }
        $shippingAddress = Mage::getModel('sales/order_address')
            ->setData($shippingInfo)
            ->setId(null);

        $payment = Mage::getModel('sales/order_payment')
            ->setMethod($this->getMethodCode());

        $transferDataKays = array(
            'store_id',             'store_name',           'customer_id',          'customer_email',
            'customer_firstname',   'customer_lastname',    'customer_middlename',  'customer_prefix',
            'customer_suffix',      'customer_taxvat',      'customer_gender',      'customer_is_guest',
            'customer_note_notify', 'customer_group_id',    'customer_note',        'shipping_method',
            'shipping_description', 'base_currency_code',   'global_currency_code', 'order_currency_code',
            'store_currency_code',  'base_to_global_rate',  'base_to_order_rate',   'store_to_base_rate',
            'store_to_order_rate'
        );

        $orderInfo = $this->getOrderInfo();
        if (!is_array($orderInfo)) {
            $orderInfo = unserialize($orderInfo);
        }

        foreach ($transferDataKays as $key) {

            if (isset($orderInfo[$key])) {

                $order->setData($key, $orderInfo[$key]);

            } elseif (isset($shippingInfo[$key])) {

                $order->setData($key, $shippingInfo[$key]);
            }
        }

        $qty = ($qty)?$qty:$orderInfo['items_qty'];

        $order->setStoreId($this->getStoreId())
            ->setState(Mage_Sales_Model_Order::STATE_NEW)
            ->setBaseToOrderRate($orderInfo['base_to_quote_rate'])
            ->setStoreToOrderRate($orderInfo['store_to_quote_rate'])
            ->setOrderCurrencyCode($orderInfo['quote_currency_code'])
            ->setBaseSubtotal($billingAmount)
            ->setSubtotal($billingAmount)
            ->setBaseShippingAmount($shippingAmount)
            ->setShippingAmount($shippingAmount)
            ->setBaseTaxAmount($taxAmount)
            ->setTaxAmount($taxAmount)
            ->setDiscountAmount($discountAmount)
            ->setBaseDiscountAmount($discountAmount)
            ->setBaseGrandTotal($grandTotal)
            ->setGrandTotal($grandTotal)
            ->setIsVirtual($isVirtual)
            ->setWeight($weight)
            ->setTotalQtyOrdered($qty)
            ->setBillingAddress($billingAddress)
            ->setShippingAddress($shippingAddress)
            ->setPayment($payment);

        foreach ($items as $item) {
            $order->addItem($item);
        }

        return $order;
    }

    /**
     * Check whether the workflow allows to activate the profile
     *
     * @return bool
     */
    public function canActivate()
    {
        return false;
    }

    /**
     * Check whether the workflow allows to suspend the profile
     *
     * @return bool
     */
    public function canSuspend()
    {
        if (Mage::helper('settings_xpc')->isXpcMethod($this->getMethodCode())) {
            $result = false;
        } else {
           $result = parent::canSuspend();
        }

        return $result;
    }

    /**
     * Set state finished for current profile
     *
     * @return void
     */
    public function finished()
    {
        $this->_checkWorkflow(self::STATE_FINISHED, false);

        $this->setNewState(self::STATE_FINISHED);

        $this->getMethodInstance()->updateRecurringProfileStatus($this);

        $this->setState(self::STATE_FINISHED)->save();
    }

    /**
     * Get state label based on the code
     *
     * @param string $state
     *
     * @return string
     */
    public function getStateLabel($state)
    {
        $states = $this->getAllStates();

        return !empty($states[$state])
            ? $states[$state]
            : $state;
    }

    /**
     * Get all available states
     *
     * @param bool $withLabels
     *
     * @return array
     */
    public function getAllStates($withLabels = true)
    {
        $states = array(
            self::STATE_UNKNOWN   => Mage::helper('sales')->__('Not Initialized'), 
            self::STATE_PENDING   => Mage::helper('sales')->__('Pending'), 
            self::STATE_ACTIVE    => Mage::helper('sales')->__('Active'),
            self::STATE_SUSPENDED => Mage::helper('sales')->__('Suspended'),
            self::STATE_CANCELED  => Mage::helper('xpaymentsconnector')->__('Finished'), 
            self::STATE_EXPIRED   => Mage::helper('sales')->__('Canceled'), 
            self::STATE_FINISHED  => Mage::helper('sales')->__('Expired'),
        );

        return $withLabels
            ? $states
            : array_keys($states);
    }
}
