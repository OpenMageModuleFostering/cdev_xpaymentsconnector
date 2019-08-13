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
 * Used for management of customer cards
 * Class Cdev_XPaymentsConnector_Model_Usercards
 */
class Cdev_XPaymentsConnector_Model_Usercards extends Mage_Core_Model_Abstract
{
    /**
     * Card usage types
     */
    const SIMPLE_CARD    = 0;
    const BALANCE_CARD   = 1;
    const RECURRING_CARD = 2;

    /**
     * Internal constructor
     * 
     * @return void
     * @access protected
     * @see    ____func_see____
     * @since  1.0.0
     */
    protected function _construct()
    {
        $this->_init('xpaymentsconnector/usercards');
    }

    /**
     * Get card usage types
     *
     * @return array
     */
    public function getCardsUsageOptions()
    {
        return array(
            self::SIMPLE_CARD    => 'Simple',
            self::BALANCE_CARD   => 'Balance',
            self::RECURRING_CARD => 'Recurring',
        );
    }

    /**
     * Check usage type
     *
     * @param string $usageType Usage type
     *
     * @return string
     */
    private function checkUsageType($usageType)
    {
        $types = array_keys($this->getCardsUsageOptions());

        if (!in_array($usageType, $types)) {
            $usageType = self::SIMPLE_CARD;
        }

        return $usageType;
    }

    /**
     * Process masked card data from the callback request
     *
     * @param array $cardData Card data
     * @param int $customerId Customer ID
     *
     * @return Cdev_XPaymentsConnector_Model_Usercards 
     */
    public function saveUserCard($cardData, $customerId, $usageType = self::SIMPLE_CARD)
    {
        $txnId = $cardData['txnId'];

        $usageType = $this->checkUsageType($usageType);

        // Try to find card with already existing txnId.
        // Then update it or fill the empty found entity.
        $usercards = Mage::getModel('xpaymentsconnector/usercards')
            ->getCollection()
            ->addFieldToFilter('txnId', $txnId)
            ->getFirstItem();

        $data = array(
            'user_id'       => $customerId,
            'txnId'         => $txnId,
            'last_4_cc_num' => !empty($cardData['last4']) ? $cardData['last4'] : '',
            'first6'        => !empty($cardData['first6']) ? $cardData['first6'] : '',
            'card_type'     => !empty($cardData['type']) ? $cardData['type'] : '',
            'expire_month'  => !empty($cardData['expire_month']) ? $cardData['expire_month'] : '',
            'expire_year'   => !empty($cardData['expire_year']) ? $cardData['expire_year'] : '',
            'confid'        => $cardData['confid'],
            'usage_type'    => $usageType,
        );

        if ($usercards->getData('xp_card_id')) {
            $data['xp_card_id'] = $usercards->getData('xp_card_id');
        }

        $usercards->setData($data)->save();

        return $usercards;
    }

    /**
     * Check that card with certain txnId is saved in customer's profile
     *
     * @param int $customerId Customer ID
     * @param string $txnId X-Payments txnId
     *
     * @return bool
     */
    public function checkSavedCard($customerId, $txnId)
    {
        $count = $this->getCollection()
            ->addFieldToFilter('user_id', $customerId)
            ->addFieldToFilter('txnId', $txnId)
            ->count();

        return 0 < $count;
    } 
}

