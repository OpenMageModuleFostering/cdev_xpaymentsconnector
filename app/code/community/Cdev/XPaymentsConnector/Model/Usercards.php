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

/**
 * Used for management of customer cards
 * Class Cdev_XPaymentsConnector_Model_Usercards
 */

class Cdev_XPaymentsConnector_Model_Usercards extends Mage_Core_Model_Abstract
{
    const SIMPLE_CARD = 0;
    const BALANCE_CARD = 1;
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
     * @return array
     */
    public function getCardsUsageOptions(){
        $options = array();
        $options[self::SIMPLE_CARD] = "Simple";
        $options[self::BALANCE_CARD] = "Balance";
        $options[self::RECURRING_CARD] = "Recurring";
        return $options;
    }
}

