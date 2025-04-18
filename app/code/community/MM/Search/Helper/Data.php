<?php
/**
 * MM Search Helper
 */
class MM_Search_Helper_Data extends Mage_Core_Helper_Abstract
{
    const XML_PATH_SEARCH_ONLY_API_KEY = 'mm_search/connection/search_only_api_key';
    const XML_PATH_ADMIN_API_KEY = 'mm_search/connection/api_key';
    const XML_PATH_HOST = 'mm_search/connection/host';
    const XML_PATH_PORT = 'mm_search/connection/port';
    const XML_PATH_PROTOCOL = 'mm_search/connection/protocol';
    const XML_PATH_PROXY = 'mm_search/connection/proxy';
    const XML_PATH_COLLECTION_NAME = 'mm_search/connection/collection_name';
    const XML_PATH_ENABLED = 'mm_search/general/enabled';

    const XML_PATH_INSTANTSEARCH_CACHE = 'mm_search/instantsearch/cache_lifetime';

    /**
     * Check if module is enabled
     *
     * @param int|null $storeId Store ID
     * @return bool
     */
    public function isEnabled($storeId = null)
    {
        return Mage::getStoreConfigFlag(self::XML_PATH_ENABLED, $storeId);
    }
    /**
     * Get search only API key
     *
     * @param int|null $storeId Store ID
     * @return string
     */
    public function getSearchOnlyApiKey($storeId = null)
    {
        return Mage::getStoreConfig(self::XML_PATH_SEARCH_ONLY_API_KEY, $storeId);
    }

    /**
     * Check if proxy mode is enabled
     * 
     */
    public function isProxyEnabled($storeId = null)
    {
        return Mage::getStoreConfigFlag(self::XML_PATH_PROXY, $storeId);
    }

    /**
     * Get admin API key
     *
     * @param int|null $storeId Store ID
     * @return string
     */
    public function getAdminApiKey($storeId = null)
    {
        return Mage::getStoreConfig(self::XML_PATH_ADMIN_API_KEY, $storeId);
    }

    /**
     * Get host
     *
     * @param int|null $storeId Store ID
     * @return string
     */
    public function getHost($storeId = null)
    {
        return Mage::getStoreConfig(self::XML_PATH_HOST, $storeId);
    }

    /**
     * Get port
     *
     * @param int|null $storeId Store ID
     * @return string
     */
    public function getPort($storeId = null)
    {
        return Mage::getStoreConfig(self::XML_PATH_PORT, $storeId);
    }

    /**
     * Get protocol
     *
     * @param int|null $storeId Store ID
     * @return string
     */
    public function getProtocol($storeId = null)
    {
        return Mage::getStoreConfig(self::XML_PATH_PROTOCOL, $storeId);
    }

    /**
     * Get collection name
     *
     * @param int|null $storeId Store ID
     * @return string
     */
    public function getCollectionName($storeId = null)
    {
        return Mage::getStoreConfig(self::XML_PATH_COLLECTION_NAME, $storeId);
    }

    /**
     * Get cache lifetime for instant search
     * 
     */
    public function getCacheLifetime($storeId = null)
    {
        return (int) Mage::getStoreConfig(self::XML_PATH_INSTANTSEARCH_CACHE, $storeId);
    }
}
