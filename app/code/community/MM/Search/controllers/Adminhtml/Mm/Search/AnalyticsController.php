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
        $session = Mage::getSingleton('admin/session');
        
        switch ($action) {
            case 'queries':
                return $session->isAllowed('catalog/mm_search_analytics/popular_queries');
            case 'nohits':
                return $session->isAllowed('catalog/mm_search_analytics/nohits_queries');
            default:
                return $session->isAllowed('catalog/mm_search_analytics');
        }
    }

    /**
     * Initialize action - set breadcrumbs and active menu
     *
     * @param string $pageTitle Page title for breadcrumbs
     * @param string $activeMenu Active menu item path
     * @return MM_Search_Adminhtml_Mm_Search_AnalyticsController
     */
    protected function _initAction($pageTitle, $activeMenu)
    {
        $this->_title($this->__('Catalog'))
             ->_title($this->__('Advanced Search'))
             ->_title($this->__($pageTitle));
        
        $this->loadLayout();
        $this->_setActiveMenu($activeMenu);
        
        return $this;
    }

    /**
     * Popular Queries action
     */
    public function queriesAction()
    {
        $this->_initAction('Popular Queries', 'catalog/mm_search_analytics/popular_queries');
        $this->renderLayout();
    }

    /**
     * No Hits Queries action
     */
    public function nohitsAction()
    {
        $this->_initAction('No Results Queries', 'catalog/mm_search_analytics/nohits_queries');
        $this->renderLayout();
    }

    /**
     * Create analytics rules action
     */
    public function createRulesAction()
    {
        $storeId = $this->getRequest()->getParam('store', 0);
        $redirectTo = $this->getRequest()->getParam('redirect', 'queries');
        
        // Validate redirect parameter
        if (!in_array($redirectTo, array('queries', 'nohits'))) {
            $redirectTo = 'queries';
        }
        
        try {
            $helper = Mage::helper('mm_search');
            $collectionName = $helper->getCollectionName($storeId);
            
            if (empty($collectionName)) {
                throw new Exception($this->__('Collection name is not configured for this store.'));
            }
            
            $factory = Mage::getSingleton('mm_search/api_factory');
            $engine = $factory->createEngine($storeId);
            
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
        
        $this->_redirect('*/*/' . $redirectTo, array('store' => $storeId));
    }
}