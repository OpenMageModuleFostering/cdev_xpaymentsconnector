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
* @author     Qualiteam Software info@qtmsoft.com
* @category   Cdev
* @package    Cdev_XPaymentsConnector
* @copyright  (c) Qualiteam Software Ltd. <info@qtmsoft.com>. All rights reserved.
* @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
*/
?>

<?php
/**
 * Additional display settings for "IFrame" variant of payment method (frontend)
 */
?>

<?php

class Cdev_XPaymentsConnector_Model_Adminhtml_System_Config_Source_Placedisplay
{
    public function toOptionArray()
    {
        $themes = array(
            array('value' => 'review', 'label' => 'Order Review step'),
            array('value' => 'payment', 'label' => 'Payment Information step'),
        );

        return $themes;
    }
}