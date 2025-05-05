<?php

declare(strict_types=1);

class MM_Search_Model_Observer
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
     * Add layout handle if module is enabled
     *
     * @param Varien_Event_Observer $observer
     */
    public function addLayoutHandleIfEnabled(Varien_Event_Observer $observer): void
    {

        if ($this->_helper->isEnabled()) {
            $update = $observer->getEvent()->getLayout()->getUpdate();
            $update->addHandle('mm_search_instantsearch');
        }
    }

    /**
     * Synchronize schema with attribute
     * @param  Varien_Event_Observer $observer
     */
    public function syncSchemaWithAttribute(Varien_Event_Observer $observer): void
    {
        /**
         * @var Mage_Catalog_Model_Resource_Eav_Attribute $attribute
         */
        $attribute = $observer->getData('attribute');

        // Skip if attribute search options are not changed
        $isSearchableChanged = $attribute->getIsSearchable() != $attribute->getOrigData('is_searchable');
        $isFilterableChanged = $attribute->getIsFilterableInSearch() != $attribute->getOrigData('is_filterable_in_search');
        if (!$isSearchableChanged && !$isFilterableChanged) {
            return;
        }

        try {
            /**
            * @var MM_Search_Model_Api $_modelApi
            */
            $_modelApi = Mage::getSingleton('mm_search/api');
            foreach (Mage::app()->getStores() as $store) {
                if (!$this->_helper->isEnabled($store->getId())) {
                    continue;
                }
                Mage::getSingleton('adminhtml/session')->addSuccess(
                    Mage::helper('mm_search')->__('Update schema field "%s" for %s', $attribute->getAttributeCode(), $store->getName())
                );
                $_modelApi->setStoreId($store->getId())->updateSchema($attribute);
            }
        } catch (Exception $e) {
            Mage::logException($e);
        }
    }
}
