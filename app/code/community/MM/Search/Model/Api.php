<?php
/**
 * Typesense API model
 */
use Http\Client\Curl\Client as CurlClient;
use Http\Discovery\Psr17FactoryDiscovery;
use CmsIg\Seal\Adapter\Typesense\TypesenseAdapter;
use CmsIg\Seal\Engine;
use Typesense\Client;

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
    public function setStoreId($storeId = null): static
    {
        $this->storeId = $storeId;
        $this->collectionName = $this->_helper->getCollectionName($storeId);
        return $this;
    }

    /**
     * Get store ID
     *
     * @return int|null
     */
    public function getStoreId(): int|null
    {
        return $this->storeId;
    }

    /**
     * Set collection name
     * @param string $collectionName
     * @return static
     */
    public function setCollectionName($collectionName = null): static
    {
        $this->collectionName = $collectionName;
        return $this;
    }    
    /**
     * Get collection name
     *
     * @return string
     */
    public function getCollectionName(): string
    {
        return $this->_helper->getCollectionName($this->storeId);
    }

    /**
     * Get admin client
     *
     * @return Client
     */
    public function getAdminClient(): Client|null
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
    public function getSearchClient(): Client
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
    public function getClient($apiKey, $host, $port, $protocol): Client
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

    protected function getSchema(): Schema
    {
        $collectionName = $this->getCollectionName();
        
        // Use schema helper to get complete schema
        $schemaHelper = Mage::helper('mm_search/schema');
        return $schemaHelper->getCompleteSchema($collectionName);
    }

    public function reindex($dropIndex = false): static
    {
        $collectionName = $this->getCollectionName();
        $reindexProviders = [
            new MM_Search_Model_ProductReindexProvider($this->storeId)
        ];
        $reindexConfig = \CmsIg\Seal\Reindex\ReindexConfig::create()
            ->withIndex($collectionName)
            ->withBulkSize(100)
            ->withDropIndex($dropIndex);
        
        $this->getEngine()->reindex($reindexProviders, $reindexConfig, function ($index, $count, $total) {
            Mage::log("Reindexing {$index}: {$count}/{$total}");
        });
        return $this;
    }

    public function updateSchema(Mage_Catalog_Model_Resource_Eav_Attribute $attribute = null): static
    {
        return $this->reindex();
    }
}