<?php
/**
 * Factory for creating search engine instances
 *
 * Auto-discovers available engines based on installed classes.
 * Supports multiple engines: Typesense (always), Meilisearch (if SDK installed), etc.
 *
 * @category   MM
 * @package    MM_Search
 * @author     Tony
 */
class MM_Search_Model_Api_Factory
{
    /**
     * @var MM_Search_Helper_Data
     */
    protected $_helper;

    /**
     * @var array Map of engine types to engine class names
     */
    protected $_engineMap = array();

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->_helper = Mage::helper('mm_search');
        $this->_discoverEngines();
    }

    /**
     * Auto-discover available engines
     *
     * Typesense is always available (required dependency).
     * Other engines are registered if their SDK classes exist.
     *
     * @return void
     */
    protected function _discoverEngines()
    {
        // Typesense: always available (required dependency)
        $this->registerEngine('MM_Search_Model_Search_Engine_Typesense');
        
        // Meilisearch: only if SDK is installed
        if (class_exists('Meilisearch\Client')) {
            $this->registerEngine('MM_Search_Model_Search_Engine_Meilisearch');
        }
        
        // Future engines can be added here
        // Example: Algolia
        // if (class_exists('Algolia\AlgoliaSearch\SearchClient')) {
        //     $this->registerEngine('MM_Search_Model_Search_Engine_Algolia');
        // }
    }

    /**
     * Create engine instance for the specified store
     *
     * @param int|null $storeId Store ID
     * @return MM_Search_Model_Search_EngineInterface
     * @throws Mage_Core_Exception
     */
    public function createEngine($storeId = null)
    {
        $engineType = $this->_helper->getEngineType($storeId);
        
        if (!isset($this->_engineMap[$engineType])) {
            $available = implode(', ', array_keys($this->_engineMap));
            Mage::throwException(
                sprintf(
                    'Engine "%s" not available. Available engines: %s',
                    $engineType,
                    $available
                )
            );
        }
        
        $engineClass = $this->_engineMap[$engineType];
        $engine = new $engineClass($storeId);
        
        if (!($engine instanceof MM_Search_Model_Search_EngineInterface)) {
            Mage::throwException(
                sprintf('Engine %s must implement MM_Search_Model_Search_EngineInterface', $engineClass)
            );
        }
        
        return $engine;
    }

    /**
     * Get available search engines for configuration dropdown
     *
     * @return array Associative array ['type' => 'Label']
     */
    public function getAvailableEngines()
    {
        $engines = array();
        
        foreach ($this->_engineMap as $type => $engineClass) {
            $engines[$type] = call_user_func(array($engineClass, 'getLabel'));
        }
        
        return $engines;
    }

    /**
     * Check if the given engine type is supported
     *
     * @param string $engineType Engine type to check
     * @return bool True if supported, false otherwise
     */
    public function isEngineTypeSupported($engineType)
    {
        return isset($this->_engineMap[$engineType]);
    }

    /**
     * Get list of supported engine types
     *
     * @return array List of supported engine types
     */
    public function getSupportedEngineTypes()
    {
        return array_keys($this->_engineMap);
    }

    /**
     * Get engine class name for the specified engine type
     *
     * @param string $engineType Engine type
     * @return string|null Engine class name or null if not found
     */
    public function getEngineClassName($engineType = null)
    {
        return isset($this->_engineMap[$engineType]) ? $this->_engineMap[$engineType] : null;
    }

    /**
     * Register a new search engine
     *
     * @param string $engineClass Engine class name (must implement MM_Search_Model_Search_EngineInterface)
     * @return $this
     * @throws Mage_Core_Exception
     */
    public function registerEngine($engineClass)
    {
        if (!class_exists($engineClass)) {
            Mage::throwException(sprintf('Engine class %s not found', $engineClass));
        }
        
        if (!in_array('MM_Search_Model_Search_EngineInterface', class_implements($engineClass))) {
            Mage::throwException(
                sprintf('Engine %s must implement MM_Search_Model_Search_EngineInterface', $engineClass)
            );
        }
        
        // Get type from static method
        $type = call_user_func(array($engineClass, 'getType'));
        
        if (isset($this->_engineMap[$type])) {
            Mage::log(sprintf('Engine type "%s" already registered, overwriting with %s', $type, $engineClass));
        }
        
        $this->_engineMap[$type] = $engineClass;
        return $this;
    }
    
    /**
     * Check if engine is available
     *
     * @param string $engineType Engine type
     * @return bool
     */
    public function isEngineAvailable($engineType)
    {
        return isset($this->_engineMap[$engineType]);
    }
}