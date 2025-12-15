<?php
/**
 * Product Indexer
 * 
 * Provides product data for search engine indexing using Generator pattern
 * for memory-efficient processing of large product catalogs.
 * 
 * @category   MM
 * @package    MM_Search
 * @author     Tony
 */
class MM_Search_Model_Indexer_Product
{
    /**
     * @var int
     */
    protected $_storeId;
    
    /**
     * @var MM_Search_Helper_Schema
     */
    protected $_schemaHelper;
    
    /**
     * @var Mage_Catalog_Model_Resource_Product_Collection|null
     */
    protected $_collection = null;
    
    /**
     * Constructor
     * 
     * @param int $storeId Store ID for indexing
     */
    public function __construct($storeId)
    {
        $this->_storeId = $storeId;
        $this->_schemaHelper = Mage::helper('mm_search/schema');
    }
    
    /**
     * Get store ID
     * 
     * @return int
     */
    public function getStoreId()
    {
        return $this->_storeId;
    }
    
    /**
     * Get product collection for indexing
     * 
     * @param array $productIds Optional array of specific product IDs to index
     * @return Mage_Catalog_Model_Resource_Product_Collection
     */
    protected function _getCollection($productIds = array())
    {
        if (!$this->_collection) {
            // Get searchable attributes
            $searchableAttributes = $this->_schemaHelper
                ->getSearchableAttributes()
                ->getColumnValues('attribute_code');
            
            // Build collection
            $this->_collection = Mage::getResourceModel('catalog/product_collection')
                ->setStoreId($this->_storeId)
                ->addAttributeToSelect($searchableAttributes)
                ->addAttributeToSelect(array('thumbnail', 'url_key', 'news_from_date', 'news_to_date'))
                ->addPriceData()
                ->addUrlRewrite()
                ->setVisibility(array(
                    Mage_Catalog_Model_Product_Visibility::VISIBILITY_IN_SEARCH,
                    Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH
                ))
                ->addAttributeToFilter('status', array(
                    'eq' => Mage_Catalog_Model_Product_Status::STATUS_ENABLED
                ));
            
            // Only filter out of stock products if configured to do so
            /** @var MM_Search_Helper_Data $helper */
            $helper = Mage::helper('mm_search');
            if (!$helper->isIncludeOutOfStockEnabled($this->_storeId)) {
                Mage::getSingleton('cataloginventory/stock')
                    ->addInStockFilterToCollection($this->_collection);
            }

            // Filter by specific product IDs if provided
            if (!empty($productIds)) {
                $this->_collection->addFieldToFilter('entity_id', array('in' => $productIds));
            }
        }
        
        return $this->_collection;
    }
    
    /**
     * Get document generator for memory-efficient indexing
     * 
     * Uses PHP Generator to yield documents one at a time, avoiding
     * loading all products into memory at once. This is crucial for
     * large catalogs with thousands of products.
     * 
     * @param array $productIds Optional array of specific product IDs to index
     * @return Generator Yields product data arrays
     */
    public function getDocumentGenerator($productIds = array())
    {
        $collection = $this->_getCollection($productIds);
        
        foreach ($collection as $product) {
            /** @var Mage_Catalog_Model_Product $product */
            
            // Skip products without ID (shouldn't happen but safety check)
            if (!$product->getId()) {
                continue;
            }
            
            // Get complete product data using schema helper
            $productData = $this->_schemaHelper->getCompleteProductData($product, $this->_storeId);
            
            // Yield document for batch processing by engine
            yield $productData;
        }
    }
    
    /**
     * Get total count of products to be indexed
     * 
     * @param array $productIds Optional array of specific product IDs
     * @return int
     */
    public function getTotalCount($productIds = array())
    {
        return $this->_getCollection($productIds)->getSize();
    }
    
    /**
     * Reset collection for re-use
     * 
     * @return $this
     */
    public function resetCollection()
    {
        $this->_collection = null;
        return $this;
    }
}