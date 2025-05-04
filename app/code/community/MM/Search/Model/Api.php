<?php

declare(strict_types=1);

class MM_Search_Model_Api
{   
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

    /**
     * @var CmsIg\Seal\Adapter\AdapterInterface
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

    public function getAdapter(): CmsIg\Seal\Adapter\AdapterInterface
    {
        return $this->_adapter = Mage::getSingleton('mm_search/adapter_manager')
                ->createAdapter($this->storeId);
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

    public function getEngine(): CmsIg\Seal\Engine
    {
        return new CmsIg\Seal\Engine(
            $this->getAdapter(),
            $this->getSchema(),
        );
    }

    protected function getSchema(): CmsIg\Seal\Schema\Schema
    {
        $collectionName = $this->getCollectionName();
        /**
         * @var MM_Search_Helper_Schema $schemaHelper
         */
        $schemaHelper = Mage::helper('mm_search/schema');
        return $schemaHelper->getCompleteSchema($collectionName);
    }

    public function reindex($dropIndex = false, $identifiers = []): static
    {
        $collectionName = $this->getCollectionName();
        if (Mage::registry("MM_SEARCH_REINDEX_$collectionName")) {
            return $this;
        }
        $reindexProviders = [
            new MM_Search_Model_ProductReindexProvider($this->storeId)
        ];
        $reindexConfig = \CmsIg\Seal\Reindex\ReindexConfig::create()
            ->withIndex($collectionName)
            ->withBulkSize($this->_bulkSize)
            ->withIdentifiers($identifiers)
            ->withDropIndex($dropIndex);
        
        $this->getEngine()->reindex($reindexProviders, $reindexConfig, function ($index, $count, $total) {
            //Mage::log( sprintf("Reindexing %s: %s/%s", $index, $count, $total));
        });
        Mage::getSingleton('adminhtml/session')->addSuccess(
            Mage::helper('mm_search')->__('Collection "%s" was reindex on %s.', $collectionName, get_class($this->getAdapter()))
        );
        Mage::register("MM_SEARCH_REINDEX_$collectionName", true);
        return $this;
    }

    public function deleteDocument($identifier): static
    {
        $this->getEngine()->deleteDocument($this->getCollectionName(), $identifier);
        return $this;
    }

    public function updateSchema(Mage_Catalog_Model_Resource_Eav_Attribute $attribute = null): static
    {
        return $this->reindex(dropIndex: true);
    }
}