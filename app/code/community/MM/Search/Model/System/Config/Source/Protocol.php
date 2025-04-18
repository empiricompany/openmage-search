<?php
/**
 * Protocol source model
 */
class MM_Search_Model_System_Config_Source_Protocol
{
    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => 'http', 'label' => Mage::helper('mm_search')->__('HTTP')],
            ['value' => 'https', 'label' => Mage::helper('mm_search')->__('HTTPS')]
        ];
    }

    /**
     * Get options in "key-value" format
     *
     * @return array
     */
    public function toArray()
    {
        return [
            'http' => Mage::helper('mm_search')->__('HTTP'),
            'https' => Mage::helper('mm_search')->__('HTTPS')
        ];
    }
}