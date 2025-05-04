<?php

declare(strict_types=1);

/**
 * Adapter manager for creating search engine adapters
 */
class MM_Search_Model_Adapter_Manager
{
    /**
     * @var MM_Search_Helper_Data
     */
    protected $_helper;

    /**
     * @var CmsIg\Seal\Adapter\AdapterInterface[]
     */
    protected $_factories = [];

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->_helper = Mage::helper('mm_search');
        $this->_initFactories();
    }

    /**
     * Initialize adapter factories
     */
    protected function _initFactories(): void
    {
        $this->_factories = [
            'typesense' => new MM_Search_Model_Api_Typesense(),
            'meilisearch' => new MM_Search_Model_Api_Meilisearch()
        ];
    }

    /**
     * Create an adapter for the specified store ID
     */
    public function createAdapter($storeId): CmsIg\Seal\Adapter\AdapterInterface
    {
        $engineType = $this->_helper->getEngineType($storeId);
        
        if (!isset($this->_factories[$engineType])) {
            Mage::throwException(sprintf('Unsupported search engine type: %s', $engineType));
        }
        
        $factory = $this->_factories[$engineType];
        
        return $factory->create($storeId);
    }

    /**
     * Get available search engines as key-value pairs
     *
     * @return array Associative array of engine types and labels
     */
    public function getAvailableEngines(): array
    {
        return [
            'typesense' => 'Typesense',
            'meilisearch' => 'MeiliSearch'
        ];
    }
}