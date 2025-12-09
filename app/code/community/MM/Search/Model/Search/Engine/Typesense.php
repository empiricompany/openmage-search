<?php
/**
 * Typesense Search Engine Implementation
 *
 * Direct integration with typesense/typesense-php SDK.
 * Extends Abstract to inherit automatic batch processing logic.
 *
 * @category   MM
 * @package    MM_Search
 * @author     Tony
 */
class MM_Search_Model_Search_Engine_Typesense extends MM_Search_Model_Search_Engine_Abstract
{
    /**
     * Maximum number of analytics queries to store and retrieve
     */
    const ANALYTICS_LIMIT = 100;
    
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
        $this->_helper->debug(json_encode($fields));
        // Convert standard field format to Typesense schema
        $typesenseFields = [];
        foreach ($fields as $name => $props) {
            $field = [
                'name' => $name,
                'type' => $this->_mapFieldType($props)
            ];
            
            // Add faceting if field is filterable
            if (isset($props['filterable']) && $props['filterable']) {
                $field['facet'] = true;
            }
            
            // Add infix search if specified (Typesense-specific feature)
            if (isset($props['infix']) && $props['infix']) {
                $field['infix'] = true;
            }
            
            // Add optional flag (defaults to true if not specified)
            $field['optional'] = isset($props['optional']) ? (bool)$props['optional'] : true;

            // Add sortable flag if specified
            if (isset($props['sortable'])) {
                $field['sort'] = (bool)$props['sortable'];
            }
            
            // Add index flag if specified (for controlling full-text indexing)
            if (isset($props['index'])) {
                $field['index'] = (bool)$props['index'];
            }
            
            $typesenseFields[] = $field;
        }
        
        // Create collection with schema
        $schema = [
            'name' => $collectionName,
            'fields' => $typesenseFields
        ];
        
        $this->_helper->debug(
            sprintf(
                'Creating or updating Typesense collection "%s" schema, schema %s.',
                $collectionName,
                json_encode($schema)
            )
        );
        
