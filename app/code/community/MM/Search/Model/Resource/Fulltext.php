<?php
/**
 * Catalog search fulltext resource model
 */
class MM_Search_Model_Resource_Fulltext extends Mage_CatalogSearch_Model_Resource_Fulltext
{
    /**
     * Initialize connection and define main table
     */
    protected function _construct()
    {
        parent::_construct();
        $this->_engine = Mage::getResourceSingleton('mm_search/fulltext_engine');
    }

    /**
     * Return search engine instance
     *
     * @return MM_Search_Model_Resource_Fulltext_Engine
     */
    public function getEngine()
    {
        return $this->_engine;
    }
}