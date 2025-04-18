<?php
/**
 * Typesense search engine implementation
 */
class MM_Search_Model_Resource_Fulltext_Engine extends Mage_CatalogSearch_Model_Resource_Fulltext_Engine
{
    /**
     * @var MM_Search_Model_Api
     */
    protected $_apiModel;

    /**
     * @var MM_Search_Helper_Data
     */
    protected $_helper;

    public function __construct()
    {
        parent::__construct();
        $this->_apiModel = Mage::getSingleton('mm_search/api');
        $this->_helper = Mage::helper('mm_search');
    }

    /**
     * Add entity data to fulltext search table
     *
     * @param int $entityId
     * @param int $storeId
     * @param array $index
     * @param string $entity 'product'|'cms'
     * @return $this
     */
    public function saveEntityIndex($entityId, $storeId, $index, $entity = 'product')
    {
        $this->saveEntityIndexes($storeId, [$entityId => $index], $entity);
        return $this;
    }

    /**
     * Add entities data to search index
     *
     * @param int $storeId
     * @param array $entityIndexes
     * @param string $entityType
     * @return Mage_CatalogSearch_Model_Resource_Fulltext_Engine
     */
    public function saveEntityIndexes($storeId, $entityIndexes, $entityType = 'product')
    {
        if (!$this->_helper->isEnabled($storeId)) {
            return parent::saveEntityIndexes($storeId, $entityIndexes, $entityType);
        }
        try {
            $client = $this->_apiModel->setStoreId($storeId)->getAdminClient();
            $collectionName = $this->_helper->getCollectionName($storeId);

            // Check if collection exists, if not create it
            $collections = $client->collections->retrieve();
            $collectionExists = false;
            foreach ($collections as $collection) {
                if ($collection['name'] === $collectionName) {
                    $collectionExists = true;
                    break;
                }
            }

            if (!$collectionExists) {
                $this->_createCollection($client, $collectionName, $storeId);
            }

            $productCollection = Mage::getResourceModel('catalog/product_collection')
                ->setStoreId($storeId)
                ->addAttributeToSelect('*')
                ->addUrlRewrite()
                ->setVisibility([
                    Mage_Catalog_Model_Product_Visibility::VISIBILITY_IN_SEARCH,
                    Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH
                ])
                ->addFieldToFilter('entity_id', array('in' => array_keys($entityIndexes)));
            // Prepare documents for batch upsert
            foreach ($productCollection as $product) {
                /**
                 * @var Mage_Catalog_Model_Product $product
                 */
                if (!$product->getId()) {
                    continue;
                }

                $payload = new Varien_Object([
                    'id' => (string) $product->getId(),
                    'sku' => (string) $product->getSku(),
                    'url_key' => (string) $product->getUrlKey(),
                    'request_path' => (string) $product->getRequestPath() ?: 'catalog/product/view/id/' . $product->getId(),
                    'category_names' => (array) $this->_getCategoryNames($product, $storeId),
                    'thumbnail' => (string) $product->getThumbnail(),
                    'thumbnail_small' => (string) $this->_getResizedImageUrl($product, 100, 100),
                    'thumbnail_medium' => (string) $this->_getResizedImageUrl($product, 300, 300),
                ]);

                // Add additional attributes
                $attributes = Mage::getResourceModel('catalog/product_attribute_collection')->addSearchableAttributeFilter();
                foreach ($attributes as $attribute) {
                    $code = $attribute->getAttributeCode();
                    if ($attribute->getBackendType() === 'decimal') {
                        $payload->setData($code, (float) $product->getData($code));
                    } elseif (in_array($code, ['status', 'visibility'])) {
                        $payload->setData($code, (int) $product->getData($code));
                    } elseif ($attribute->getFrontendInput() === 'select') {
                        $payload->setData($code, (string) $product->getAttributeText($code));
                    } else {
                        $payload->setData($code, (string) $product->getData($code));
                    }
                }
                $client->collections[$collectionName]->documents->upsert($payload->getData());

            }
            return $this;
        } catch (Exception $e) {
            dd($e->getMessage());
            Mage::logException($e);
            // Fallback to default engine
            return parent::saveEntityIndexes($storeId, $entityIndexes, $entityType);
        }
    }

