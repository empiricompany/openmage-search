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
}