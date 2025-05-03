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
        if (Mage::helper('mm_search')->isEnabled()) {
            $this->_engine = Mage::getResourceSingleton('mm_search/fulltext_engine');
        }
    }
}