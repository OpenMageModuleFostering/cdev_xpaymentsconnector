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
 * Helper for cart 
 */

class Cdev_XPaymentsConnector_Helper_Cart_Data extends Cdev_XPaymentsConnector_Helper_Abstract 
{
    /**
     * Prepare totals element: shipping, tax, discount
     *
     * @param array $totals Cart totals
     * @param string $key Totals element key
     *
     * @return string
     */
    private function prepareTotalsElement($totals, $key)
    {
        $value = 0;

        if (
            isset($totals[$key])
            && is_object($totals[$key])
            && method_exists($totals[$key], 'getValue')
        ) {
            $value = abs($totals[$key]->getValue());
        }

        return $this->preparePrice($value);
    }

    /**
     * Prepare simple items from quote for initial payment request
     *
     * @param Cdev_XPaymentsConnector_Model_Quote $quote
     * @param array &$result
     *
     * @return void
     */
    protected function prepareSimpleItems(Cdev_XPaymentsConnector_Model_Quote $quote, &$result)
    {
        if ($quote->isBackendOrderQuote()) {

            $order = $quote->getBackendOrder();

            $result['totalCost'] = $this->preparePrice($order->getGrandTotal());
            $result['shippingCost'] = $this->preparePrice($order->getShippingSmount());
            $result['taxCost'] = $this->preparePrice($order->getTaxAmount());
            $result['discount'] = $this->preparePrice($order->getDiscountAmount());
        
        } else {

            $quote->collectTotals();
            $totals = $quote->getTotals();

            $result['totalCost'] = $this->preparePrice($quote->getGrandTotal());
            $result['shippingCost'] = $this->prepareTotalsElement($totals, 'shipping');
            $result['taxCost'] = $this->prepareTotalsElement($totals, 'tax');
            $result['discount'] = $this->prepareTotalsElement($totals, 'discount');
        }

        foreach ($quote->getAllVisibleItems() as $item) {

            if ($item->getIsNominal()) {
                continue;
            }

            $productId = $item->getProductId();
            $product = Mage::getModel('catalog/product')->load($productId);

            $qty = $item->getQty() ? $item->getQty() : $item->getQtyOrdered();

            $result['items'][] = array(
                'sku'      => $product->getData('sku'),
                'name'     => $product->getData('name'),
                'price'    => $this->preparePrice($product->getPrice()),
                'quantity' => intval($qty),
            );
        }
    }

    /**
     * Prepare recurring item for initial payment request
     *
     * @param Cdev_XPaymentsConnector_Model_Quote $quote
     * @param array &$result
     *
     * @return void
     */
    private function prepareRecurringItem(Cdev_XPaymentsConnector_Model_Quote $quote, &$result)
    {
        $item = $quote->getRecurringItem();
        $product = $item->getProduct();
        $recurringProfile = $product->getRecurringProfile();

        $startDateParams = Mage::helper('xpaymentsconnector')->checkStartDateDataByProduct($product, $item);
        $startDateParams = $startDateParams[$product->getId()];

        $shipping = $item->getData('shipping_amount');
        $discount = abs($item->getData('discount_amount'));

        $quantity = $item->getQty();

        if ($startDateParams['success']) {

            $minimalPayment = $startDateParams['minimal_payment_amount'];

            $tax = !empty($recurringProfile['init_amount'])
                ? $item->getData('initialfee_tax_amount')
                : 0;

            $totalCost = $minimalPayment + $tax + $shipping - $discount;

        } else {

            $minimalPayment = 0;

            $tax = $item->getData('initialfee_tax_amount') + $item->getData('tax_amount');

            $totalCost = $item->getData('nominal_row_total');
        }

        $recurringPrice = $product->getPrice();

        if (!empty($recurringProfile['init_amount'])) {
            $recurringPrice += $item->getXpRecurringInitialFee() / $quantity;
        }

        $price = $minimalPayment
            ? $minimalPayment / $quantity
            : $recurringPrice;

        $result['items'][] = array(
            'sku'      => $product->getData('sku'),
            'name'     => $product->getData('name'),
            'price'    => $this->preparePrice($price),
            'quantity' => intval($quantity),
        );

        $result['totalCost'] = $this->preparePrice($price);
        $result['shippingCost'] = $this->preparePrice($shipping);
        $result['taxCost'] = $this->preparePrice($tax);
        $result['discount'] = $this->preparePrice($discount);
    }

