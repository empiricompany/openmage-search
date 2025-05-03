<?php

declare(strict_types=1);

use CmsIg\Seal\Reindex\ReindexConfig;
use CmsIg\Seal\Reindex\ReindexProviderInterface;

class MM_Search_Model_ProductReindexProvider implements ReindexProviderInterface
{
    private static $indexName; 

    private $_collection = null;

    public function __construct(
        private readonly int $storeId
    ) {
        self::$indexName = Mage::helper('mm_search/schema')->getIndexName($this->storeId);
    }
    public function getStoreId(): int
    {
        return $this->storeId;
    }

    public function total(): ?int
    {
        return null;
    }

    protected function getCollection($entity_ids = []): Mage_Catalog_Model_Resource_Collection_Abstract|Mage_Catalog_Model_Resource_Product_Collection
    {
        if (!$this->_collection) {
            $this->_collection = Mage::getResourceModel('catalog/product_collection')
                ->setStoreId($this->storeId)
                ->addAttributeToSelect('*')
                ->addUrlRewrite()
                ->setVisibility([
                    Mage_Catalog_Model_Product_Visibility::VISIBILITY_IN_SEARCH,
                    Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH
                ]);
            
            if (!empty($entity_ids)) {
                $this->_collection->addFieldToFilter('entity_id', ['in' => $entity_ids]);
            }
        }
        return $this->_collection;
    }

    public function provide(ReindexConfig $reindexConfig): \Generator
    {
        foreach ($this->getCollection($reindexConfig->getIdentifiers()) as $product) {
            if (!$product->getId()) {
                continue;
            }

            // Use schema helper to get complete product data
            $schemaHelper = Mage::helper('mm_search/schema');
            $productData = $schemaHelper->getCompleteProductData($product, $this->storeId);

            yield $productData;
        }
    }

    public static function getIndex(): string
    {
        return self::$indexName;
    }
}