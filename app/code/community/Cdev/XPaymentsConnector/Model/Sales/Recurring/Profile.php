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
 * @author     Valerii Demidov
 * @category   Cdev
 * @package    Cdev_XPaymentsConnector
 * @copyright  (c) Qualiteam Software Ltd. <info@qtmsoft.com>. All rights reserved.
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Cdev_XPaymentsConnector_Model_Sales_Recurring_Profile extends Mage_Sales_Model_Recurring_Profile {

    /**
     * Determine nearest possible profile start date
     *
     * @param Zend_Date $minAllowed
     * @return Mage_Payment_Model_Recurring_Profile
     */
    public function setNearestStartDatetime(Zend_Date $minAllowed = null)
    {
        $paymentMethodCode = $this->getMethodCode();

        $isXpaymentMethod = Mage::helper("xpaymentsconnector")->isXpaymentsMethod($paymentMethodCode);
        if($isXpaymentMethod){
            $date = $minAllowed;
            if (!$date) {
                $date = new Zend_Date(time());
            }
            $this->setStartDatetime($date->toString(Varien_Date::DATETIME_INTERNAL_FORMAT));
            return $this;

        }
        return parent::setNearestStartDatetime($minAllowed);
    }


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
        foreach ($itemInfoObjects as $itemInfo) {
            $item = $this->_getItem($itemInfo);
            $billingAmount += $item->getPrice();
            $shippingAmount += $item->getShippingAmount();
            $taxAmount += $item->getTaxAmount();
            $discountAmount -= $itemInfo->getDiscountAmount();
            $weight += $item->getWeight();
            if (!$item->getIsVirtual()) {
                $isVirtual = 0;
            }
            $items[] = $item;
        }

        $grandTotal = $billingAmount + $shippingAmount + $taxAmount + $discountAmount;

        $order = Mage::getModel('sales/order');

        $billingAddressInfo = $this->getBillingAddressInfo();
        // add check for recurring profile order
        if(!is_array($billingAddressInfo)){
            $billingAddressInfo = unserialize($billingAddressInfo);
        }
        $billingAddress = Mage::getModel('sales/order_address')
            ->setData($billingAddressInfo)
            ->setId(null);

        $shippingInfo = $this->getShippingAddressInfo();
        // add check for recurring profile order
        if(!is_array($shippingInfo)){
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

        // add check for recurring profile order
        if(!is_array($orderInfo)){
            $orderInfo = unserialize($orderInfo);
        }

        foreach ($transferDataKays as $key) {
            if (isset($orderInfo[$key])) {
                $order->setData($key, $orderInfo[$key]);
            } elseif (isset($shippingInfo[$key])) {
                $order->setData($key, $shippingInfo[$key]);
            }
        }

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
            ->setTotalQtyOrdered($orderInfo['items_qty'])
            ->setBillingAddress($billingAddress)
            ->setShippingAddress($shippingAddress)
            ->setPayment($payment);

        foreach ($items as $item) {
            $order->addItem($item);
        }

        return $order;
    }


    /**
     * Check whether the workflow allows to suspend the profile
     *
     * @return bool
     */
    public function canSuspend()
    {
        $paymentMethodCode = $this->getMethodCode();
        $isXpaymentMethod = Mage::helper("xpaymentsconnector")->isXpaymentsMethod($paymentMethodCode);
        if($isXpaymentMethod){
            return false;
        }else{
           return parent::canSuspend();
        }

    }

    /**
     * Create and return new order item based on profile item data and $itemInfo
     * for regular payment
     *
     * @param Varien_Object $itemInfo
     * @return Mage_Sales_Model_Order_Item
     */
    protected function _getRegularItem($itemInfo)
    {
        $price = $itemInfo->getPrice() ? $itemInfo->getPrice() : $this->getBillingAmount();
        $shippingAmount = $itemInfo->getShippingAmount() ? $itemInfo->getShippingAmount() : $this->getShippingAmount();
        $taxAmount = $itemInfo->getTaxAmount() ? $itemInfo->getTaxAmount() : $this->getTaxAmount();
        $orderItemInfo = $this->getOrderItemInfo();
        if(!is_array($orderItemInfo)){
            $orderItemInfo = unserialize($orderItemInfo);
        }

        $item = Mage::getModel('sales/order_item')
            ->setData($orderItemInfo)
            ->setQtyOrdered($orderItemInfo['qty'])
            ->setBaseOriginalPrice($orderItemInfo['price'])
            ->setPrice($price)
            ->setBasePrice($price)
            ->setRowTotal($price)
            ->setBaseRowTotal($price)
            ->setTaxAmount($taxAmount)
            ->setShippingAmount($shippingAmount)
            ->setId(null);
        return $item;
    }




}