    /**
     * Prepare items for initial payment request
     *
     * @param Cdev_XPaymentsConnector_Model_Quote $quote
     * @param array &$result
     *
     * @return void
     */
    public function prepareItems(Cdev_XPaymentsConnector_Model_Quote $quote, &$result)
    {
        if ($quote->getRecurringItem()) {

            // Actually, only one item per order.
            // For checkout with subscription
            $this->prepareRecurringItem($quote, $result);

        } else {

            // For checkout with regular items
            $this->prepareSimpleItems($quote, $result);
        }
    }

    /**
     * Get forced transaction type
     *
     * @param Cdev_XPaymentsConnector_Model_Quote $quote
     *
     * @return string
     */
    private function getForcedTransactionType(Cdev_XPaymentsConnector_Model_Quote $quote)
    {
        $isRecurringItem = (bool)$quote->getRecurringItem();

        $xpcSlot = $quote->getXpcSlot();

        $settings = Mage::helper('settings_xpc');

        if (
            $isRecurringItem && $settings->getPaymentConfig('use_initialfee_authorize', $xpcSlot)
            || !$isRecurringItem && $settings->getPaymentConfig('use_authorize', $xpcSlot)
        ) {

            $result = 'A';

        } else {

            $result = '';
        }

        return $result;
    }

    /**
     * Prepare cart for initial payment request
     *
     * @param Cdev_XPaymentsConnector_Model_Quote $quote
     * @param string $refId Reference to the order
     *
     * @return array
     */
    public function prepareCart(Cdev_XPaymentsConnector_Model_Quote $quote, $refId = false)
    {
        if ($refId) {
            $description = 'Order #' . $refId;
        } else {
            $description = 'Quote #' . $quote->getId();
        }

        $customer = $quote->getCustomer();

        if (
            !$customer->getData('email')
            || !$customer->getData('entity_id')
        ) {
            $login = 'Anonymous customer (' . $description . ')';
        } else {
            $login = $customer->getData('email') . ' (User ID #' . $customer->getData('entity_id') . ')';
        }

        $result = array(
            'login'                => $login,
            'billingAddress'       => Mage::helper('address_xpc')->prepareQuoteBillingAddress($quote),
            'shippingAddress'      => Mage::helper('address_xpc')->prepareQuoteShippingAddress($quote),
            'items'                => array(),
            'currency'             => $quote->getCurrency(),
            'shippingCost'         => 0.00,
            'taxCost'              => 0.00,
            'discount'             => 0.00,
            'totalCost'            => 0.00,
            'description'          => $description,
            'merchantEmail'        => Mage::getStoreConfig('trans_email/ident_sales/email'),
            'forceTransactionType' => $this->getForcedTransactionType($quote),
        );

        $this->prepareItems($quote, $result);

        return $result;
    }

    /**
     * Prepare cart for initial payment request
     *
     * @param Mage_Customer_Model_Customer $customer Customer
     *
     * @return array
     */
    public function prepareFakeCart(Mage_Customer_Model_Customer $customer)
    {
        $settings = Mage::helper('settings_xpc');

        $description = strval($settings->getXpcConfig('xpay_zero_auth_description')); 
        if (!$description) {
            $description = 'Authorization';
        }

        $price = $this->preparePrice($settings->getXpcConfig('xpay_zero_auth_amount'));

        $currency = $settings->getZeroAuthMethod()->getPaymentConfiguration()->getCurrency();

        $result = array(
            'login'                => $customer->getData('email') . ' (User ID #' . $customer->getData('entity_id') . ')',
            'billingAddress'       => Mage::helper('address_xpc')->prepareCustomerBillingAddress($customer),
            'shippingAddress'      => Mage::helper('address_xpc')->prepareCustomerShippingAddress($customer),
            'items'                => array(
                array(
                    'sku'      => 'CardSetup',
                    'name'     => 'CardSetup',
                    'price'    => $price,
                    'quantity' => '1',
                ),
            ),
            'currency'             => $currency,
            'shippingCost'         => 0.00,
            'taxCost'              => 0.00,
            'discount'             => 0.00,
            'totalCost'            => $price,
            'description'          => $description,
            'merchantEmail'        => Mage::getStoreConfig('trans_email/ident_sales/email'),
            'forceTransactionType' => 'A',
        );

        return $result;
    }
}
