<?php

declare(strict_types=1);

class MM_Search_Model_Api
{   
    /**
     * @var int|null
     */
    protected ?int $storeId = null;

    /**
     * @var string|null
     */
    protected ?string $collectionName = null;
    
    /**
     * @var MM_Search_Helper_Data
     */
    protected $_helper;

    /**
     * @var CmsIg\Seal\Adapter\AdapterInterface|null
     */
    protected $_adapter = null;

    /**
     * Bulk size for reindexing
     */
    private int $_bulkSize = 100;
    
    public function __construct()
    {
        $this->_helper = Mage::helper('mm_search');
    }

    /**
     * Get adapter instance
     * 
     * @return CmsIg\Seal\Adapter\AdapterInterface
     */
    public function getAdapter(): CmsIg\Seal\Adapter\AdapterInterface
    {
        return Mage::getSingleton('mm_search/api_factory')->createAdapter($this->storeId);
    }
    
    /**
     * Set store ID
     *
     * @param int|null $storeId
     * @return MM_Search_Model_Api
     */
    public function setStoreId(?int $storeId = null): static
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
    public function getStoreId(): ?int
    {
        return $this->storeId;
    }

    /**
     * Set collection name
     * @param string|null $collectionName
     * @return static
     */
    public function setCollectionName(?string $collectionName = null): static
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
     * Get search engine instance
     * 
     * @return CmsIg\Seal\Engine
     */
    public function getEngine(): CmsIg\Seal\Engine
    {
        return new CmsIg\Seal\Engine(
            $this->getAdapter(),
            $this->getSchema(),
        );
    }

    /**
     * Get schema
     * 
     * @return CmsIg\Seal\Schema\Schema
     */
    protected function getSchema(): CmsIg\Seal\Schema\Schema
    {
        $collectionName = $this->getCollectionName();
        /**
         * @var MM_Search_Helper_Schema $schemaHelper
         */
        $schemaHelper = Mage::helper('mm_search/schema');
        return $schemaHelper->getCompleteSchema($collectionName);
    }

    /**
     * Reindex products
     * 
     * @param bool $dropIndex Whether to drop the index before reindexing
     * @param array $identifiers Product IDs to reindex (empty for all)
     * @return static
     */
    public function reindex(bool $dropIndex = false, array $identifiers = []): static
    {
        $collectionName = $this->getCollectionName();
        if (Mage::registry("MM_SEARCH_REINDEX_$collectionName")) {
            return $this;
        }
        
        // Create provider with the current store ID
        $reindexProviders = [
            new MM_Search_Model_Reindex_Provider_Product( $this->storeId)
        ];
        
        $reindexConfig = \CmsIg\Seal\Reindex\ReindexConfig::create()
            ->withIndex($collectionName)
            ->withBulkSize($this->_bulkSize)
            ->withIdentifiers($identifiers)
            ->withDropIndex($dropIndex);
        
        $this->getEngine()->reindex($reindexProviders, $reindexConfig, function ($index, $count, $total) {
            //Mage::log( sprintf("Reindexing %s: %s/%s", $index, $count, $total));
        });
        
        // Get engine type instead of adapter class name
        $engineType = $this->_helper->getEngineType($this->storeId);
        
        Mage::getSingleton('adminhtml/session')->addSuccess(
            Mage::helper('mm_search')->__('Collection "%s" was reindex on %s.', $collectionName, ucfirst($engineType))
        );
        
        Mage::register("MM_SEARCH_REINDEX_$collectionName", true);
        return $this;
    }

    /**
     * Delete document from index
     * 
     * @param string|int $identifier Document ID
     * @return static
     */
    public function deleteDocument($identifier): static
    {
        $this->getEngine()->deleteDocument($this->getCollectionName(), $identifier);
        return $this;
    }

    /**
     * Update schema
     * 
     * @param Mage_Catalog_Model_Resource_Eav_Attribute|null $attribute Attribute to update
     * @return static
     */
    public function updateSchema(?Mage_Catalog_Model_Resource_Eav_Attribute $attribute = null): static
    {
        return $this->reindex(dropIndex: true);
    }
}