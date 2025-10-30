<?php
/**
 * Typesense Search Engine Implementation
 * 
 * Direct integration with typesense/typesense-php SDK.
 * Implements MM_Search_Model_Search_EngineInterface for compatibility
 * with multi-engine architecture.
 * 
 * @category   MM
 * @package    MM_Search
 * @author     Tony
 */
class MM_Search_Model_Search_Engine_Typesense implements MM_Search_Model_Search_EngineInterface
{
    /**
     * @var Typesense\Client
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
     * Initialize Typesense client
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
     * Initialize Typesense client with store configuration
     * 
     * @return void
     */
    protected function _initClient()
    {
        $host = $this->_helper->getHost($this->_storeId);
        $port = $this->_helper->getPort($this->_storeId);
        $protocol = $this->_helper->getProtocol($this->_storeId);
        $apiKey = $this->_helper->getAdminApiKey($this->_storeId);
        
        $this->_client = new \Typesense\Client([
            'api_key' => $apiKey,
            'nodes' => [[
                'host' => $host,
                'port' => (int)$port,
                'protocol' => $protocol
            ]],
            'connection_timeout_seconds' => 5
        ]);
    }
    
    /**
     * Get native Typesense client
     * 
     * @return Typesense\Client
     */
    public function getClient()
    {
        return $this->_client;
    }
    
    /**
     * Create or update Typesense collection schema
     * 
     * Drops existing collection and creates new one with updated schema.
     * This is necessary because Typesense doesn't support schema modifications
     * on existing collections.
     * 
     * @param string $collectionName Collection name
     * @param array $fields Field definitions in standard format
     * @return array Collection info from Typesense
     * @throws Exception
     */
    public function createOrUpdateSchema($collectionName, array $fields)
    {
        // Drop existing collection if it exists
        try {
            $this->_client->collections[$collectionName]->retrieve();
            $this->_client->collections[$collectionName]->delete();
        } catch (\Typesense\Exceptions\ObjectNotFound $e) {
            // Collection doesn't exist, which is fine
        }
        
        // Convert standard field format to Typesense schema
        $typesenseFields = [];
        foreach ($fields as $name => $props) {
            $field = [
                'name' => $name,
                'type' => $this->_mapToTypesenseType($props)
            ];
            
            // Add faceting if field is filterable
            if (isset($props['filterable']) && $props['filterable']) {
                $field['facet'] = true;
            }
            
            // Add optional flag
            if (isset($props['optional'])) {
                $field['optional'] = (bool)$props['optional'];
            } else {
                $field['optional'] = true; // Default to optional
            }
            
            $typesenseFields[] = $field;
        }
        
        // Create collection with schema
        $schema = [
            'name' => $collectionName,
            'fields' => $typesenseFields,
            'default_sorting_field' => 'price'
        ];
        
        return $this->_client->collections->create($schema);
    }
    
    /**
     * Map standard field type to Typesense type
     * 
     * @param array $props Field properties
     * @return string Typesense field type (e.g., 'string', 'int32', 'float', 'string[]')
     */
    protected function _mapToTypesenseType(array $props)
    {
        // Define type mapping
        $typeMap = [
            'identifier' => 'string',
            'text' => 'string',
            'integer' => 'int32',
            'float' => 'float'
        ];
        
        $type = isset($props['type']) ? $props['type'] : 'text';
        $typesenseType = isset($typeMap[$type]) ? $typeMap[$type] : 'string';
        
        // Handle array types
        $multiple = isset($props['multiple']) && $props['multiple'];
        if ($multiple) {
            $typesenseType .= '[]';
        }
        
        return $typesenseType;
    }
    
    /**
     * Bulk index documents into Typesense
     * 
     * Uses Typesense's import API for efficient batch operations.
     * Processes documents in configurable batches to manage memory usage.
     * 
     * @param string $collectionName Collection name
     * @param iterable $documents Generator or array of documents
     * @param int $batchSize Number of documents per batch (default: 100)
     * @return array Stats: ['count' => int, 'errors' => array]
     */
    public function bulkIndex($collectionName, $documents, $batchSize = 100)
    {
        $batch = [];
        $count = 0;
        $errors = [];
        
        foreach ($documents as $document) {
            $batch[] = $document;
            $count++;
            
            // Process batch when it reaches the specified size
            if (count($batch) >= $batchSize) {
                $batchErrors = $this->_importBatch($collectionName, $batch);
                $errors = array_merge($errors, $batchErrors);
                $batch = [];
            }
        }
        
        // Process remaining documents
        if (!empty($batch)) {
            $batchErrors = $this->_importBatch($collectionName, $batch);
            $errors = array_merge($errors, $batchErrors);
        }
        
        return [
            'count' => $count,
            'errors' => $errors
        ];
    }
    
    /**
     * Import a batch of documents
     * 
     * @param string $collectionName Collection name
     * @param array $batch Array of documents
     * @return array Array of error messages (empty if all successful)
     */
    protected function _importBatch($collectionName, array $batch)
    {
        $errors = [];
        
        try {
            $result = $this->_client->collections[$collectionName]->documents->import(
                $batch,
                ['action' => 'upsert']
            );
            
            // Check each item in the result for errors
            foreach ($result as $item) {
                if (!$item['success']) {
                    $errors[] = isset($item['error']) ? $item['error'] : 'Unknown error';
                }
            }
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
            Mage::logException($e);
        }
        
        return $errors;
    }
    
    /**
     * Delete a single document from Typesense
     * 
     * @param string $collectionName Collection name
     * @param string $documentId Document ID to delete
     * @return void
     */
    public function deleteDocument($collectionName, $documentId)
    {
        try {
            $this->_client->collections[$collectionName]
                ->documents[(string)$documentId]
                ->delete();
        } catch (\Typesense\Exceptions\ObjectNotFound $e) {
            // Document doesn't exist, which is fine
        } catch (Exception $e) {
            Mage::logException($e);
            throw $e;
        }
    }
    
    /**
     * Drop (delete) entire Typesense collection
     * 
     * @param string $collectionName Collection name to drop
     * @return void
     */
    public function dropCollection($collectionName)
    {
        try {
            $this->_client->collections[$collectionName]->delete();
        } catch (\Typesense\Exceptions\ObjectNotFound $e) {
            // Collection doesn't exist, which is fine
        } catch (Exception $e) {
            Mage::logException($e);
            throw $e;
        }
    }
    
    /**
     * Check if Typesense collection exists
     * 
     * @param string $collectionName Collection name
     * @return bool True if exists, false otherwise
     */
    public function collectionExists($collectionName)
    {
        try {
            $this->_client->collections[$collectionName]->retrieve();
            return true;
        } catch (\Typesense\Exceptions\ObjectNotFound $e) {
            return false;
        } catch (Exception $e) {
            Mage::logException($e);
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
        return 'typesense';
    }
    
    /**
     * Get engine display label
     * 
     * @return string
     */
    public static function getLabel()
    {
        return 'Typesense';
    }
    
    /**
     * Get InstantSearch.js adapter filename
     * 
     * @return string
     */
    public static function getInstantSearchAdapterJs()
    {
        return 'typesense-instantsearch-adapter.min.js';
    }
}