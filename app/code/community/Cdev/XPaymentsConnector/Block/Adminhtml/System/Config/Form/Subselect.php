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


class Cdev_XPaymentsConnector_Block_Adminhtml_System_Config_Form_Subselect extends Mage_Adminhtml_Block_System_Config_Form_Field
{

    /**
     * Get the button and scripts contents
     *
     * @param Varien_Data_Form_Element_Abstract $element
     * @return string
     */
    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
    {
        $html = parent::_getElementHtml($element);
        $js = $this->getElementJsEvents();
        $html = $html.$js;

        return $html;
    }


    /* Enter description here...
    *
    * @return string
    */
    public function getElementJsEvents()
    {
        $js = <<<EndHTML
        <script type="text/javascript">
            document.observe("dom:loaded", function() {
                var useIframe = $('payment_xpayments_use_iframe');
                var placeDisplaySelect = $('row_payment_xpayments_placedisplay');
                if(useIframe.value == 0){
                   placeDisplaySelect.hide();
                }
                Event.observe(useIframe, 'change', checkPlaceDisplayAccess.bind(this));
                function checkPlaceDisplayAccess(event)
                {
                    var conditionNameElement = Event.element(event);
                    if(conditionNameElement.value == 1){
                       placeDisplaySelect.show();
                    }else{
                       placeDisplaySelect.hide();
                    }

                }
            });
        </script>
EndHTML;

        return $js;
    }


}