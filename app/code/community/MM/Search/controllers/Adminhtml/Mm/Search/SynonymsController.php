<?php
/**
 * Admin Synonyms Controller
 * 
 * Handles management of search synonyms in Typesense:
 * - List synonyms
 * - Create/Edit synonyms
 * - Delete synonyms
 *
 * @category   MM
 * @package    MM_Search
 * @author     Tony
 */
class MM_Search_Adminhtml_Mm_Search_SynonymsController extends Mage_Adminhtml_Controller_Action
{
    /**
     * Check ACL permissions
     *
     * @return bool
     */
    protected function _isAllowed()
    {
        return Mage::getSingleton('admin/session')->isAllowed('catalog/mm_search_analytics/synonyms');
    }

    /**
     * Initialize action - set breadcrumbs and active menu
     *
     * @return MM_Search_Adminhtml_Mm_Search_SynonymsController
     */
    protected function _initAction()
    {
        $this->_title($this->__('Catalog'))
             ->_title($this->__('Advanced Search'))
             ->_title($this->__('Synonyms'));
        
        $this->loadLayout();
        $this->_setActiveMenu('catalog/mm_search_analytics/synonyms');
        
        return $this;
    }

    /**
     * Index action - list synonyms
     */
    public function indexAction()
    {
        $this->_initAction();
        $this->renderLayout();
    }

    /**
     * New synonym action
     */
    public function newAction()
    {
        $this->_forward('edit');
    }

    /**
     * Edit synonym action
     */
    public function editAction()
    {
        $this->_title($this->__('Catalog'))
             ->_title($this->__('Advanced Search'))
             ->_title($this->__('Synonyms'));
        
        $storeId = $this->getRequest()->getParam('store', 0);
        $synonymId = $this->getRequest()->getParam('id');
        
        // Load synonym data if editing
        $synonymData = array();
        if ($synonymId) {
            try {
                $helper = Mage::helper('mm_search');
                $collectionName = $helper->getCollectionName($storeId);
                
                if (!empty($collectionName)) {
                    $factory = Mage::getSingleton('mm_search/api_factory');
                    $engine = $factory->createEngine($storeId);
                    
                    if ($engine->supportsSynonyms()) {
                        $synonymData = $engine->getSynonym($collectionName, $synonymId);
                    }
                }
            } catch (Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
                $this->_redirect('*/*/index', array('store' => $storeId));
                return;
            }
            
            if (empty($synonymData)) {
                Mage::getSingleton('adminhtml/session')->addError(
                    $this->__('Synonym not found.')
                );
                $this->_redirect('*/*/index', array('store' => $storeId));
                return;
            }
            
            $this->_title($synonymId);
        } else {
            $this->_title($this->__('New Synonym'));
        }
        
        // Register synonym data for the form
        Mage::register('current_synonym', $synonymData);
        Mage::register('current_synonym_id', $synonymId);
        
        $this->loadLayout();
        $this->_setActiveMenu('catalog/mm_search_analytics/synonyms');
        $this->renderLayout();
    }

    /**
     * Save synonym action
     */
    public function saveAction()
    {
        $storeId = $this->getRequest()->getParam('store', 0);
        
        if (!$this->getRequest()->isPost()) {
            $this->_redirect('*/*/index', array('store' => $storeId));
            return;
        }
        
        try {
            $helper = Mage::helper('mm_search');
            $collectionName = $helper->getCollectionName($storeId);
            
            if (empty($collectionName)) {
                throw new Exception($this->__('Collection name is not configured for this store.'));
            }
            
            $factory = Mage::getSingleton('mm_search/api_factory');
            $engine = $factory->createEngine($storeId);
            
            if (!$engine->supportsSynonyms()) {
                throw new Exception($this->__('Current search engine does not support synonyms.'));
            }
            
            // Get form data
            $synonymId = $this->getRequest()->getParam('synonym_id');
            $synonymType = $this->getRequest()->getParam('synonym_type', 'multi_way');
            $root = $this->getRequest()->getParam('root', '');
            $synonymsText = $this->getRequest()->getParam('synonyms', '');
            
            // Generate ID if new
            if (empty($synonymId)) {
                $synonymId = $this->_generateSynonymId($synonymsText, $root);
            }
            
            // Parse synonyms (comma or newline separated)
            $synonyms = $this->_parseSynonyms($synonymsText);
            
            if (empty($synonyms)) {
                throw new Exception($this->__('Please enter at least one synonym.'));
            }
            
            // Build synonym data
            $synonymData = array(
                'synonyms' => $synonyms
            );
            
            // Add root for one-way synonyms
            if ($synonymType === 'one_way' && !empty($root)) {
                $synonymData['root'] = trim($root);
            }
            
            // Save synonym
            $engine->upsertSynonym($collectionName, $synonymId, $synonymData);
            
            Mage::getSingleton('adminhtml/session')->addSuccess(
                $this->__('Synonym "%s" has been saved successfully.', $synonymId)
            );
            
            // Redirect based on button clicked
            if ($this->getRequest()->getParam('back')) {
                $this->_redirect('*/*/edit', array(
                    'id' => $synonymId,
                    'store' => $storeId
                ));
            } else {
                $this->_redirect('*/*/index', array('store' => $storeId));
            }
            
        } catch (Exception $e) {
            Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
            Mage::logException($e);
            
            // Stay on form with entered data
            Mage::getSingleton('adminhtml/session')->setFormData($this->getRequest()->getParams());
            $this->_redirect('*/*/edit', array(
                'id' => $this->getRequest()->getParam('synonym_id'),
                'store' => $storeId
            ));
        }
    }

