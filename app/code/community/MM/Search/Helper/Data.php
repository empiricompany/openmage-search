<?php
class MM_Search_Helper_Data extends Mage_Core_Helper_Abstract
{
    const XML_PATH_SEARCH_ENGINE_TYPE = 'mm_search/general/engine_type';
    const XML_PATH_SEARCH_ONLY_API_KEY = 'mm_search/connection/search_only_api_key';
    const XML_PATH_ADMIN_API_KEY = 'mm_search/connection/api_key';
    const XML_PATH_HOST = 'mm_search/connection/host';
    const XML_PATH_PORT = 'mm_search/connection/port';
    const XML_PATH_PROTOCOL = 'mm_search/connection/protocol';
    const XML_PATH_PROXY = 'mm_search/connection/proxy';
    const XML_PATH_COLLECTION_NAME = 'mm_search/connection/collection_name';
    const XML_PATH_ENABLED = 'mm_search/general/enabled';
    const XML_PATH_DEBUG = 'mm_search/general/debug';

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
     * Get search engine type from configuration
     *
     * @param int|null $storeId Store ID
     * @return string
     */
    public function getEngineType($storeId = null)
    {
        return Mage::getStoreConfig(self::XML_PATH_SEARCH_ENGINE_TYPE, $storeId);
    }

    /**
     * Get instant search adapter JS library name
     *
     * @param int|null $storeId
     * @return string
     */
    public function getInstantSearchAdapterJs($storeId = null)
    {
        if (!$this->isEnabled($storeId)) {
            return '';
        }
        $engineType = $this->getEngineType($storeId);
        if (empty($engineType)) {
            return '';
        }
        $factory = Mage::getSingleton('mm_search/api_factory');
        $engineClass = $factory->getEngineClassName($engineType);
        return sprintf('js/mm_search/%s', call_user_func(array($engineClass, 'getInstantSearchAdapterJs')));
    }

    /**
     * Get engine config template path
     *
     * @param int|null $storeId
     * @return string
     */
    public function getEngineConfigTemplate($storeId = null)
    {
        if (!$this->isEnabled($storeId)) {
            return '';
        }

        $engineType = $this->getEngineType($storeId);
        if (empty($engineType)) {
            return '';
        }

        return sprintf('mm/search/instantsearch/config/%s.phtml', $engineType);
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
     * @param int|null $storeId
     * @return bool
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
     * @param int|null $storeId
     * @return int
     */
    public function getCacheLifetime($storeId = null)
    {
        return (int) Mage::getStoreConfig(self::XML_PATH_INSTANTSEARCH_CACHE, $storeId);
    }

    /**
     * Check if debug mode is enabled (global configuration)
     *
     * @return bool
     */
    public function isDebugEnabled()
    {
        return Mage::getStoreConfigFlag(self::XML_PATH_DEBUG);
    }

    /**
     * Add debug message to admin session
     *
     * Only displays message if debug mode is enabled in global configuration.
     * Use this instead of directly calling addNotice to avoid flooding
     * admin session with messages in production.
     *
     * @param string $message Debug message to display
     * @return void
     */
    public function debug($message)
    {
        if ($this->isDebugEnabled()) {
            Mage::getSingleton('adminhtml/session')->addNotice($this->__($message));
        }
    }

    /**
     * Get skin URL for a file, respecting theme fallback.
     *
     * @param string $file
     * @return string
     */
    public function getSkinUrl($file)
    {
        return Mage::getDesign()->getSkinUrl($file);
    }

    /**
     * Get InstantSearch bundle JS path from Vite manifest
     *
     * @return string
     */
    public function getInstantSearchBundleJs()
    {
        $manifestPath = Mage::getBaseDir('skin') . DS . 'frontend' . DS . 'base' . DS . 'default'
            . DS . 'js' . DS . 'mm_search' . DS . 'dist' . DS . '.vite' . DS . 'manifest.json';
        
        if (!file_exists($manifestPath)) {
            return 'js/mm_search/dist/instantsearch-bundle.js';
        }
        
        $manifest = json_decode(file_get_contents($manifestPath), true);
        
        if (isset($manifest['skin/frontend/base/default/js/mm_search/src/index.js']['file'])) {
            return 'js/mm_search/dist/' . $manifest['skin/frontend/base/default/js/mm_search/src/index.js']['file'];
        }
        
        return 'js/mm_search/dist/instantsearch-bundle.js';
    }
}
