<?php
use CmsIg\Seal\Reindex\ReindexConfig;
use CmsIg\Seal\Reindex\ReindexProviderInterface;

class MM_Search_Model_ProductReindexProvider implements ReindexProviderInterface
{

    private static $indexName; 

    private $_collection = null;

    public function __construct(
        private readonly int $storeId
    ) {
        self::$indexName = Mage::getSingleton('mm_search/api')->getCollectionName($this->storeId);
    }
    public function getStoreId(): int
    {
        return $this->storeId;
    }

    public function total(): ?int
    {
        return $this->getCollection()->count();
    }

    protected function getCollection($entity_ids = null): Mage_Catalog_Model_Resource_Collection_Abstract|Mage_Catalog_Model_Resource_Product_Collection
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
            if ($entity_ids) {
                $this->_collection->addFieldToFilter('entity_id', array('in' => $entity_ids));
            }
        }
        Mage::log($this->_collection->getSelect()->__toString());
        Mage::log($this->_collection->count());
        return $this->_collection;
    }

    public function provide(ReindexConfig $reindexConfig): \Generator
    {
        Mage::log($reindexConfig);
        // use `$reindexConfig->getIdentifiers()` or `$reindexConfig->getDateTimeBoundary()`
        //     to support partial reindexing

        foreach ($this->getCollection($reindexConfig->getIdentifiers()) as $product) {
            /**
             * @var Mage_Catalog_Model_Product $product
             */
            if (!$product->getId()) {
                continue;
            }

            $productData = [
                'id' => (string) $product->getId(),
                'sku' => (string) $product->getSku(),
                'url_key' => (string) $product->getUrlKey(),
                'request_path' => (string) $product->getRequestPath() ?: 'catalog/product/view/id/' . $product->getId(),
                'category_names' => (array) $this->_getCategoryNames($product, $this->storeId),
                'thumbnail' => (string) $product->getThumbnail(),
                'thumbnail_small' => (string) $this->_getResizedImageUrl($product, 100, 100),
                'thumbnail_medium' => (string) $this->_getResizedImageUrl($product, 300, 300),
            ];

            $attributes = Mage::getResourceModel('catalog/product_attribute_collection')->addSearchableAttributeFilter();
            foreach ($attributes as $attribute) {
                $code = $attribute->getAttributeCode();
                if ($attribute->getBackendType() === 'decimal') {
                    $productData[$code] = (float) $product->getData($code);
                } elseif (in_array($code, ['status', 'visibility'])) {
                    $productData[$code] = (int) $product->getData($code);
                } elseif ($attribute->getFrontendInput() === 'select') {
                    $productData[$code] = (string) $product->getAttributeText($code);
                } else {
                    $productData[$code] = (string) $product->getData($code);
                }
            }

            yield $productData;
        }

    }

    public static function getIndex(): string
    {
        return self::$indexName;
    }

    

    /**
     * Get resized image URL
     *
     * @param Mage_Catalog_Model_Product $product Prodotto
     * @param int $width Larghezza desiderata
     * @param int $height Altezza desiderata
     * @return string URL dell'immagine ridimensionata
     */
    protected function _getResizedImageUrl(Mage_Catalog_Model_Product $product, $width, $height)
    {
        try {
            $imageHelper = Mage::helper('catalog/image');
            $imageUrl = $imageHelper->init($product, 'thumbnail')
                ->resize($width, $height);
            if (!$imageUrl) {
                return Mage::getDesign()->getSkinUrl('images/catalog/product/placeholder/image.jpg');
            }
            
            return $imageUrl;
        } catch (Exception $e) {
            /* Mage::log($product->getThumbnail());
            Mage::logException($e); */
            return '';
        }
    }
    
    /**
     * Get category names for a product
     * @param Mage_Catalog_Model_Product $product
     * @param mixed $storeId
     * @return array
     */
    protected function _getCategoryNames(Mage_Catalog_Model_Product $product, $storeId)
    {
        // Get category names
        $categoryCollection = $product->getCategoryCollection()
            ->setStore($storeId)
            ->addAttributeToSelect('name')
            ->addAttributeToFilter('is_active', true);
        return $categoryCollection->getColumnValues('name');
    }
}