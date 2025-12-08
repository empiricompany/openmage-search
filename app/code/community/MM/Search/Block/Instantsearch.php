<?php
class MM_Search_Block_Instantsearch extends Mage_Page_Block_Html_Header
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

    /**
     * Get swatch dimensions for layered navigation
     * @return array|null
     */
    public function getSwatchDimensions()
    {
        if (!Mage::helper('core')->isModuleEnabled('Mage_ConfigurableSwatches')) {
            return null;
        }

        /** @var Mage_ConfigurableSwatches_Helper_Swatchdimensions $dimHelper */
        $dimHelper = Mage::helper('configurableswatches/swatchdimensions');

        return array(
            'innerWidth'   => $dimHelper->getInnerWidth(Mage_ConfigurableSwatches_Helper_Swatchdimensions::AREA_LAYER),
            'innerHeight'  => $dimHelper->getInnerHeight(Mage_ConfigurableSwatches_Helper_Swatchdimensions::AREA_LAYER),
            'outerWidth'   => $dimHelper->getOuterWidth(Mage_ConfigurableSwatches_Helper_Swatchdimensions::AREA_LAYER),
            'outerHeight'  => $dimHelper->getOuterHeight(Mage_ConfigurableSwatches_Helper_Swatchdimensions::AREA_LAYER),
        );
    }

    /**
     * Get swatch data for attributes (URLs and dimensions)
     * @return array
     */
    public function getSwatchData()
    {
        $swatchData = array();
        if (!Mage::helper('core')->isModuleEnabled('Mage_ConfigurableSwatches')) {
            return $swatchData;
        }

        /** @var Mage_ConfigurableSwatches_Helper_Data $swatchHelper */
        $swatchHelper = Mage::helper('configurableswatches');
        /** @var Mage_ConfigurableSwatches_Helper_Productimg $swatchImageHelper */
        $swatchImageHelper = Mage::helper('configurableswatches/productimg');

        if (!$swatchHelper->isEnabled()) {
            return $swatchData;
        }

        $dimensions = $this->getSwatchDimensions();
        if (!$dimensions) {
            return $swatchData;
        }

        $swatchAttributes = $swatchHelper->getSwatchAttributeIds();

        foreach ($this->getFacetFields() as $attributeCode) {
            $attribute = Mage::getSingleton('eav/config')->getAttribute(Mage_Catalog_Model_Product::ENTITY, $attributeCode);

            if (in_array($attribute->getId(), $swatchAttributes)) {
                $options = $attribute->getSource()->getAllOptions(false);
                $swatchData[$attributeCode] = array(
                    'dimensions' => $dimensions,
                    'options' => array()
                );
                foreach ($options as $option) {
                    if (empty($option['label'])) {
                        continue;
                    }
                    $label = $option['label'];
                    $swatchUrl = $swatchImageHelper->getGlobalSwatchUrl(
                        null,
                        $label,
                        $dimensions['innerWidth'],
                        $dimensions['innerHeight']
                    );

                    if ($swatchUrl) {
                        $swatchData[$attributeCode]['options'][$label] = $swatchUrl;
                    }
                }
            }
        }

        return $swatchData;
    }
}