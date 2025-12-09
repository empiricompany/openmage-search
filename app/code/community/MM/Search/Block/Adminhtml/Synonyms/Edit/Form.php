<?php
/**
 * Synonym Edit Form
 *
 * @category   MM
 * @package    MM_Search
 * @author     Tony
 */
class MM_Search_Block_Adminhtml_Synonyms_Edit_Form extends Mage_Adminhtml_Block_Widget_Form
{
    /**
     * Prepare form
     *
     * @return MM_Search_Block_Adminhtml_Synonyms_Edit_Form
     */
    protected function _prepareForm()
    {
        $helper = Mage::helper('mm_search');
        $storeId = $this->getRequest()->getParam('store', 0);
        $synonymData = Mage::registry('current_synonym');
        $synonymId = Mage::registry('current_synonym_id');
        
        // Get form data from session if validation failed
        $formData = Mage::getSingleton('adminhtml/session')->getFormData(true);
        if ($formData) {
            $synonymData = array_merge((array)$synonymData, $formData);
        }
        
        $form = new Varien_Data_Form(array(
            'id'      => 'edit_form',
            'action'  => $this->getUrl('*/*/save', array('store' => $storeId)),
            'method'  => 'post',
            'enctype' => 'multipart/form-data'
        ));
        
        $fieldset = $form->addFieldset('base_fieldset', array(
            'legend' => $helper->__('Synonym Information'),
            'class'  => 'fieldset-wide'
        ));
        
        // Show collection info
        $collectionName = $helper->getCollectionName($storeId);
        $fieldset->addField('collection_info', 'note', array(
            'label' => $helper->__('Collection'),
            'text'  => '<strong>' . $this->escapeHtml($collectionName) . '</strong>'
        ));
        
        // Synonym ID (read-only if editing)
        if ($synonymId) {
            $fieldset->addField('synonym_id', 'text', array(
                'label'    => $helper->__('Synonym ID'),
                'name'     => 'synonym_id',
                'value'    => $synonymId,
                'readonly' => true,
                'disabled' => true,
                'note'     => $helper->__('The ID cannot be changed after creation.')
            ));
            
            // Hidden field to pass the ID
            $fieldset->addField('synonym_id_hidden', 'hidden', array(
                'name'  => 'synonym_id',
                'value' => $synonymId
            ));
        } else {
            $fieldset->addField('synonym_id', 'text', array(
                'label' => $helper->__('Synonym ID'),
                'name'  => 'synonym_id',
                'value' => isset($synonymData['id']) ? $synonymData['id'] : '',
                'note'  => $helper->__('Leave empty to auto-generate from synonyms. Use lowercase letters, numbers, and hyphens only.')
            ));
        }
        
        // Synonym type selector
        $isOneWay = !empty($synonymData['root']);
        $fieldset->addField('synonym_type', 'select', array(
            'label'    => $helper->__('Synonym Type'),
            'name'     => 'synonym_type',
            'required' => true,
            'values'   => array(
                array('value' => 'multi_way', 'label' => $helper->__('Multi-way (all terms are equivalent)')),
                array('value' => 'one_way', 'label' => $helper->__('One-way (root term includes synonyms)'))
            ),
            'value'    => $isOneWay ? 'one_way' : 'multi_way',
            'note'     => $helper->__('Multi-way: searching for any term returns all. One-way: only root term triggers synonym expansion.'),
            'onchange' => 'toggleRootField(this.value)'
        ));
        
        // Root term (only for one-way synonyms)
        $fieldset->addField('root', 'text', array(
            'label'    => $helper->__('Root Term'),
            'name'     => 'root',
            'value'    => isset($synonymData['root']) ? $synonymData['root'] : '',
            'note'     => $helper->__('The main search term. Searches for this term will include the synonyms below.'),
            'class'    => 'input-text',
            'container_id' => 'root_field_container'
        ));
        
        // Synonyms textarea
        $synonymsValue = '';
        if (isset($synonymData['synonyms']) && is_array($synonymData['synonyms'])) {
            $synonymsValue = implode("\n", $synonymData['synonyms']);
        } elseif (isset($synonymData['synonyms'])) {
            $synonymsValue = $synonymData['synonyms'];
        }
        
        $fieldset->addField('synonyms', 'textarea', array(
            'label'    => $helper->__('Synonyms'),
            'name'     => 'synonyms',
            'value'    => $synonymsValue,
            'required' => true,
            'style'    => 'height: 150px;',
            'note'     => $helper->__('Enter each synonym on a new line, or separate with commas. For multi-way synonyms, all terms are treated as equivalent. For one-way synonyms, these terms will be matched when searching for the root term.')
        ));
        
        $form->setUseContainer(true);
        $this->setForm($form);
        
        return parent::_prepareForm();
    }

    /**
     * Prepare layout - add JavaScript for form toggle
     *
     * @return MM_Search_Block_Adminhtml_Synonyms_Edit_Form
     */
    protected function _prepareLayout()
    {
        parent::_prepareLayout();
        
        // Add JavaScript to toggle root field visibility
        $this->setChild('form_after', $this->getLayout()->createBlock('core/text')->setText("
            <script type='text/javascript'>
                function toggleRootField(value) {
                    var rootContainer = $('root_field_container');
                    if (rootContainer) {
                        if (value === 'one_way') {
                            rootContainer.show();
                        } else {
                            rootContainer.hide();
                            // Clear root value when switching to multi-way
                            var rootInput = $('root');
                            if (rootInput) {
                                rootInput.value = '';
                            }
                        }
                    }
                }
                
                // Initialize on page load
                document.observe('dom:loaded', function() {
                    var typeSelect = $('synonym_type');
                    if (typeSelect) {
                        toggleRootField(typeSelect.value);
                    }
                });
            </script>
        "));
        
        return $this;
    }
}