<?php
/**
 * Test connection button for Typesense
 */
class MM_Search_Block_Adminhtml_System_Config_TestConnection extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    /**
     * Set template
     */
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('mm/search/system/config/test_connection.phtml');
    }

    /**
     * Return element html
     *
     * @param  Varien_Data_Form_Element_Abstract $element
     * @return string
     */
    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
    {
        return $this->_toHtml();
    }

    /**
     * Return ajax url for test button
     *
     * @return string
     */
    public function getAjaxUrl()
    {
        return Mage::helper('adminhtml')->getUrl('mm_search/testconnection/index');
    }

    /**
     * Return button html
     *
     * @return string
     */
    public function getButtonHtml()
    {
        $button = $this->getLayout()->createBlock('adminhtml/widget_button')
            ->setData([
                'id'      => 'mm_search_test_connection_button',
                'label'   => $this->helper('mm_search')->__('Test Connection'),
                'onclick' => 'javascript:testTypesenseConnection(); return false;'
            ]);

        return $button->toHtml();
    }
}