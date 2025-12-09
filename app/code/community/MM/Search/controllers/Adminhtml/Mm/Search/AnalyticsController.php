<?php
/**
 * Admin Analytics Controller
 * 
 * Handles display of search analytics from Typesense:
 * - Popular Queries
 * - No Results Queries
 *
 * @category   MM
 * @package    MM_Search
 * @author     Tony
 */
class MM_Search_Adminhtml_Mm_Search_AnalyticsController extends Mage_Adminhtml_Controller_Action
{
    /**
     * Check ACL permissions
     *
     * @return bool
     */
    protected function _isAllowed()
    {
        $action = strtolower($this->getRequest()->getActionName());
        switch ($action) {
            case 'queries':
                return Mage::getSingleton('admin/session')->isAllowed('catalog/mm_search_analytics/popular_queries');
            case 'nohits':
                return Mage::getSingleton('admin/session')->isAllowed('catalog/mm_search_analytics/nohits_queries');
            default:
                return Mage::getSingleton('admin/session')->isAllowed('catalog/mm_search_analytics');
        }
    }

    /**
     * Popular Queries action
     */
    public function queriesAction()
    {
        $this->_title($this->__('Catalog'))
             ->_title($this->__('Advanced Search'))
             ->_title($this->__('Popular Queries'));

        $this->loadLayout();
        $this->_setActiveMenu('catalog/mm_search_analytics/popular_queries');
        
        // Add store switcher
        $this->_addContent(
            $this->getLayout()->createBlock('mm_search/adminhtml_analytics_queries')
        );
        
        $this->renderLayout();
    }

    /**
     * No Hits Queries action
     */
    public function nohitsAction()
    {
        $this->_title($this->__('Catalog'))
             ->_title($this->__('Advanced Search'))
             ->_title($this->__('No Results Queries'));

        $this->loadLayout();
        $this->_setActiveMenu('catalog/mm_search_analytics/nohits_queries');
        
        $this->_addContent(
            $this->getLayout()->createBlock('mm_search/adminhtml_analytics_nohits')
        );
        
        $this->renderLayout();
    }

    /**
     * AJAX grid action for popular queries
     */
    public function queriesGridAction()
    {
        $this->loadLayout();
        $this->getResponse()->setBody(
            $this->getLayout()->createBlock('mm_search/adminhtml_analytics_queries_grid')->toHtml()
        );
    }

    /**
     * AJAX grid action for no hits queries
     */
    public function nohitsGridAction()
    {
        $this->loadLayout();
        $this->getResponse()->setBody(
            $this->getLayout()->createBlock('mm_search/adminhtml_analytics_nohits_grid')->toHtml()
        );
    }

    /**
     * Create analytics rules action
     */
    public function createRulesAction()
    {
        $storeId = $this->getRequest()->getParam('store', 0);
        
        try {
            $helper = Mage::helper('mm_search');
            $collectionName = $helper->getCollectionName($storeId);
            
            if (empty($collectionName)) {
                throw new Exception($this->__('Collection name is not configured for this store.'));
            }
            
            $api = Mage::getModel('mm_search/api', $storeId);
            $engine = $api->getEngine();
            
            if (!$engine->supportsAnalytics()) {
                throw new Exception($this->__('Current search engine does not support analytics.'));
            }
            
            $success = $engine->createAnalyticsRules($collectionName);
            
            if ($success) {
                Mage::getSingleton('adminhtml/session')->addSuccess(
                    $this->__('Analytics rules have been created successfully for collection "%s".', $collectionName)
                );
            } else {
                Mage::getSingleton('adminhtml/session')->addError(
                    $this->__('Some analytics rules could not be created. Check logs for details.')
                );
            }
            
        } catch (Exception $e) {
            Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
            Mage::logException($e);
        }
        
        $this->_redirect('*/*/queries', array('store' => $storeId));
    }
}