<?php
/**
 * Schema Helper
 *
 * Manages search schema definitions and product data mapping.
 * Returns field definitions as arrays (compatible with EngineInterface).
 *
 * @category   MM
 * @package    MM_Search
 * @author     Tony
 */
class MM_Search_Helper_Schema extends Mage_Core_Helper_Abstract
{
    /**
     * Get all schema fields (base + attributes)
     *
     * @return array Associative array of field definitions
     */
    public function getAllSchemaFields()
    {
        // Start with base fields
        $fields = $this->getBaseSchemaFields();

        // Add attribute fields
        $attributeCollection = $this->getSearchableAttributes();

        foreach ($attributeCollection as $attribute) {
            $fields[$attribute->getAttributeCode()] = $this->createSchemaField($attribute);
        }

        return $fields;
    }

    /**
     * Get complete product data (base + attributes)
     *
     * @param Mage_Catalog_Model_Product $product
     * @param int $storeId
     * @return array
     */
    public function getCompleteProductData(Mage_Catalog_Model_Product $product, $storeId)
    {
        // Add attribute data
        $attributeCollection = $this->getSearchableAttributes();

        foreach ($attributeCollection as $attribute) {
            $code = $attribute->getAttributeCode();
            $productData[$code] = $this->getAttributeValue($product, $attribute);
        }

        // Override with base data
        $productData = array_merge(
            $productData,
            $this->getBaseProductData($product, $storeId)
        );

        return $productData;
    }

    /**
     * Get searchable attributes collection
     *
     * @return Mage_Catalog_Model_Resource_Product_Attribute_Collection
     */
    public function getSearchableAttributes()
    {
        return Mage::getResourceModel('catalog/product_attribute_collection')
            ->addIsSearchableFilter();
    }

    /**
     * Get field type for attribute
     *
     * @param Mage_Catalog_Model_Resource_Eav_Attribute $attribute
     * @return string
     */
    public function getFieldType(Mage_Catalog_Model_Resource_Eav_Attribute $attribute)
    {
        $code = $attribute->getAttributeCode();

        if ($attribute->getBackendType() === 'decimal') {
            return 'float';
        } elseif (in_array($code, ['status', 'visibility'])) {
            return 'integer';
        } else {
            return 'text';
        }
    }

    /**
     * Create schema field definition for attribute
     * Returns array format compatible with EngineInterface
     *
     * @param Mage_Catalog_Model_Resource_Eav_Attribute $attribute
     * @return array Field definition
     */
    public function createSchemaField(Mage_Catalog_Model_Resource_Eav_Attribute $attribute)
    {
        $code = $attribute->getAttributeCode();
        $type = $this->getFieldType($attribute);
        $multiple = $attribute->getFrontendInput() === 'multiselect';
        $filterable = (bool) $attribute->getIsFilterableInSearch();
        $sortable = (bool) $attribute->getUsedForSortBy();
        $searchable = ($type === 'text');
        
        return array(
            'type' => $type,
            'multiple' => $multiple,
            'filterable' => $filterable,
            'sortable' => $sortable,
            'searchable' => $searchable,
            'optional' => true
        );
    }

    /**
     * Get attribute value for product
     *
     * @param Mage_Catalog_Model_Product $product
     * @param Mage_Catalog_Model_Resource_Eav_Attribute $attribute
     * @return mixed
     */
    public function getAttributeValue($product, $attribute)
    {
        $code = $attribute->getAttributeCode();
        $type = $this->getFieldType($attribute);
        
        switch ($type) {
            case 'float':
                return (float) $product->getData($code);
            case 'integer':
                return (int) $product->getData($code);
            default:
                if ($attribute->getFrontendInput() === 'select') {
                    return (string) $product->getAttributeText($code);
                } else {
                    return (string) $product->getData($code);
                }
        }
    }

