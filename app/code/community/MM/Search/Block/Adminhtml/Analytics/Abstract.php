<?php
/**
 * Abstract Analytics Container Block
 * 
 * Base class for analytics container blocks to avoid code duplication
 *
 * @category   MM
 * @package    MM_Search
 * @author     Tony
 */
abstract class MM_Search_Block_Adminhtml_Analytics_Abstract extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    /**
     * @var string Redirect parameter for create rules action
     */
    protected $_redirectParam = 'queries';
    
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        
        // Remove add button - we don't need it for analytics
        $this->_removeButton('add');
        
        // Add "Create Rules" button if engine supports analytics
        $this->_addCreateRulesButton();
    }

    /**
     * Add "Create Analytics Rules" button
     */
    protected function _addCreateRulesButton()
    {
        $storeId = $this->getRequest()->getParam('store', 0);
        
        try {
            $helper = Mage::helper('mm_search');
            $factory = Mage::getSingleton('mm_search/api_factory');
            $engine = $factory->createEngine($storeId);
            
            if ($engine->supportsAnalytics()) {
                $collectionName = $helper->getCollectionName($storeId);
                
                if (!$engine->analyticsRulesExist($collectionName)) {
                    $this->_addButton('create_rules', array(
                        'label'   => $helper->__('Create Analytics Rules'),
                        'onclick' => "setLocation('{$this->getUrl('*/*/createRules', array('store' => $storeId, 'redirect' => $this->_redirectParam))}')",
                        'class'   => 'add',
                    ));
                }
            }
        } catch (Exception $e) {
            Mage::logException($e);
        }
    }

    /**
     * Get grid HTML with store selector and analytics info
     *
     * @return string
     */
    public function getGridHtml()
    {
        return $this->_getStoreViewSelectorHtml() 
             . $this->getChildHtml('grid') 
             . $this->_getAnalyticsInfoHtml();
    }

    /**
     * Get store view selector HTML - always shows all store views
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

    /**
     * Get analytics info/warning HTML
     *
     * @return string
     */
    protected function _getAnalyticsInfoHtml()
    {
        $storeId = $this->getRequest()->getParam('store', 0);
        $helper = Mage::helper('mm_search');
        
        try {
            $factory = Mage::getSingleton('mm_search/api_factory');
            $engine = $factory->createEngine($storeId);
            
            if (!$engine->supportsAnalytics()) {
                return '<div class="notification-global">' 
                     . $helper->__('Analytics are not supported by the current search engine (%s).', 
                         $helper->getEngineType($storeId)) 
                     . '</div>';
            }
            
        } catch (Exception $e) {
            return '<div class="notification-global notification-global-error">' 
                 . $helper->__('Error: %s', $e->getMessage()) 
                 . '</div>';
        }
        
        return '';
    }
}