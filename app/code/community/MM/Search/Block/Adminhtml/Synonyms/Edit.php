<?php
/**
 * Synonym Edit Form Container
 *
 * @category   MM
 * @package    MM_Search
 * @author     Tony
 */
class MM_Search_Block_Adminhtml_Synonyms_Edit extends Mage_Adminhtml_Block_Widget_Form_Container
{
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->_objectId = 'id';
        $this->_blockGroup = 'mm_search';
        $this->_controller = 'adminhtml_synonyms';
        
        parent::__construct();
        
        $storeId = $this->getRequest()->getParam('store', 0);
        
        // Update save button
        $this->_updateButton('save', 'label', Mage::helper('mm_search')->__('Save Synonym'));
        
        // Add save and continue button
        $this->_addButton('saveandcontinue', array(
            'label'   => Mage::helper('mm_search')->__('Save and Continue Edit'),
            'onclick' => 'saveAndContinueEdit()',
            'class'   => 'save',
        ), -100);
        
        // Update back button URL with store parameter
        $this->_updateButton('back', 'onclick', "setLocation('{$this->getUrl('*/*/index', array('store' => $storeId))}')");
        
        // Add delete button only if editing existing synonym
        $synonymId = Mage::registry('current_synonym_id');
        if ($synonymId) {
            $this->_updateButton('delete', 'label', Mage::helper('mm_search')->__('Delete Synonym'));
            $this->_updateButton('delete', 'onclick', "deleteConfirm('" 
                . Mage::helper('mm_search')->__('Are you sure you want to delete this synonym?') 
                . "', '{$this->getUrl('*/*/delete', array('id' => $synonymId, 'store' => $storeId))}')");
        } else {
            $this->_removeButton('delete');
        }
        
        $this->_formScripts[] = "
            function saveAndContinueEdit() {
                editForm.submit($('edit_form').action + 'back/edit/');
            }
        ";
    }

    /**
     * Get header text
     *
     * @return string
     */
    public function getHeaderText()
    {
        $synonymId = Mage::registry('current_synonym_id');
        
        if ($synonymId) {
            return Mage::helper('mm_search')->__('Edit Synonym: %s', $this->escapeHtml($synonymId));
        } else {
            return Mage::helper('mm_search')->__('New Synonym');
        }
    }
}