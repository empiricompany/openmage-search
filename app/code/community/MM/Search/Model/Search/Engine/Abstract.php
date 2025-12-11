<?php
/**
 * Abstract Search Engine Base Class
 * 
 * Provides common functionality for all search engines.
 * Implements batch processing logic so concrete engines don't have to.
 * 
 * Concrete engines only need to implement:
 * - _importBatch() for engine-specific import
 * - Schema and connection methods
 * 
 * @category   MM
 * @package    MM_Search
 * @author     Tony
 */
abstract class MM_Search_Model_Search_Engine_Abstract implements MM_Search_Model_Search_EngineInterface
{
    /**
     * @var mixed Native client instance
     */
    protected $_client;
    
    /**
     * @var MM_Search_Helper_Data
     */
    protected $_helper;
    
    /**
     * @var int|null
     */
    protected $_storeId;
    
    /**
     * Initialize engine
     * 
     * @param int|null $storeId
     */
    public function __construct($storeId = null)
    {
        $this->_storeId = $storeId;
        $this->_helper = Mage::helper('mm_search');
        $this->_initClient();
    }
    
    /**
     * Initialize native client
     * Must be implemented by concrete engine
     * 
     * @return void
     */
    abstract protected function _initClient();
    
    /**
     * Import a batch of documents
     * 
     * This method handles the actual import for a single batch.
     * Concrete engines implement this with their specific SDK calls.
     * 
     * @param string $collectionName
     * @param array $batch Array of documents to import
     * @return array Array of error messages (empty if all successful)
     */
    abstract protected function _importBatch($collectionName, array $batch);
    
    /**
     * Bulk index documents with automatic batch processing
     * 
     * This template method handles all batching logic automatically.
     * Concrete engines don't need to implement batching themselves.
     * 
     * @param string $collectionName
     * @param iterable $documents Generator or array
     * @param int $batchSize Number of documents per batch
     * @return array Stats ['count' => int, 'errors' => array]
     */
    public function bulkIndex($collectionName, $documents, $batchSize = 300)
    {
        $batch = array();
        $count = 0;
        $errors = array();
        
        foreach ($documents as $document) {
            $batch[] = $document;
            $count++;
            
            // Process batch when it reaches the specified size
            if (count($batch) >= $batchSize) {
                $batchErrors = $this->_importBatch($collectionName, $batch);
                $errors = array_merge($errors, $batchErrors);
                $batch = array();
            }
        }
        
        // Process remaining documents
        if (!empty($batch)) {
            $batchErrors = $this->_importBatch($collectionName, $batch);
            $errors = array_merge($errors, $batchErrors);
        }
        
        $this->_helper->debug(
            sprintf(
                'Completed bulk indexing into collection "%s". Total documents: %d, Batch size: %d.',
                $collectionName,
                $count,
                $batchSize
            )
        );
        
        return array(
            'count' => $count,
            'errors' => $errors
        );
    }
    
    /**
     * Get native client instance
     * 
     * @return mixed
     */
    public function getClient()
    {
        return $this->_client;
    }
    
    /**
     * Map standard field properties to engine-specific type
     *
     * Helper method for concrete engines to use in schema creation.
     *
     * @param array $props Field properties
     * @param array $typeMap Engine-specific type mapping
     * @return string Engine-specific type string
     */
    protected function _mapFieldType(array $props, array $typeMap = array())
    {
        // Default type map
        if (empty($typeMap)) {
            $typeMap = array(
                'identifier' => 'string',
                'text' => 'string',
                'integer' => 'int32',
                'float' => 'float',
                'bool' => 'bool',
            );
        }
        
        $type = isset($props['type']) ? $props['type'] : 'text';
        $mappedType = isset($typeMap[$type]) ? $typeMap[$type] : 'string';
        
        // Handle array types
        $multiple = isset($props['multiple']) && $props['multiple'];
        if ($multiple) {
            $mappedType .= '[]';
        }
        
        return $mappedType;
    }
    
    /**
     * Check if engine supports analytics
     *
     * Default: false. Override in concrete engines that support analytics.
     *
     * @return bool
     */
    public function supportsAnalytics()
    {
        return false;
    }
    
    /**
     * Get popular search queries
     *
     * Default: empty array. Override in concrete engines that support analytics.
     *
     * @param string $collectionName Base collection name
     * @param int $limit Maximum number of results
     * @return array
     */
    public function getPopularQueries($collectionName, $limit = 100)
    {
        return array();
    }
    
    /**
     * Get queries with no results
     *
     * Default: empty array. Override in concrete engines that support analytics.
     *
     * @param string $collectionName Base collection name
     * @param int $limit Maximum number of results
     * @return array
     */
    public function getNoHitsQueries($collectionName, $limit = 100)
    {
        return array();
    }
    
    /**
     * Create analytics rules for tracking search queries
     *
     * Default: false. Override in concrete engines that support analytics.
     *
     * @param string $collectionName Base collection name
     * @return bool
     */
    public function createAnalyticsRules($collectionName)
    {
        return false;
    }
    
    /**
     * Check if analytics rules exist for a collection
     *
     * Default: false. Override in concrete engines that support analytics.
     *
     * @param string $collectionName Base collection name
     * @return bool
     */
    public function analyticsRulesExist($collectionName)
    {
        return false;
    }
    
    // ==========================================
    // SYNONYMS API - Default implementations
    // ==========================================
    
    /**
     * Check if engine supports synonyms management
     *
     * Default: false. Override in concrete engines that support synonyms.
     *
     * @return bool
     */
    public function supportsSynonyms()
    {
        return false;
    }
    
    /**
     * Get all synonyms for a collection
     *
     * Default: empty array. Override in concrete engines.
     *
     * @param string $collectionName Collection name
     * @return array
     */
    public function getSynonyms($collectionName)
    {
        return array();
    }
    
    /**
     * Get a single synonym by ID
     *
     * Default: null. Override in concrete engines.
     *
     * @param string $collectionName Collection name
     * @param string $synonymId Synonym ID
     * @return array|null
     */
    public function getSynonym($collectionName, $synonymId)
    {
        return null;
    }
    
    /**
     * Create or update a synonym
     *
     * Default: throws exception. Override in concrete engines.
     *
     * @param string $collectionName Collection name
     * @param string $synonymId Unique synonym ID
     * @param array $synonymData Synonym configuration
     * @return array
     * @throws Exception
     */
    public function upsertSynonym($collectionName, $synonymId, array $synonymData)
    {
        throw new Exception('Synonyms are not supported by this search engine.');
    }
    
    /**
     * Delete a synonym
     *
     * Default: false. Override in concrete engines.
     *
     * @param string $collectionName Collection name
     * @param string $synonymId Synonym ID to delete
     * @return bool
     */
    public function deleteSynonym($collectionName, $synonymId)
    {
        return false;
    }
}