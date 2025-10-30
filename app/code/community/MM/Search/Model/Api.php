<?php
/**
 * Search API Model
 *
 * Main business logic for search operations.
 * Uses native Engine implementation via Factory pattern.
 *
 * @category   MM
 * @package    MM_Search
 * @author     Tony
 */
class MM_Search_Model_Api
{
    /**
     * @var int|null
     */
    protected $_storeId = null;

    /**
     * @var string|null
     */
    protected $_collectionName = null;

    /**
     * @var MM_Search_Helper_Data
     */
    protected $_helper;

    /**
     * @var MM_Search_Model_Search_EngineInterface|null
     */
    protected $_engine = null;

    /**
     * Bulk size for reindexing
     *
     * @var int
     */
    protected $_bulkSize = 100;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->_helper = Mage::helper('mm_search');
    }

    /**
     * Get search engine instance
     *
     * Uses Factory to create appropriate engine (Typesense, Meilisearch, etc.)
     * based on configuration.
     *
     * @return MM_Search_Model_Search_EngineInterface
     */
    protected function _getEngine()
    {
        if (!$this->_engine) {
            $factory = Mage::getSingleton('mm_search/api_factory');
            $this->_engine = $factory->createEngine($this->_storeId);
        }
        return $this->_engine;
    }

    /**
     * Set store ID
     *
     * @param int|null $storeId
     * @return $this
     */
    public function setStoreId($storeId = null)
    {
        $this->_storeId = $storeId;
        $this->_collectionName = $this->_helper->getCollectionName($storeId);
        $this->_engine = null; // Reset engine for new store
        return $this;
    }

    /**
     * Get store ID
     *
     * @return int|null
     */
    public function getStoreId()
    {
        return $this->_storeId;
    }

    /**
     * Set collection name
     *
     * @param string|null $collectionName
     * @return $this
     */
    public function setCollectionName($collectionName = null)
    {
        $this->_collectionName = $collectionName;
        return $this;
    }

    /**
     * Get collection name
     *
     * @return string
     */
    public function getCollectionName()
    {
        return $this->_helper->getCollectionName($this->_storeId);
    }

    /**
     * Reindex products
     *
     * @param bool $dropIndex Whether to drop the index before reindexing
     * @param array $identifiers Product IDs to reindex (empty for all)
     * @return $this
     */
    public function reindex($dropIndex = false, $identifiers = array())
    {
        $collectionName = $this->getCollectionName();
        
        // Prevent duplicate reindex
        $hash = md5(json_encode(array($this->_storeId, $collectionName, $dropIndex, $identifiers)));
        if (Mage::registry("MM_SEARCH_REINDEX_" . $hash)) {
            return $this;
        }

        try {
            $engine = $this->_getEngine();
            
            // Drop and recreate schema if requested
            if ($dropIndex) {
                $engine->dropCollection($collectionName);
                
                $schemaHelper = Mage::helper('mm_search/schema');
                $fields = $schemaHelper->getAllSchemaFields();
                $engine->createOrUpdateSchema($collectionName, $fields);
            }
            
            // Get product data generator from indexer
            $indexer = Mage::getModel('mm_search/indexer_product', $this->_storeId);
            $documents = $indexer->getDocumentGenerator($identifiers);
            
            // Bulk index documents
            $stats = $engine->bulkIndex($collectionName, $documents, $this->_bulkSize);
            
            // Show success message
            $engineType = $this->_helper->getEngineType($this->_storeId);
            Mage::getSingleton('adminhtml/session')->addSuccess(
                Mage::helper('mm_search')->__(
                    'Indexed %d products in collection "%s" using %s',
                    $stats['count'],
                    $collectionName,
                    ucfirst($engineType)
                )
            );
            
            // Show errors if any
            if (!empty($stats['errors'])) {
                foreach ($stats['errors'] as $error) {
                    Mage::getSingleton('adminhtml/session')->addError(
                        Mage::helper('mm_search')->__('Indexing error: %s', $error)
                    );
                }
            }
            
        } catch (Exception $e) {
            Mage::logException($e);
            Mage::getSingleton('adminhtml/session')->addError(
                Mage::helper('mm_search')->__('Reindex failed: %s', $e->getMessage())
            );
        }

        Mage::register("MM_SEARCH_REINDEX_" . $hash, true);
        return $this;
    }

    /**
     * Delete document from index
     *
     * @param string|int $identifier Document ID
     * @return $this
     */
    public function deleteDocument($identifier)
    {
        try {
            $engine = $this->_getEngine();
            $engine->deleteDocument($this->getCollectionName(), (string)$identifier);
        } catch (Exception $e) {
            Mage::logException($e);
        }
        
        return $this;
    }

    /**
     * Update schema
     *
     * Triggers a full reindex with schema drop/recreate.
     *
     * @param Mage_Catalog_Model_Resource_Eav_Attribute|null $attribute Attribute to update (unused, kept for BC)
     * @return $this
     */
    public function updateSchema($attribute = null)
    {
        return $this->reindex(true);
    }
}
