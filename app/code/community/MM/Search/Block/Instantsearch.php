<?php
/**
 * Instantsearch Block
 */
class MM_Search_Block_Instantsearch extends Mage_Core_Block_Template
{
    /**
     * Get proxy path url
     * @return string
     */
    public function getProxyPath()
    {
        return (string) parse_url($this->getUrl('mm_search/search/proxy'), PHP_URL_PATH);
    }

    /**
     * Get attribute configurated for facet
     * @return array
     */
    public function getFacetFields()
    {
        $attributeCollection = Mage::getResourceModel('catalog/product_attribute_collection');
        $attributeCollection->addIsSearchableFilter();
        $attributeCollection->addIsFilterableInSearchFilter();
        $attributeCollection = $attributeCollection->getColumnValues('attribute_code');
        return $attributeCollection;
    }
}