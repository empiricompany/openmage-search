<?php

declare(strict_types=1);

abstract class MM_Search_Model_Api_Adapter_Abstract
{
    protected readonly MM_Search_Helper_Data $_helper;
    
    public function __construct(readonly ?int $storeId = null)
    {
        $this->_helper = Mage::helper('mm_search');
    }
    
    protected function getHost(): string
    {
        return $this->_helper->getHost($this->storeId);
    }
    
    protected function getProtocol(): string
    {
        return $this->_helper->getProtocol($this->storeId);
    }
    
    protected function getPort(): int
    {
        return (int)$this->_helper->getPort($this->storeId);
    }
    
    protected function getAdminApiKey(): string
    {
        return $this->_helper->getAdminApiKey($this->storeId);
    }
    
    protected function getSearchOnlyApiKey(): string
    {
        return $this->_helper->getSearchOnlyApiKey($this->storeId);
    }
    
    protected function getCollectionName(): string
    {
        return $this->_helper->getCollectionName($this->storeId);
    }
}