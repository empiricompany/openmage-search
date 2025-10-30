<?php
/**
 * Search Engine Interface
 * 
 * Defines the contract that all search engines must implement.
 * This abstraction allows easy switching between different search engines
 * (Typesense, Meilisearch, Algolia, etc.) without changing business logic.
 * 
 * @category   MM
 * @package    MM_Search
 * @author     Tony
 */
interface MM_Search_Model_Search_EngineInterface
{
    /**
     * Initialize connection to search engine
     * 
     * @param int|null $storeId Store ID for store-specific configuration
     */
    public function __construct($storeId = null);
    
    /**
     * Get native client instance
     * 
     * Returns the underlying SDK client (Typesense\Client, Meilisearch\Client, etc.)
     * Useful for advanced operations not covered by this interface.
     * 
     * @return mixed Native client instance
     */
    public function getClient();
    
    /**
     * Create or update collection/index schema
     * 
     * @param string $collectionName Name of the collection/index
     * @param array $fields Field definitions in standard format:
     *   [
     *       'field_name' => [
     *           'type' => 'identifier|text|integer|float',
     *           'multiple' => bool,      // Is array type?
     *           'filterable' => bool,    // Can filter on this field?
     *           'sortable' => bool,      // Can sort by this field?
     *           'searchable' => bool,    // Is full-text searchable?
     *       ]
     *   ]
     * @return array Collection/Index information
     * @throws Exception If schema creation fails
     */
    public function createOrUpdateSchema($collectionName, array $fields);
    
    /**
     * Bulk index documents
     * 
     * Efficiently imports multiple documents in batches.
     * Supports both arrays and Generators for memory-efficient processing.
     * 
     * @param string $collectionName Name of the collection/index
     * @param iterable $documents Generator or array of documents to index
     * @param int $batchSize Number of documents to process per batch (default: 100)
     * @return array Stats: ['count' => int, 'errors' => array]
     * @throws Exception If bulk indexing fails
     */
    public function bulkIndex($collectionName, $documents, $batchSize = 100);
    
    /**
     * Delete a single document from the index
     * 
     * @param string $collectionName Name of the collection/index
     * @param string $documentId Unique document identifier
     * @return void
     * @throws Exception If deletion fails
     */
    public function deleteDocument($collectionName, $documentId);
    
    /**
     * Drop (delete) entire collection/index
     * 
     * WARNING: This permanently deletes all data in the collection!
     * 
     * @param string $collectionName Name of the collection/index to drop
     * @return void
     * @throws Exception If drop operation fails
     */
    public function dropCollection($collectionName);
    
    /**
     * Check if collection/index exists
     * 
     * @param string $collectionName Name of the collection/index
     * @return bool True if exists, false otherwise
     */
    public function collectionExists($collectionName);
    
    /**
     * Get engine type identifier
     * 
     * Used for configuration and routing. Must be lowercase, no spaces.
     * 
     * @return string Example: 'typesense', 'meilisearch', 'algolia'
     */
    public static function getType();
    
    /**
     * Get engine display label
     * 
     * Human-readable name shown in admin configuration.
     * 
     * @return string Example: 'Typesense', 'Meilisearch', 'Algolia'
     */
    public static function getLabel();
    
    /**
     * Get InstantSearch.js adapter filename
     * 
     * Returns the JavaScript adapter file needed for frontend InstantSearch.
     * File should be located in: skin/frontend/base/default/js/mm_search/
     * 
     * @return string Example: 'typesense-instantsearch-adapter.min.js'
     */
    public static function getInstantSearchAdapterJs();
}