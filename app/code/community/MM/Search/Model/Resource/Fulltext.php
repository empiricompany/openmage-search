<?php

declare(strict_types=1);

class MM_Search_Model_Resource_Fulltext extends Mage_CatalogSearch_Model_Resource_Fulltext
{
    /**
     * Initialize connection and define main table
     */
    protected function _construct(): void
    {
        parent::_construct();
        $this->_engine = Mage::getResourceSingleton('mm_search/fulltext_engine');
    }

    /**
     * Return search engine instance
     *
     * @return MM_Search_Model_Resource_Fulltext_Engine
     */
    public function getEngine(): MM_Search_Model_Resource_Fulltext_Engine
    {
        return $this->_engine;
    }
}