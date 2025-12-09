<?php
/**
 * Synonyms Container Block
 *
 * @category   MM
 * @package    MM_Search
 * @author     Tony
 */
class MM_Search_Block_Adminhtml_Synonyms extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->_blockGroup = 'mm_search';
        $this->_controller = 'adminhtml_synonyms';
        $this->_headerText = Mage::helper('mm_search')->__('Search Synonyms');
        
        parent::__construct();
        
        // Change add button label
        $this->_updateButton('add', 'label', Mage::helper('mm_search')->__('Add New Synonym'));
        
        // Update add button URL with store parameter
        $storeId = $this->getRequest()->getParam('store', 0);
        $this->_updateButton('add', 'onclick', "setLocation('{$this->getUrl('*/*/new', array('store' => $storeId))}')");
    }

    /**
     * Get grid HTML with store selector
     *
     * @return string
     */
    public function getGridHtml()
    {
        return $this->_getStoreViewSelectorHtml()
             . $this->getChildHtml('grid');
    }

    /**
     * Get store view selector HTML
     *
     * @return string
     */
    protected function _getStoreViewSelectorHtml()
    {
        $currentStoreId = $this->getRequest()->getParam('store', 0);
        $helper = Mage::helper('mm_search');
        
        $html = '<div class="store-switcher" style="margin-bottom: 15px; padding: 10px; background: #f5f5f5; border: 1px solid #ddd;">';
        $html .= '<label for="store_switcher" style="font-weight: bold; margin-right: 10px;">' . $helper->__('Select Store View:') . '</label>';
        $html .= '<select id="store_switcher" name="store" onchange="setLocation(this.value);" style="padding: 5px;">';
        
        // Add "All Store Views" option
        $url = $this->getUrl('*/*/*', array('store' => 0));
        $selected = ($currentStoreId == 0) ? ' selected="selected"' : '';
        $html .= '<option value="' . $url . '"' . $selected . '>' . $helper->__('All Store Views') . '</option>';
        
        // Get all store views
        foreach (Mage::app()->getStores() as $store) {
            $storeId = $store->getId();
            $storeName = $store->getWebsite()->getName() . ' / ' . $store->getGroup()->getName() . ' / ' . $store->getName();
            $url = $this->getUrl('*/*/*', array('store' => $storeId));
            $selected = ($currentStoreId == $storeId) ? ' selected="selected"' : '';
            $html .= '<option value="' . $url . '"' . $selected . '>' . $this->escapeHtml($storeName) . '</option>';
        }
        
        $html .= '</select>';
        
        // Show current collection name
        $collectionName = $helper->getCollectionName($currentStoreId);
        if ($collectionName) {
            $html .= '<span style="margin-left: 20px; color: #666;">' 
                   . $helper->__('Collection: %s', '<strong>' . $this->escapeHtml($collectionName) . '</strong>') 
                   . '</span>';
        }
        
        $html .= '</div>';
        
        return $html;
    }
}