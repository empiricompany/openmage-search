<?php
/**
 * Typesense API model
 */
use Http\Client\Curl\Client as CurlClient;
use Http\Discovery\Psr17FactoryDiscovery;
use CmsIg\Seal\Adapter\Typesense\TypesenseAdapter;
use CmsIg\Seal\Engine;
use Typesense\Client;

use CmsIg\Seal\Schema\Field;
use CmsIg\Seal\Schema\Index;
use CmsIg\Seal\Schema\Schema;

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
        return new Client(
            [
               'api_key' => $apiKey,
                'nodes' => [
                    [
                        'host' => $host,
                        'port' => $port,
                        'protocol' => $protocol
                    ]
                ],
               'client' => new CurlClient(Psr17FactoryDiscovery::findResponseFactory(), Psr17FactoryDiscovery::findStreamFactory()),
            ]
       );
    }

    public function getEngine(): Engine
    {
        return new Engine(
            new TypesenseAdapter($this->getAdminClient()),
            $this->getSchema(),
        );
    }

    protected function getSchema()
    {
        $collectionName = $this->getCollectionName();
        $fields = [
            'id' => new Field\IdentifierField('id'),
            'url_key' => new Field\TextField('url_key'),
            'request_path' => new Field\TextField('request_path'),
            'category_names' => new Field\TextField('category_names', multiple: true, filterable: true),
            'thumbnail' => new Field\TextField('thumbnail'),
            'thumbnail_small' => new Field\TextField('thumbnail_small'),
            'thumbnail_medium' => new Field\TextField('thumbnail_medium'),
        ];

        // Add additional fields for searchable attributes
        /** @var Mage_Catalog_Model_Resource_Product_Attribute_Collection $attributeCollection */
        $attributeCollection = Mage::getResourceModel('catalog/product_attribute_collection');
        $attributeCollection->addIsSearchableFilter();
        foreach ($attributeCollection as $attribute) {
            $multiple = false;
            $filterable = false;
            if ($attribute->getFrontendInput() === 'multiselect') {
                $multiple = true;
            }
            if ($attribute->getIsFilterableInSearch()) {
                $filterable = true;
            }
            $code = $attribute->getAttributeCode();
            if ($attribute->getBackendType() === 'decimal') {
                $field = new Field\FloatField($attribute->getAttributeCode(), multiple: $multiple, filterable: $filterable, sortable: true, searchable: false);
            } elseif (in_array($code, ['status', 'visibility'])) {
                $field = new Field\IntegerField($attribute->getAttributeCode(), multiple: $multiple, filterable: $filterable, sortable: true, searchable: false);
            } else {
                $field = new Field\TextField($attribute->getAttributeCode(), multiple: $multiple, filterable: $filterable, sortable: true, searchable: true);

            }
            $fields[$attribute->getAttributeCode()] = $field;
        }
        $schema = new Schema([
            $collectionName => new Index($collectionName, $fields),
        ]);
        return $schema;
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
            $collectionName = $this->getCollectionName();
        }
        $reindexProviders = [
            new MM_Search_Model_ProductReindexProvider($this->storeId)
        ];
        $reindexConfig = \CmsIg\Seal\Reindex\ReindexConfig::create()
            ->withIndex($collectionName)
            ->withBulkSize(100)
            ->withDropIndex(true);
        
        return $this->getEngine()->reindex($reindexProviders, $reindexConfig);
    }
}