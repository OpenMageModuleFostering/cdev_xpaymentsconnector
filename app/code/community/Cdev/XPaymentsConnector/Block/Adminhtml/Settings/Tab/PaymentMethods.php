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
 * X-Payments Connector Payment Methods tab 
 * 
 */
class Cdev_XPaymentsConnector_Block_Adminhtml_Settings_Tab_PaymentMethods 
    extends Cdev_XPaymentsConnector_Block_Adminhtml_Settings_Tab 
    implements Mage_Adminhtml_Block_Widget_Tab_Interface
{
    /**
     * Current tab
     */
    protected $tab = Cdev_XPaymentsConnector_Helper_Settings_Data::TAB_PAYMENT_METHODS;

    /**
     * Determines whether to display the tab
     * Add logic here to decide whether you want the tab to display
     *
     * @return bool
     */
    public function canShowTab()
    {
        return !$this->isWelcomeMode();
    }

    /**
     * Get data for payment method title input
     *
     * @param $pm Payment method data
     *
     * @return array
     */
    private function getTitleInput($pm)
    {
        return array(
            'name' => 'payment_method_data[' . $pm['confid'] . '][title]',
            'value' => $pm['payment_method_data']['title'],
        );
    }

    /**
     * Get data for payment method sort order input
     *
     * @param $pm Payment method data
     *
     * @return array
     */
    private function getSortOrderInput($pm)
    {
        return array(
            'name' => 'payment_method_data[' . $pm['confid'] . '][sort_order]',
            'value' => $pm['payment_method_data']['sort_order'],
        );
    }

    /**
     * Get data for list of specific countries selectbox
     *
     * @param array $pm Payment method data
     *
     * @return array
     */
    private function getSpecificCountriesSelectBox($pm)
    {
        $countries = Mage::getResourceModel('directory/country_collection')->loadData()->toOptionArray(false);

        $result = array(
            'select' => array(
                'name' => 'payment_method_data[' . $pm['confid'] . '][specificcountry][]',
                'multiple' => 'multiple',
                'class' => 'select multiselect specificcountry',
                'size' => 10,
            ),
            'options' => array(),
        );

        $selected = explode(',', $pm['payment_method_data']['specificcountry']);

        foreach ($countries as $country) {

            $code = $country['value'];

            $result['options'][$code] = array(
                'title' => $country['label'],
                'value' => $code,
            );

            if (in_array($code, $selected)) {
                $result['options'][$code]['selected'] = 'selected';
            }
        }

        return $result;
    }

    /**
     * Get data for Yes/No selectbox
     *
     * @param array $pm Payment method data
     * @param string $name Element name
     * @param bool $isYes Should be Yes selected by default
     *
     * @return array
     */
    private function getYesNoSelectBox($pm, $name, $isYes = false)
    {
        $result = array(
            'select' => array(
                'name' => 'payment_method_data[' . $pm['confid'] . '][' . $name . ']',
                'class' => $name,
            ),
            'options' => array(
                1 => array(
                     'title' => 'Yes',
                     'value' => '1',
                ),
                0 => array(
                    'title' => 'No',
                    'value' => '0',
                ),
            ),
        );

        if (isset($pm['payment_method_data'][$name])) {
            $key = $pm['payment_method_data'][$name] ? 1 : 0;
        } else {
            $key = $isYes ? 1 : 0;
        }

        $result['options'][$key]['selected'] = 'selected';

        return $result;
    }

    /**
     * Get list of imported payment configurations 
     *
     * @param bool $includeDisabled Include "disable all" fake payment method or not
     *
     * @return array
     */
    public function getPaymentMethods($includeDisabled = true)
    {
        $list = Mage::getModel('xpaymentsconnector/paymentconfiguration')->getCollection();

        $activeCount = 0;

        $result = array();

        if (!empty($list)) {

            $count = 0;

            foreach ($list as $k => $v) {

                $v['payment_method_data'] = unserialize($v['payment_method_data']);

                $result[$k] = $v;

                // Enable/disable checkbox
                $result[$k]['active_checkbox'] = array(
                    'id'    => 'active-checkbox-' . $v['confid'],
                    'name'  => 'payment_methods[active][' . $v['confid'] . ']',
                    'value' => 'Y',
                    'class' => 'pm-active pointer ' . ($count++ % 2 == 0 ? 'even' : 'odd'),
                );

                if (
                    'Y' == $v['active']
                    && $activeCount < Cdev_XPaymentsConnector_Helper_Settings_Data::MAX_SLOTS 
                ) {
                    $result[$k]['active_checkbox'] += array('checked' => 'checked');                
                    $activeCount++;
                }

                // Save cards checkbox
                $result[$k]['savecard_checkbox'] = array(
                    'id'    => 'savecard-checkbox-' . $v['confid'],
                    'name'  => 'payment_methods[savecard][' . $v['confid'] . ']',
                    'value' => 'Y',
                    'class' => 'pm-savecard',
                );

                if ('Y' == $v['save_cards']) {
                    $result[$k]['savecard_checkbox'] += array('checked' => 'checked');
                }


                // Payment method block
                $result[$k]['payment_method'] = array(
                    'id'                        => 'payment-method-' . $v['confid'],
                    'title'                     => $this->getTitleInput($v),
                    'sort_order'                => $this->getSortOrderInput($v),
                    'allowspecific'             => $this->getYesNoSelectBox($v, 'allowspecific'),
                    'specificcountry'           => $this->getSpecificCountriesSelectBox($v),
                    'use_authorize'             => $this->getYesNoSelectBox($v, 'use_authorize'),
                    'use_initialfee_authorize'  => $this->getYesNoSelectBox($v, 'use_initialfee_authorize'),
                );

            }
        }

        if ($includeDisabled) {

            // Add the "disabled" row
            $result[0] = array(
                'confid'  => 0,
                'name'    => $this->__('Disable X-Payments payment method'),
                'active_checkbox' => array(
                    'id'    => 'active-checkbox-0',
                    'name'  => 'payment_methods[active][0]',
                    'value' => 'Y',
                    'class' => 'pm-disable pointer ' . ($count++ % 2 == 0 ? 'even' : 'odd'),
                ),
            );

            if (0 == $activeCount) {
                $result[0]['active_checkbox']['checked'] = 'checked';
            }
        }

        return $result;
    }

    /**
     * Get attributes string
     * param1="value1" param2="value2"...
     *
     * @param array $data Attributes list
     *
     * @return string
     */
    private function getAttributesStr($data)
    {
        $str = '';

        foreach ($data as $k => $v) {
            $str .= $k . '=' . '"' . $v . '" ';
        }

        return $str;
    }

    /**
     * Print selectbox
     *
     * @param array $data Selectbox data
     *
     * @return void
     */
    public function printSelectbox($data)
    {
        $str = '<select '
            . $this->getAttributesStr($data['select'])
            . '>' . PHP_EOL;

        foreach ($data['options'] as $option) {
            $title = $option['title'];
            unset($option['title']);
            $str .= '<option ' . $this->getAttributesStr($option) . '>'
                . $this->__($title)
                . '</option>' . PHP_EOL;
        }

        $str .= '</select>';

        echo $str;
    }

    /**
     * Print checkbox
     *
     * @param array $data Checkbox data
     *
     * @return void
     */
    public function printCheckbox($data)
    {
        $str = '<input type="checkbox" '
            . $this->getAttributesStr($data)
            . '/>';

        echo $str;
    }

    /**
     * Print input text
     *
     * @param array $data Input data
     *
     * @return void
     */
    public function printInputText($data)
    {
        $str = '<input type="text" class="input-text" '
            . $this->getAttributesStr($data)
            . '/>';

        echo $str;
    }
}