    /**
     * Remove entity data from fulltext search table
     *
     * @param int $storeId
     * @param int $entityId
     * @param string $entity 'product'|'cms'
     * @return $this
     */
    public function cleanIndex($storeId = null, $entityId = null, $entity = 'product')
    {
        if (!$this->_helper->isEnabled($storeId)) {
            return parent::cleanIndex($storeId, $entityId, $entity);
        }

        try {
            $client = $this->_apiModel->setStoreId($storeId)->getAdminClient();
            $collectionName = $this->_helper->getCollectionName($storeId);

            // Delete document from Typesense
            try {
                if ($entityId) {
                    $client->collections[$collectionName]->documents[(string)$entityId]->delete();
                } else {
                    $client->collections[$collectionName]->documents->delete(['filter_by' => 'id:>0']);
                }
            } catch (Exception $e) {
                Mage::logException($e);
                return parent::cleanIndex($storeId, $entityId, $entity);
            }
            return $this;
        } catch (Exception $e) {
            Mage::logException($e);
            // Fallback to default engine
            return parent::cleanIndex($storeId, $entityId, $entity);
        }
    }

    /**
     * Prepare results for query
     *
     * @param Mage_CatalogSearch_Model_Query $query
     * @return array
     */
    public function getIdsByQuery($query)
    {
        $storeId = Mage::app()->getStore()->getId();
        if (!$this->_helper->isEnabled($storeId)) {
            return parent::getIdsByQuery($query);
        }

        try {
            $client = $this->_apiModel->setStoreId($storeId)->getSearchClient();
            $collectionName = $this->_helper->getCollectionName($storeId);
            $queryText = $query->getQueryText();

            $searchParameters = [
                'q'                   => $queryText,
                'query_by'            => 'name,sku,description,short_description',
                'sort_by'             => '_text_match:desc',
                'per_page'            => 1000,
                'highlight_full_fields' => 'name,sku,description,short_description',
                'filter_by'           => 'status:1 && visibility:[2,4]'
            ];

            $searchResults = $client->collections[$collectionName]->documents->search($searchParameters);

            $ids = [];
            foreach ($searchResults['hits'] as $hit) {
                $ids[$hit['document']['id']] = [
                    'relevance' => $hit['text_match']
                ];
            }

            return $ids;
        } catch (Exception $e) {
            Mage::logException($e);
            // Fallback to default engine
            return parent::getIdsByQuery($query);
        }
    }

    /**
     * Create Typesense collection
     *
     * @param Typesense\Client $client
     * @param string $collectionName
     * @param int $storeId
     * @return void
     */
    protected function _createCollection($client, $collectionName, $storeId)
    {
        $schema = [
            'name' => $collectionName,
            'fields' => [
                ['name' => 'id', 'type' => 'string'],
                ['name' => 'url_key', 'type' => 'string'],
                ['name' => 'request_path', 'type' => 'string'],
                ['name' => 'category_names', 'type' => 'string[]', 'facet' => true],
                ['name' => 'thumbnail', 'type' => 'string'],
                ['name' => 'thumbnail_small', 'type' => 'string'],
                ['name' => 'thumbnail_medium', 'type' => 'string']
            ],
        ];

        // Add additional fields for searchable attributes
        /** @var Mage_Catalog_Model_Resource_Product_Attribute_Collection $attributeCollection */
        $attributeCollection = Mage::getResourceModel('catalog/product_attribute_collection');
        $attributeCollection->addIsSearchableFilter();
        foreach ($attributeCollection as $attribute) {
            $schema['fields'][] = $this->_apiModel->getAttributeSchema($attribute);
        }
        $client->collections->create($schema);
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
            Mage::logException($e);
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