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
 * Abstract X-Payments helper
 */

abstract class Cdev_XPaymentsConnector_Helper_Abstract extends Mage_Core_Helper_Abstract
{
    /**
     * Log file name
     */
    const XPAYMENTS_LOG_FILE = 'xpayments.log';

    /**
     * Format price in 1234.56 format
     *
     * @param mixed $price
     *
     * @return string
     */
    public function preparePrice($price)
    {
        return number_format($price, 2, '.', '');
    }

    /**
     * Write log
     *
     * @param string $title Log title
     * @param mixed  $data  Data to log
     * @param bool   $trace Include backtrace or not
     *
     * @return void
     */
    public function writeLog($title, $data = '', $trace = false)
    {
        if (!is_string($data)) {
            $data = var_export($data, true);
        }

        $message = PHP_EOL . date('Y-m-d H:i:s') . PHP_EOL
            . $title . PHP_EOL
            . $data . PHP_EOL
            . Mage::helper('core/url')->getCurrentUrl() . PHP_EOL;

        if ($trace) {
            $message .= '--------------------------' . PHP_EOL
                . Varien_Debug::backtrace(true, false, false)
                . PHP_EOL;
        }

        Mage::log($message, null, self::XPAYMENTS_LOG_FILE, true);
    }
}
