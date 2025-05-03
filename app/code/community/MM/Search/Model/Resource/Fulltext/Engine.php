<?php

declare(strict_types=1);

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
    public function saveEntityIndex($entityId, $storeId, $index, $entity = 'product'): static
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
            $this->_apiModel->setStoreId($storeId)->reindex(dropIndex: false, identifiers: array_keys($entityIndexes));
        } catch (Exception $e) {
            Mage::logException($e);
        }
        return $this;
    }

    /**
     * Remove entity data
     *
     * @param int $storeId
     * @param array|int $entityId
     * @param string $entity 'product'|'cms'
     * @return $this
     */
    public function cleanIndex($storeId = null, $entityId = null, $entity = 'product'): Mage_CatalogSearch_Model_Resource_Fulltext_Engine|MM_Search_Model_Resource_Fulltext_Engine
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
}