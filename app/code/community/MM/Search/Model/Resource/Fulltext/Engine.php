<?php
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
     * @param string $entityType 'product'|'cms'
     * @return $this
     */
    public function saveEntityIndexes($storeId, $entityIndexes, $entityType = 'product'): static
    {
        if (!$this->_helper->isEnabled($storeId)) {
            return parent::saveEntityIndexes($storeId, $entityIndexes, $entityType);
        }
        try {
            $this->_apiModel->setStoreId($storeId)->reindex(dropIndex: true);
        } catch (Exception $e) {
            Mage::logException($e);
        }
        return $this;
    }

    /**
     * Remove entity data from fulltext search table
     *
     * @param int $storeId
     * @param array|int $entityId
     * @param string $entity 'product'|'cms'
     * @return $this
     */
    public function cleanIndex($storeId = null, $entityId = null, $entity = 'product')
    {
        if (!$this->_helper->isEnabled($storeId)) {
            return parent::cleanIndex($storeId, $entityId, $entity);
        }
        if ($entityId === null) {
            return $this;
        }
        try {
            $engine = $this->_apiModel->setStoreId($storeId)->getEngine();
            foreach ($entityId as $id) {
                $engine->deleteDocument($this->_helper->getCollectionName($storeId), $id);
            }
        } catch (Exception $e) {
            Mage::logException($e);
        }
        return $this;
    }

    /**
     * Prepare results for query
     *
     * @param Mage_CatalogSearch_Model_Query $query
     * @return array
     */
    /* public function getIdsByQuery($query)
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
    } */

    /**
     * Create Typesense collection
     *
     * @param int $storeId
     * @return void
     */
    protected function _createCollection($storeId)
    {
        $this->_apiModel->setStoreId($storeId)->getEngine()->createSchema();
    }

    
    /**
     * Get resized image URL
     *
     * @param Mage_Catalog_Model_Product $product Product
     * @param int $width Width desired
     * @param int $height Height desired
     * @return string Resized image URL
     * @throws Exception
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