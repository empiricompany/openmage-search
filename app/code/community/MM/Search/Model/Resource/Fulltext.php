<?php



class MM_Search_Model_Resource_Fulltext extends Mage_CatalogSearch_Model_Resource_Fulltext
{
    /**
     * @var MM_Search_Helper_Data 
     */
    protected $_helper;

    /**
     * Initialize connection and define main table
     */
    protected function _construct(): void
    {
        parent::_construct();
        $this->_helper = Mage::helper('mm_search');
        if ($this->_helper->isEnabled()) {
            $this->_engine = Mage::getResourceSingleton('mm_search/fulltext_engine');
        }
    }

    /**
     * Regenerate search index for specific store
     *
     * @param int $storeId Store View Id
     * @param int|array $productIds Product Entity Id
     * @return Mage_CatalogSearch_Model_Resource_Fulltext|MM_Search_Model_Resource_Fulltext
     */
    protected function _rebuildStoreIndex($storeId, $productIds = null)
    {
         if(!Mage::app()->getStore($storeId)->getIsActive()) {
            return $this;
        }

        if(!$this->_helper->isEnabled($storeId)) {
            return parent::_rebuildStoreIndex($storeId, $productIds);
        }

        if (is_null($productIds)) {
            $productIds = [];
        }

        $this->cleanIndex($storeId, $productIds);

        // our engine needs product IDs as keys
        $this->_saveProductIndexes($storeId, array_flip($productIds));        

        $this->resetSearchResults();

        return $this;
    }
}