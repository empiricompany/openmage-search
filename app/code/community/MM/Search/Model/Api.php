<?php
/**
 * Typesense API model
 */
use Typesense\Client;

class MM_Search_Model_Api
{
    /**
     * @var Client|null
     */
    protected $client = null;
    
    /**
     * @var int|null
     */
    protected $storeId = null;

    /**
     * @var string|null
     */
    protected $collectionName = null;
    
    /**
     * @var MM_Search_Helper_Data
     */
    protected $_helper;
    
    public function __construct()
    {
        $this->_helper = Mage::helper('mm_search');
    }
    
    /**
     * Set store ID
     *
     * @param int|null $storeId
     * @return MM_Search_Model_Api
     */
    public function setStoreId($storeId = null)
    {
        $this->storeId = $storeId;
        $this->collectionName = $this->_helper->getCollectionName($storeId);
        return $this;
    }
    /**
     * Set collection name
     * @param string $collectionName
     * @return static
     */
    public function setCollectionName($collectionName = null)
    {
        $this->collectionName = $collectionName;
        return $this;
    }

    /**
     * Get admin client
     *
     * @return Client
     */
    public function getAdminClient()
    {
        if ($this->client === null) {
            $apiKey = $this->_helper->getAdminApiKey($this->storeId);
            $host = $this->_helper->getHost($this->storeId);
            $port = $this->_helper->getPort($this->storeId);
            $protocol = $this->_helper->getProtocol($this->storeId);
            
            $this->client = $this->getClient($apiKey, $host, $port, $protocol);
        }
        
        return $this->client;
    }
    
    /**
     * Get search-only client
     *
     * @return Client
     */
    public function getSearchClient()
    {
        $apiKey = $this->_helper->getSearchOnlyApiKey($this->storeId);
        $host = $this->_helper->getHost($this->storeId);
        $port = $this->_helper->getPort($this->storeId);
        $protocol = $this->_helper->getProtocol($this->storeId);
        
        return $this->getClient($apiKey, $host, $port, $protocol);
    }
    
    /**
     * Get client with parameters
     *
     * @param string $apiKey
     * @param string $host
     * @param int $port
     * @param string $protocol
     * @param string $path
     * @return Client
     */
    public function getClient($apiKey, $host, $port, $protocol)
    {
        return new Client([
            'api_key' => $apiKey,
            'nodes' => [
                [
                    'host' => $host,
                    'port' => $port,
                    'protocol' => $protocol
                ]
            ],
            'connection_timeout_seconds' => 5
        ]);
    }
    
    /**
     * Get collection name
     *
     * @return string
     */
    public function getCollectionName()
    {
        return $this->_helper->getCollectionName($this->storeId);
    }

    public function updateSchema(Mage_Catalog_Model_Resource_Eav_Attribute $attribute, $collectionName = null)
    {
        if ($collectionName === null) {
            $collectionName = $this->collectionName;
        }
        
        $_attributeCode = $attribute->getAttributeCode();
        
        /**
         * @var Client $client
         */
        $client = $this->getAdminClient();

        // Drop field if it exists
        $collectionFields = $client->collections[$collectionName]->retrieve();
        $collectionFields = $collectionFields['fields'];
        foreach ($collectionFields as $field) {
            if ($field['name'] === $_attributeCode) {
                $this->dropFieldFromCollection($attribute, $collectionName);
                /* Mage::getSingleton('adminhtml/session')->addSuccess(
                    Mage::helper('mm_search')->__('Field %s has been dropped from the collection %s', $_attributeCode, $collectionName)
                ); */
            }
        }

        // Add field if it is searchable
        if ($attribute->getIsSearchable()) {
            $client->collections[$collectionName]->update([
                'fields' => [
                    $this->getAttributeSchema($attribute)
                ]
            ]);
            /* Mage::getSingleton('adminhtml/session')->addSuccess(
                Mage::helper('mm_search')->__('Field %s has been added to the collection %s', $_attributeCode, $collectionName)
            ); */
        }
    }

    /**
     * Get schema field from attribute
     * @param Mage_Catalog_Model_Resource_Eav_Attribute $attribute
     * @return array
     */
    public function getAttributeSchema(Mage_Catalog_Model_Resource_Eav_Attribute $attribute)
    {
        $code = $attribute->getAttributeCode();
        $field = ['name' => $code];
        if ($attribute->getBackendType() === 'decimal') {
            $field['type'] = 'float';
        } elseif (in_array($code, ['status', 'visibility'])) {
            $field['type'] = 'int32';
        } else {
            $field['type'] = 'string';
            if ($attribute->getFrontendInput() === 'select' || $attribute->getFrontendInput() === 'multiselect') {
                $field['facet'] = true;
            }
        }
        if ($attribute->getIsFilterableInSearch()) {
            $field['facet'] = true;
        }
        $field['optional'] = true;
        return $field;
    }

    /**
     * Drop field from collection
     * @param string $fieldName
     * @return array
     */
    public function dropFieldFromCollection(Mage_Catalog_Model_Resource_Eav_Attribute $attribute, $collectionName = null)
    {
        if ($collectionName === null) {
            $collectionName = $this->collectionName;
        }
        return $this->getAdminClient()->collections[$collectionName]->update([
            'fields' => [
                [
                    'name' => $attribute->getAttributeCode(),
                    'drop' => true
                ]
            ]
        ]);   
    }
}