        return $this->_client->collections->create($schema);
    }
    
    /**
     * Import a batch of documents into Typesense
     *
     * Called automatically by bulkIndex() from Abstract class.
     * Only handles the actual import - batching is automatic.
     *
     * @param string $collectionName Collection name
     * @param array $batch Array of documents to import
     * @return array Array of error messages (empty if all successful)
     */
    protected function _importBatch($collectionName, $batch)
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
        $this->_helper->debug(
            sprintf(
                'Deleting document ID "%s" from Typesense collection "%s".',
                $documentId,
                $collectionName
            )
        );
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
        $this->_helper->debug(
            sprintf(
                'Dropping collection "%s" from Typesense. This action is irreversible!',
                $collectionName
            )
        );
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
    
    /**
     * Typesense supports analytics via Analytics Rules
     *
     * @return bool
     */
    public function supportsAnalytics()
    {
        return true;
    }
    
    /**
     * Get popular search queries from Typesense analytics collection
     *
     * Reads from the {collectionName}-product_queries collection
     * which is populated by Typesense Analytics Rules.
     *
     * @param string $collectionName Base collection name
     * @param int $limit Maximum number of results
     * @return array Array of ['q' => string, 'count' => int]
     */
    public function getPopularQueries($collectionName, $limit = 100)
    {
        $analyticsCollection = $collectionName . '-product_queries';
        return $this->_getAnalyticsDocuments($analyticsCollection, $limit);
    }
    
    /**
     * Get queries with no results from Typesense analytics collection
     *
     * Reads from the {collectionName}-no_hits_queries collection
     * which is populated by Typesense Analytics Rules.
     *
     * @param string $collectionName Base collection name
     * @param int $limit Maximum number of results
     * @return array Array of ['q' => string, 'count' => int]
     */
    public function getNoHitsQueries($collectionName, $limit = 100)
    {
        $analyticsCollection = $collectionName . '-no_hits_queries';
        return $this->_getAnalyticsDocuments($analyticsCollection, $limit);
    }
    
    /**
     * Fetch documents from an analytics collection
     *
     * @param string $analyticsCollection Collection name
     * @param int $limit Maximum results
     * @return array
     */
    protected function _getAnalyticsDocuments($analyticsCollection, $limit)
    {
        try {
            if (!$this->collectionExists($analyticsCollection)) {
                $this->_helper->debug(
                    sprintf('Analytics collection "%s" does not exist.', $analyticsCollection)
                );
                return array();
            }
            
            // Search all documents sorted by count descending
            // Use min of limit and analytics limit constant
            $perPage = min($limit, self::ANALYTICS_LIMIT);
            $searchParams = array(
                'q' => '*',
                'query_by' => 'q',
                'per_page' => $perPage,
                'sort_by' => 'count:desc'
            );
            
            $result = $this->_client->collections[$analyticsCollection]->documents->search($searchParams);
            
            $documents = array();
            if (isset($result['hits']) && is_array($result['hits'])) {
                foreach ($result['hits'] as $hit) {
                    if (isset($hit['document'])) {
                        $q = isset($hit['document']['q']) ? trim($hit['document']['q']) : '';
                        
                        // Skip empty queries and queries with less than 4 characters
                        if (empty($q) || mb_strlen($q) < 4) {
                            continue;
                        }
                        
                        $documents[] = array(
                            'q' => $q,
                            'count' => isset($hit['document']['count']) ? (int)$hit['document']['count'] : 0
                        );
                    }
                }
            }
            
            return $documents;
            
        } catch (\Typesense\Exceptions\ObjectNotFound $e) {
            $this->_helper->debug(
                sprintf('Analytics collection "%s" not found.', $analyticsCollection)
            );
            return array();
        } catch (Exception $e) {
            Mage::logException($e);
            $this->_helper->debug(
                sprintf('Error fetching analytics from "%s": %s', $analyticsCollection, $e->getMessage())
            );
            return array();
        }
    }
    
    /**
     * Create an analytics collection with the required schema
     *
     * @param string $collectionName Name of the analytics collection
     * @return bool True if collection was created successfully
     */
    protected function _createAnalyticsCollection($collectionName)
    {
        // Schema for analytics collections
        $schema = array(
            'name' => $collectionName,
            'fields' => array(
                array(
                    'name' => 'q',
                    'type' => 'string'
                ),
                array(
                    'name' => 'count',
                    'type' => 'int32'
                )
            )
        );
        
        try {
            // Check if collection already exists
            $this->_client->collections[$collectionName]->retrieve();
            $this->_helper->debug(
                sprintf('Analytics collection "%s" already exists.', $collectionName)
            );
            return true;
        } catch (Exception $e) {
            // Collection doesn't exist, create it
            try {
                $this->_client->collections->create($schema);
                $this->_helper->debug(
                    sprintf('Created analytics collection "%s".', $collectionName)
                );
                return true;
            } catch (Exception $createException) {
                Mage::logException($createException);
                $this->_helper->debug(
                    sprintf('Error creating analytics collection "%s": %s', $collectionName, $createException->getMessage())
                );
                return false;
            }
        }
    }
    
    /**
     * Create analytics rules for tracking search queries
     *
     * Creates two rules:
     * 1. popular_queries - tracks most searched terms
     * 2. nohits_queries - tracks searches with no results
     *
     * @param string $collectionName Base collection name to track
     * @return bool True if rules were created successfully
     */
    public function createAnalyticsRules($collectionName)
    {
        $success = true;
        
        // Define collection names
        $popularCollectionName = $collectionName . '-product_queries';
        $noHitsCollectionName = $collectionName . '-no_hits_queries';
        
        // Create analytics collections first (required before creating rules)
        if (!$this->_createAnalyticsCollection($popularCollectionName)) {
            $success = false;
        }
        
        if (!$this->_createAnalyticsCollection($noHitsCollectionName)) {
            $success = false;
        }
        
        // Create popular queries rule
        $popularRuleName = $popularCollectionName;
        $popularRuleConfig = array(
            'type' => 'popular_queries',
            'params' => array(
                'source' => array(
                    'collections' => array($collectionName)
                ),
                'destination' => array(
                    'collection' => $popularCollectionName
                ),
                'limit' => self::ANALYTICS_LIMIT
            )
        );
        
        try {
            $this->_client->analytics->rules()->upsert($popularRuleName, $popularRuleConfig);
            $this->_helper->debug(
                sprintf('Created analytics rule "%s" for popular queries.', $popularRuleName)
            );
        } catch (Exception $e) {
            Mage::logException($e);
            $this->_helper->debug(
                sprintf('Error creating popular queries rule: %s', $e->getMessage())
            );
            $success = false;
        }
        
        // Create no-hits queries rule
        $noHitsRuleName = $noHitsCollectionName;
        $noHitsRuleConfig = array(
            'type' => 'nohits_queries',
            'params' => array(
                'source' => array(
                    'collections' => array($collectionName)
                ),
                'destination' => array(
                    'collection' => $noHitsCollectionName
                ),
                'limit' => self::ANALYTICS_LIMIT
            )
        );
        
        try {
            $this->_client->analytics->rules()->upsert($noHitsRuleName, $noHitsRuleConfig);
            $this->_helper->debug(
                sprintf('Created analytics rule "%s" for no-hits queries.', $noHitsRuleName)
            );
        } catch (Exception $e) {
            Mage::logException($e);
            $this->_helper->debug(
                sprintf('Error creating no-hits queries rule: %s', $e->getMessage())
            );
            $success = false;
        }
        
        return $success;
    }
    
    /**
     * Check if analytics rules exist for a collection
     *
     * @param string $collectionName Base collection name
     * @return bool True if both analytics rules are configured
     */
    public function analyticsRulesExist($collectionName)
    {
        try {
            $rules = $this->_client->analytics->rules()->retrieve();
            
            $popularRuleName = $collectionName . '-product_queries';
            $noHitsRuleName = $collectionName . '-no_hits_queries';
            
            $hasPopularRule = false;
            $hasNoHitsRule = false;
            
            if (isset($rules['rules']) && is_array($rules['rules'])) {
                foreach ($rules['rules'] as $rule) {
                    if (isset($rule['name'])) {
                        if ($rule['name'] === $popularRuleName) {
                            $hasPopularRule = true;
                        }
                        if ($rule['name'] === $noHitsRuleName) {
                            $hasNoHitsRule = true;
                        }
                    }
                }
            }
            
            return $hasPopularRule && $hasNoHitsRule;
            
        } catch (Exception $e) {
            Mage::logException($e);
            return false;
        }
    }
}