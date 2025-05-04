<?php

declare(strict_types=1);

/**
 * Source model for search engine types
 */
class MM_Search_Model_System_Config_Source_Engine
{
    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray(): array
    {
        $adapterManager = Mage::getSingleton('mm_search/adapter_manager');
        $engines = $adapterManager->getAvailableEngines();
        
        $options = [];
        foreach ($engines as $value => $label) {
            $options[] = [
                'value' => $value,
                'label' => $label
            ];
        }
        
        return $options;
    }

    /**
     * Get options in "key-value" format
     *
     * @return array
     */
    public function toArray(): array
    {
        $adapterManager = Mage::getSingleton('mm_search/adapter_manager');
        return $adapterManager->getAvailableEngines();
    }
}