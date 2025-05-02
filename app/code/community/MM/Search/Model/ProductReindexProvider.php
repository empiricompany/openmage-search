<?php
use CmsIg\Seal\Reindex\ReindexConfig;
use CmsIg\Seal\Reindex\ReindexProviderInterface;

class MM_Search_Model_ProductReindexProvider implements ReindexProviderInterface
{
    public function __construct(
        private readonly int $storeId,
    ) {
    }
    public function getStoreId(): int
    {
        return $this->storeId;
    }

    public function total(): ?int
    {
        return null;
    }

    public function provide(ReindexConfig $reindexConfig): \Generator
    {
        Mage::log($reindexConfig);
        // use `$reindexConfig->getIdentifiers()` or `$reindexConfig->getDateTimeBoundary()`
        //     to support partial reindexing

        $productCollection = Mage::getResourceModel('catalog/product_collection')
            ->setStoreId($this->storeId)
            ->addAttributeToSelect('*')
            ->addUrlRewrite()
            ->setVisibility([
                Mage_Catalog_Model_Product_Visibility::VISIBILITY_IN_SEARCH,
                Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH
            ])
            ->addFieldToFilter('entity_id', array('in' => $reindexConfig->getIdentifiers()));

        foreach ($productCollection as $product) {
            /**
             * @var Mage_Catalog_Model_Product $product
             */
            if (!$product->getId()) {
                continue;
            }

            yield [
                'id' => (string) $product->getId(),
                'sku' => (string) $product->getSku(),
                'url_key' => (string) $product->getUrlKey(),
                'request_path' => (string) $product->getRequestPath() ?: 'catalog/product/view/id/' . $product->getId(),
                'category_names' => (array) $this->_getCategoryNames($product, $storeId),
                'thumbnail' => (string) $product->getThumbnail(),
                'thumbnail_small' => (string) $this->_getResizedImageUrl($product, 100, 100),
                'thumbnail_medium' => (string) $this->_getResizedImageUrl($product, 300, 300),
            ];

            // Add additional attributes
            $attributes = Mage::getResourceModel('catalog/product_attribute_collection')->addSearchableAttributeFilter();
            foreach ($attributes as $attribute) {
                $code = $attribute->getAttributeCode();
                if ($attribute->getBackendType() === 'decimal') {
                    yield $code => (float) $product->getData($code);
                } elseif (in_array($code, ['status', 'visibility'])) {
                    yield $code => (int) $product->getData($code);
                } elseif ($attribute->getFrontendInput() === 'select') {
                    yield $code => (string) $product->getAttributeText($code);
                } else {
                    yield $code => (string) $product->getData($code);
                }
            }

        }

    }

    public static function getIndex(): string
    {
        return 'Products';
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