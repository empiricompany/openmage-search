<?php
/**
 * Popular Queries Container Block
 *
 * @category   MM
 * @package    MM_Search
 * @author     Tony
 */
class MM_Search_Block_Adminhtml_Analytics_Queries extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->_blockGroup = 'mm_search';
        $this->_controller = 'adminhtml_analytics_queries';
        $this->_headerText = Mage::helper('mm_search')->__('Popular Search Queries');
        
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
            $api = Mage::getModel('mm_search/api', $storeId);
            $engine = $api->getEngine();
            
            if ($engine->supportsAnalytics()) {
                $collectionName = $helper->getCollectionName($storeId);
                
                if (!$engine->analyticsRulesExist($collectionName)) {
                    $this->_addButton('create_rules', array(
                        'label'   => Mage::helper('mm_search')->__('Create Analytics Rules'),
                        'onclick' => "setLocation('{$this->getUrl('*/*/createRules', array('store' => $storeId))}')",
                        'class'   => 'add',
                    ));
                }
            }
        } catch (Exception $e) {
            Mage::logException($e);
        }
    }

    /**
     * Get grid HTML
     *
     * @return string
     */
    public function getGridHtml()
    {
        return $this->getChildHtml('grid') . $this->_getAnalyticsInfoHtml();
    }

    /**
     * Get analytics info HTML
     *
     * @return string
     */
    protected function _getAnalyticsInfoHtml()
    {
        $storeId = $this->getRequest()->getParam('store', 0);
        $helper = Mage::helper('mm_search');
        
        try {
            $api = Mage::getModel('mm_search/api', $storeId);
            $engine = $api->getEngine();
            
            if (!$engine->supportsAnalytics()) {
                return '<div class="notification-global">' .
                    $helper->__('Analytics are not supported by the current search engine (%s).', 
                        $helper->getEngineType($storeId)) .
                    '</div>';
            }
            
        } catch (Exception $e) {
            return '<div class="notification-global notification-global-error">' .
                $helper->__('Error: %s', $e->getMessage()) .
                '</div>';
        }
        
        return '';
    }

    /**
     * Prepare layout
     *
     * @return MM_Search_Block_Adminhtml_Analytics_Queries
     */
    protected function _prepareLayout()
    {
        // Add store switcher
        $this->setChild('store_switcher',
            $this->getLayout()->createBlock('adminhtml/store_switcher', 'store_switcher')
                ->setUseConfirm(false)
        );
        
        return parent::_prepareLayout();
    }

    /**
     * Get store switcher HTML
     *
     * @return string
     */
    public function getStoreSwitcherHtml()
    {
        return $this->getChildHtml('store_switcher');
    }

    /**
     * Get grid URL for AJAX
     *
     * @return string
     */
    public function getGridUrl()
    {
        return $this->getUrl('*/*/queriesGrid', array('_current' => true));
    }
}