    /**
     * Get base field definitions
     *
     * @return array
     */
    public function getBaseFieldDefinitions()
    {
        return array(
            'id' => array(
                'type' => 'identifier',
                'multiple' => false,
                'filterable' => false,
                'sortable' => false,
                'searchable' => false,
            ),
            'sku' => array(
                'type' => 'text',
                'multiple' => false,
                'filterable' => true,
                'sortable' => false,
                'searchable' => true,
            ),
            'price' => array(
                'type' => 'float',
                'multiple' => false,
                'filterable' => true,
                'sortable' => true,
                'searchable' => false,
            ),
            'special_price' => array(
                'type' => 'float',
                'multiple' => false,
                'filterable' => true,
                'sortable' => true,
                'searchable' => false,
            ),
            'news_from_date' => array(
                'type' => 'text',
                'multiple' => false,
                'filterable' => false,
                'sortable' => false,
                'searchable' => false,
            ),
            'news_to_date' => array(
                'type' => 'text',
                'multiple' => false,
                'filterable' => false,
                'sortable' => false,
                'searchable' => false,
            ),
            'url_key' => array(
                'type' => 'text',
                'multiple' => false,
                'filterable' => false,
                'sortable' => false,
                'searchable' => false,
            ),
            'request_path' => array(
                'type' => 'text',
                'multiple' => false,
                'filterable' => false,
                'sortable' => false,
                'searchable' => false,
            ),
            'category_names' => array(
                'type' => 'text',
                'multiple' => true,
                'filterable' => true,
                'sortable' => false,
                'searchable' => true,
            ),
            'thumbnail' => array(
                'type' => 'text',
                'multiple' => false,
                'filterable' => false,
                'sortable' => false,
                'searchable' => false,
            ),
            'thumbnail_small' => array(
                'type' => 'text',
                'multiple' => false,
                'filterable' => false,
                'sortable' => false,
                'searchable' => false,
            ),
            'thumbnail_medium' => array(
                'type' => 'text',
                'multiple' => false,
                'filterable' => false,
                'sortable' => false,
                'searchable' => false,
            ),
        );
    }

    /**
     * Get base schema fields (same as definitions - kept for BC)
     *
     * @return array
     */
    public function getBaseSchemaFields()
    {
        return $this->getBaseFieldDefinitions();
    }

    /**
     * Get base product data
     *
     * @param Mage_Catalog_Model_Product $product
     * @param int $storeId
     * @return array
     */
    public function getBaseProductData(Mage_Catalog_Model_Product $product, $storeId)
    {
        $product->setStoreId($storeId);
        
        return array(
            'id' => (string) $product->getId(),
            'sku' => (string) $product->getSku(),
            'price' => (float) $product->getPrice(),
            'special_price' => (float) $product->getFinalPrice(),
            'news_from_date' => $product->getData('news_from_date') ? (string) $product->getData('news_from_date') : '',
            'news_to_date' => $product->getData('news_to_date') ? (string) $product->getData('news_to_date') : '',
            'url_key' => (string) $product->getUrlKey(),
            'request_path' => $product->getRequestPath() ? (string) $product->getRequestPath() : 'catalog/product/view/id/' . $product->getId(),
            'category_names' => $this->_getCategoryNames($product, $storeId),
            'thumbnail' => (string) $product->getThumbnail(),
            'thumbnail_small' => (string) $this->_getResizedImageUrl($product, 100, 100),
            'thumbnail_medium' => (string) $this->_getResizedImageUrl($product, 300, 300)
        );
    }

    /**
     * Get category names for product
     *
     * @param Mage_Catalog_Model_Product $product
     * @param int $storeId
     * @return array
     */
    private function _getCategoryNames(Mage_Catalog_Model_Product $product, $storeId)
    {
        $categoryCollection = $product->getCategoryCollection()
            ->setStore($storeId)
            ->addAttributeToSelect('name')
            ->addAttributeToFilter('is_active', true);
        return $categoryCollection->getColumnValues('name');
    }

    /**
     * Get resized image URL
     *
     * @param Mage_Catalog_Model_Product $product
     * @param int $width
     * @param int $height
     * @return string
     */
    private function _getResizedImageUrl(Mage_Catalog_Model_Product $product, $width, $height)
    {
        try {
            $imageHelper = Mage::helper('catalog/image');
            $imageUrl = $imageHelper->init($product, 'thumbnail')
                ->resize($width, $height);
            return $imageUrl ? (string) $imageUrl : Mage::getDesign()->getSkinUrl('images/catalog/product/placeholder/image.jpg');
        } catch (Exception $e) {
            return Mage::getDesign()->getSkinUrl('images/catalog/product/placeholder/image.jpg');
        }
    }

    /**
     * Get index name for a store
     *
     * @param int $storeId
     * @return string
     */
    public function getIndexName($storeId)
    {
        return Mage::getSingleton('mm_search/api')->getCollectionName($storeId);
    }
}