    /**
     * Delete synonym action
     */
    public function deleteAction()
    {
        $storeId = $this->getRequest()->getParam('store', 0);
        $synonymId = $this->getRequest()->getParam('id');
        
        if (empty($synonymId)) {
            Mage::getSingleton('adminhtml/session')->addError(
                $this->__('Invalid synonym ID.')
            );
            $this->_redirect('*/*/index', array('store' => $storeId));
            return;
        }
        
        try {
            $helper = Mage::helper('mm_search');
            $collectionName = $helper->getCollectionName($storeId);
            
            if (empty($collectionName)) {
                throw new Exception($this->__('Collection name is not configured for this store.'));
            }
            
            $factory = Mage::getSingleton('mm_search/api_factory');
            $engine = $factory->createEngine($storeId);
            
            if (!$engine->supportsSynonyms()) {
                throw new Exception($this->__('Current search engine does not support synonyms.'));
            }
            
            $deleted = $engine->deleteSynonym($collectionName, $synonymId);
            
            if ($deleted) {
                Mage::getSingleton('adminhtml/session')->addSuccess(
                    $this->__('Synonym "%s" has been deleted.', $synonymId)
                );
            } else {
                Mage::getSingleton('adminhtml/session')->addError(
                    $this->__('Could not delete synonym "%s".', $synonymId)
                );
            }
            
        } catch (Exception $e) {
            Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
            Mage::logException($e);
        }
        
        $this->_redirect('*/*/index', array('store' => $storeId));
    }

    /**
     * Mass delete action
     */
    public function massDeleteAction()
    {
        $storeId = $this->getRequest()->getParam('store', 0);
        $synonymIds = $this->getRequest()->getParam('synonym_ids', array());
        
        if (empty($synonymIds)) {
            Mage::getSingleton('adminhtml/session')->addError(
                $this->__('Please select synonyms to delete.')
            );
            $this->_redirect('*/*/index', array('store' => $storeId));
            return;
        }
        
        try {
            $helper = Mage::helper('mm_search');
            $collectionName = $helper->getCollectionName($storeId);
            
            if (empty($collectionName)) {
                throw new Exception($this->__('Collection name is not configured for this store.'));
            }
            
            $factory = Mage::getSingleton('mm_search/api_factory');
            $engine = $factory->createEngine($storeId);
            
            if (!$engine->supportsSynonyms()) {
                throw new Exception($this->__('Current search engine does not support synonyms.'));
            }
            
            $deletedCount = 0;
            foreach ($synonymIds as $synonymId) {
                if ($engine->deleteSynonym($collectionName, $synonymId)) {
                    $deletedCount++;
                }
            }
            
            Mage::getSingleton('adminhtml/session')->addSuccess(
                $this->__('%d synonym(s) have been deleted.', $deletedCount)
            );
            
        } catch (Exception $e) {
            Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
            Mage::logException($e);
        }
        
        $this->_redirect('*/*/index', array('store' => $storeId));
    }

    /**
     * Generate a synonym ID from the synonyms text
     *
     * @param string $synonymsText Comma-separated synonyms
     * @param string $root Root word (if one-way)
     * @return string Generated ID
     */
    protected function _generateSynonymId($synonymsText, $root = '')
    {
        // Use root or first synonym as base
        $base = !empty($root) ? $root : strtok($synonymsText, ",\n");
        $base = trim($base);
        
        // Sanitize: lowercase, replace spaces with hyphens, remove special chars
        $id = strtolower($base);
        $id = preg_replace('/\s+/', '-', $id);
        $id = preg_replace('/[^a-z0-9\-]/', '', $id);
        $id = trim($id, '-');
        
        // Add suffix for uniqueness
        $id .= '-synonyms';
        
        return $id;
    }

    /**
     * Parse synonyms from text input
     *
     * @param string $text Comma or newline separated synonyms
     * @return array Array of synonym terms
     */
    protected function _parseSynonyms($text)
    {
        // Replace newlines with commas
        $text = str_replace(array("\r\n", "\r", "\n"), ',', $text);
        
        // Split by comma
        $parts = explode(',', $text);
        
        // Clean and filter
        $synonyms = array();
        foreach ($parts as $part) {
            $term = trim($part);
            if (!empty($term)) {
                $synonyms[] = $term;
            }
        }
        
        return $synonyms;
    }
}