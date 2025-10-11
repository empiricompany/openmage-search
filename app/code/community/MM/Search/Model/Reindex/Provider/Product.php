<?php

declare(strict_types=1);

use CmsIg\Seal\Reindex\ReindexConfig;
use CmsIg\Seal\Reindex\ReindexProviderInterface;

class MM_Search_Model_Reindex_Provider_Product implements ReindexProviderInterface
{
    private static $indexName;

    private $_collection = null;

    /**
     * @var MM_Search_Helper_Schema
     */
    protected $_helperSchema;

    public function __construct(
        private readonly int $storeId
    ) {
        $this->_helperSchema = Mage::helper('mm_search/schema');
        self::$indexName = $this->_helperSchema->getIndexName($this->storeId);
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
                ->addAttributeToSelect($this->_helperSchema->getSearchableAttributes()->getColumnValues('attribute_code'))
                ->addAttributeToSelect(['thumbnail', 'url_key','news_from_date', 'new_to_date'])
                ->addPriceData()
                ->addUrlRewrite()
                ->setVisibility([
                    Mage_Catalog_Model_Product_Visibility::VISIBILITY_IN_SEARCH,
                    Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH
                ])
                ->addAttributeToFilter('status', [
                    'eq' => Mage_Catalog_Model_Product_Status::STATUS_ENABLED
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
                //Mage::log('Product without ID found, skipping...');
                continue;
            }

            // Use schema helper to get complete product data (base + attributes)
            $productData = $this->_helperSchema->getCompleteProductData($product, $this->storeId);
            //Mage::log('Indexing product ID ' . $product->getId());
            //Mage::log($productData);

            yield $productData;
        }
    }

    public static function getIndex(): string
    {
        return self::$indexName;
    }
}
