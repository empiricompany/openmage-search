<?php

declare(strict_types=1);

use Composer\InstalledVersions;
/**
 * Factory for creating search engine adapters
 */
class MM_Search_Model_Api_Factory
{    
    /**
     * @var MM_Search_Helper_Data
     */
    protected $_helper;

    /**
     * @var array<string, class-string<MM_Search_Model_Api_AdapterInterface>> Map of engine types to adapter class names
     * @see MM_Search_Model_Api_AdapterInterface
     */
    protected array $_engineAdapterMap = [];

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->_helper = Mage::helper('mm_search');
        $this->_initEngineAdapterMap();
    }

    /**
     * Initialize the engine adapter map
     * 
     * @return void
     */
    protected function _initEngineAdapterMap(): void
    {
        if (InstalledVersions::isInstalled('cmsig/seal-typesense-adapter')) {
            $this->registerEngineAdapter(MM_Search_Model_Api_Adapter_Typesense::getType(), MM_Search_Model_Api_Adapter_Typesense::class);
        }
        if (InstalledVersions::isInstalled('cmsig/seal-meilisearch-adapter')) {
            $this->registerEngineAdapter(MM_Search_Model_Api_Adapter_Meilisearch::getType(), MM_Search_Model_Api_Adapter_Meilisearch::class);
        }
    }

    /**
     * Create an adapter for the specified store ID
     * 
     * @param int|null $storeId Store ID
     * @return CmsIg\Seal\Adapter\AdapterInterface
     * @throws Mage_Core_Exception
     */
    public function createAdapter(?int $storeId): CmsIg\Seal\Adapter\AdapterInterface
    {
        $engineType = $this->_helper->getEngineType($storeId);
        
        if (!isset($this->_engineAdapterMap[$engineType])) {
            $supportedTypes = implode(', ', array_keys($this->_engineAdapterMap));
            Mage::throwException(sprintf('Unsupported search engine type: %s. Supported types: %s', $engineType, $supportedTypes));
        }
        
        $adapterModelName = $this->_engineAdapterMap[$engineType];
        $adapter = new $adapterModelName((int)$storeId);
        if (!($adapter instanceof MM_Search_Model_Api_AdapterInterface)) {
            Mage::throwException(sprintf('Adapter %s does not implement MM_Search_Model_Api_AdapterInterface', $adapterModelName));
        }
        
        return $adapter->create();
    }

    /**
     * Get available search engines as key-value pairs
     *
     * @return array Associative array of engine types and labels
     */
    public function getAvailableEngines(): array
    {
        $engines = [];
        
        foreach ($this->_engineAdapterMap as $type => $adapterModelName) {
            $adapter = new $adapterModelName();
            
            if ($adapter instanceof MM_Search_Model_Api_AdapterInterface) {
                $engines[$type] = $adapter::getLabel();
            }
        }
        
        return $engines;
    }

    /**
     * Check if the given engine type is supported
     *
     * @param string $engineType Engine type to check
     * @return bool True if supported, false otherwise
     */
    public function isEngineTypeSupported(string $engineType): bool
    {
        return isset($this->_engineAdapterMap[$engineType]);
    }

    /**
     * Get list of supported engine types
     *
     * @return array<string> List of supported engine types
     */
    public function getSupportedEngineTypes(): array
    {
        return array_keys($this->_engineAdapterMap);
    }

    /**
     * Register a new search engine adapter
     *
     * @param string $engineType Engine type identifier
     * @param class-string<MM_Search_Model_Api_AdapterInterface $adapterClass Adapter class name (must implement MM_Search_Model_Api_AdapterInterface)
     * @return $this
     */
    public function registerEngineAdapter(string $engineType, MM_Search_Model_Api_AdapterInterface $adapterClass): self
    {        
        $this->_engineAdapterMap[$engineType] = $adapterClass;
        return $this;
    }
}