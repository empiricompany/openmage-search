<?php
/**
 * Search Engine for Fulltext Indexing
 *
 * Adapter between Magento's catalogsearch indexing and MM_Search engine.
 * Delegates all operations to MM_Search_Model_Api which uses the native engine.
 *
 * @category   MM
 * @package    MM_Search
 * @author     Tony
 */
class MM_Search_Model_Resource_Fulltext_Engine extends Mage_CatalogSearch_Model_Resource_Fulltext_Engine
{
    /**
     * @var MM_Search_Model_Api
     */
    protected $_apiModel;

    /**
     * @var MM_Search_Helper_Data
     */
    protected $_helper;

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->_apiModel = Mage::getSingleton('mm_search/api');
        $this->_helper = Mage::helper('mm_search');
    }

    /**
     * Add entity data to fulltext search table
     *
     * @param int $entityId
     * @param int $storeId
     * @param array $index
     * @param string $entity 'product'|'cms'
     * @return $this
     */
    public function saveEntityIndex($entityId, $storeId, $index, $entity = 'product')
    {
        if (!Mage::app()->getStore($storeId)->getIsActive()) {
            return $this;
        }

        if (!$this->_helper->isEnabled($storeId)) {
            return parent::saveEntityIndex($entityId, $storeId, $index, $entity);
        }
            
        $this->saveEntityIndexes($storeId, array($entityId => $index), $entity);
        return $this;
    }

    /**
     * Add entities data to search index
     *
     * @param int $storeId
     * @param array $entityIndexes
     * @param string $entityType 'product'|'cms'
     * @return $this
     */
    public function saveEntityIndexes($storeId, $entityIndexes, $entityType = 'product')
    {
        if (!Mage::app()->getStore($storeId)->getIsActive()) {
            return $this;
        }

        if (!$this->_helper->isEnabled($storeId)) {
            return parent::saveEntityIndexes($storeId, $entityIndexes, $entityType);
        }

        try {
            // Full reindex if no entities (drop and recreate)
            $dropIndex = empty($entityIndexes);
            
            // Reindex via API model
            $this->_apiModel
                ->setStoreId($storeId)
                ->reindex($dropIndex, array_keys($entityIndexes));
                
        } catch (Exception $e) {
            Mage::logException($e);
        }
        
        return $this;
    }

    /**
     * Remove entity data
     *
     * @param int $storeId
     * @param array|int $entityId
     * @param string $entity 'product'|'cms'
     * @return $this
     */
    public function cleanIndex($storeId = null, $entityId = null, $entity = 'product')
    {
        if (!Mage::app()->getStore($storeId)->getIsActive()) {
            return $this;
        }

        if (!$this->_helper->isEnabled($storeId)) {
            return parent::cleanIndex($storeId, $entityId, $entity);
        }
        
        if ($entityId === null) {
            return $this;
        }
        
        try {
            // Handle both single ID and array of IDs
            $entityIds = is_array($entityId) ? $entityId : array($entityId);
            
            foreach ($entityIds as $identifier) {
                $this->_apiModel
                    ->setStoreId($storeId)
                    ->deleteDocument($identifier);
            }
            
        } catch (Exception $e) {
            Mage::logException($e);
        }
        
        return $this;
    }
}