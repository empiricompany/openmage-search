<?php
/**
 * Meilisearch Search Engine Implementation
 * 
 * Integration with meilisearch/meilisearch-php SDK.
 * This is a skeleton implementation that will be completed when
 * meilisearch/meilisearch-php is installed via composer.
 * 
 * To enable Meilisearch support:
 * 1. Run: composer require meilisearch/meilisearch-php
 * 2. Complete the method implementations below
 * 3. Configure in Admin > MM Search > Engine Type
 * 
 * @category   MM
 * @package    MM_Search
 * @author     Tony
 */
class MM_Search_Model_Search_Engine_Meilisearch implements MM_Search_Model_Search_EngineInterface
{
    /**
     * @var Meilisearch\Client
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
     * Initialize Meilisearch client
     * 
     * @param int|null $storeId Store ID for store-specific configuration
     */
    public function __construct($storeId = null)
    {
        $this->_storeId = $storeId;
        $this->_helper = Mage::helper('mm_search');
        $this->_initClient();
    }
    
    /**
     * Initialize Meilisearch client with store configuration
     * 
     * @return void
     */
    protected function _initClient()
    {
        // Build full URL: protocol://host:port
        $url = $this->_helper->getProtocol($this->_storeId) . '://' .
               $this->_helper->getHost($this->_storeId) . ':' .
               $this->_helper->getPort($this->_storeId);
        
        $apiKey = $this->_helper->getAdminApiKey($this->_storeId);
        
        // Initialize Meilisearch client
        $this->_client = new \Meilisearch\Client($url, $apiKey);
    }
    
    /**
     * Get native Meilisearch client
     * 
     * @return Meilisearch\Client
     */
    public function getClient()
    {
        return $this->_client;
    }
    
    /**
     * Create or update Meilisearch index schema
     * 
     * Meilisearch uses index instead of collection.
     * Schema is managed via settings (searchableAttributes, filterableAttributes, etc.)
     * 
     * @param string $collectionName Index name (Meilisearch terminology: index)
     * @param array $fields Field definitions in standard format
     * @return array Index info from Meilisearch
     * @throws Exception
     */
    public function createOrUpdateSchema($collectionName, array $fields)
    {
        // Delete existing index
        try {
            $this->_client->deleteIndex($collectionName);
            // Wait for deletion to complete
            sleep(1);
        } catch (Exception $e) {
            // Index doesn't exist, which is fine
        }
        
        // Create index with primary key
        $index = $this->_client->createIndex($collectionName, ['primaryKey' => 'id']);
        
        // Wait for index creation
        sleep(1);
        
        // Convert standard format to Meilisearch settings
        $searchableAttributes = [];
        $filterableAttributes = [];
        $sortableAttributes = [];
        
        foreach ($fields as $name => $props) {
            if (isset($props['searchable']) && $props['searchable']) {
                $searchableAttributes[] = $name;
            }
            if (isset($props['filterable']) && $props['filterable']) {
                $filterableAttributes[] = $name;
            }
            if (isset($props['sortable']) && $props['sortable']) {
                $sortableAttributes[] = $name;
            }
        }
        
        // Update index settings
        $index->updateSettings([
            'searchableAttributes' => $searchableAttributes,
            'filterableAttributes' => $filterableAttributes,
            'sortableAttributes' => $sortableAttributes,
            'rankingRules' => [
                'words',
                'typo',
                'proximity',
                'attribute',
                'sort',
                'exactness'
            ]
        ]);
        
        return $index->fetchInfo();
    }
    
    /**
     * Bulk index documents into Meilisearch
     * 
     * Uses Meilisearch's addDocuments API for batch operations.
     * 
     * @param string $collectionName Index name
     * @param iterable $documents Generator or array of documents
     * @param int $batchSize Number of documents per batch (default: 100)
     * @return array Stats: ['count' => int, 'errors' => array]
     */
    public function bulkIndex($collectionName, $documents, $batchSize = 100)
    {
        $index = $this->_client->index($collectionName);
        $batch = [];
        $count = 0;
        $errors = [];
        
        foreach ($documents as $document) {
            $batch[] = $document;
            $count++;
            
            // Process batch when it reaches the specified size
            if (count($batch) >= $batchSize) {
                $batchErrors = $this->_addDocuments($index, $batch);
                $errors = array_merge($errors, $batchErrors);
                $batch = [];
            }
        }
        
        // Process remaining documents
        if (!empty($batch)) {
            $batchErrors = $this->_addDocuments($index, $batch);
            $errors = array_merge($errors, $batchErrors);
        }
        
        return [
            'count' => $count,
            'errors' => $errors
        ];
    }
    
    /**
     * Add batch of documents to Meilisearch
     * 
     * @param object $index Meilisearch index object
     * @param array $batch Array of documents
     * @return array Array of error messages
     */
    protected function _addDocuments($index, array $batch)
    {
        $errors = [];
        
        try {
            $task = $index->addDocuments($batch);
            // Optionally wait for task completion
            // $index->waitForTask($task['taskUid']);
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
            Mage::logException($e);
        }
        
        return $errors;
    }
    
    /**
     * Delete a single document from Meilisearch
     * 
     * @param string $collectionName Index name
     * @param string $documentId Document ID to delete
     * @return void
     */
    public function deleteDocument($collectionName, $documentId)
    {
        try {
            $this->_client->index($collectionName)->deleteDocument($documentId);
        } catch (Exception $e) {
            // Document doesn't exist or other error
            Mage::logException($e);
        }
    }
    
    /**
     * Drop (delete) entire Meilisearch index
     * 
     * @param string $collectionName Index name to drop
     * @return void
     */
    public function dropCollection($collectionName)
    {
        try {
            $this->_client->deleteIndex($collectionName);
        } catch (Exception $e) {
            // Index doesn't exist or other error
            Mage::logException($e);
        }
    }
    
    /**
     * Check if Meilisearch index exists
     * 
     * @param string $collectionName Index name
     * @return bool True if exists, false otherwise
     */
    public function collectionExists($collectionName)
    {
        try {
            $this->_client->index($collectionName)->fetchInfo();
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Get engine type identifier
     * 
     * @return string
     */
    public static function getType()
    {
        return 'meilisearch';
    }
    
    /**
     * Get engine display label
     * 
     * @return string
     */
    public static function getLabel()
    {
        return 'Meilisearch';
    }
    
    /**
     * Get InstantSearch.js adapter filename
     * 
     * @return string
     */
    public static function getInstantSearchAdapterJs()
    {
        return 'instant-meilisearch.umd.min.js';
    }
}