<?php

declare(strict_types=1);

abstract class MM_Search_Model_Api_Abstract 
{    
    /**
     * @var MM_Search_Helper_Data
     */
    protected $_helper;
    
    public function __construct()
    {
        $this->_helper = Mage::helper('mm_search');
    }   

    /**
     * Get admin API key
     *
     * @param int|null $storeId The store ID to get the key for
     * @return string The admin API key
     */
    protected function getAdminApiKey($storeId = null): string
    {
        return $this->_helper->getAdminApiKey($storeId);
    }

    /**
     * Get search-only API key
     *
     * @param int|null $storeId The store ID to get the key for
     * @return string The search-only API key
     */
    protected function getSearchOnlyApiKey($storeId = null): string
    {
        return $this->_helper->getSearchOnlyApiKey($storeId);
    }

    /**
     * Get host
     *
     * @param int|null $storeId The store ID to get the host for
     * @return string The host
     */
    protected function getHost($storeId = null): string
    {
        return $this->_helper->getHost($storeId);
    }

    /**
     * Get port
     *
     * @param int|null $storeId The store ID to get the port for
     * @return int The port
     */
    protected function getPort($storeId = null): int
    {
        return (int) $this->_helper->getPort($storeId);
    }

    /**
     * Get protocol
     *
     * @param int|null $storeId The store ID to get the protocol for
     * @return string The protocol
     */
    protected function getProtocol($storeId = null): string
    {
        return $this->_helper->getProtocol($storeId);
    }
}