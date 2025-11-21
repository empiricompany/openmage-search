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
     * Cache for child products to avoid loading them multiple times
     * @var array
     */
    private $_childProductsCache = [];
    
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
            $code = $attribute->getAttributeCode();
            
            // Don't override base fields - they have custom properties like 'infix'
            if (!isset($fields[$code])) {
                $fields[$code] = $this->createSchemaField($attribute);
            }
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
            // Set store on attribute for correct option translations
            $attribute->setStoreId($storeId);
            $productData[$code] = $this->getAttributeValue($product, $attribute, $storeId);
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
        // Multiple if: multiselect OR (select + configurable)
        $multiple = $attribute->getFrontendInput() === 'multiselect'
            || ($attribute->getFrontendInput() === 'select' && $attribute->getIsConfigurable());
        $filterable = (bool) $attribute->getIsFilterableInSearch();
        $sortable = (bool) $attribute->getUsedForSortBy();
        $searchable = ($type === 'text');
        
        return array(
            'type' => $type,
            'multiple' => $multiple,
            'filterable' => $filterable,
            'sortable' => ($sortable && !$multiple), // Only single-valued fields can be sortable
            'searchable' => $searchable,
            'index' => $searchable,  // Index only searchable fields
            'optional' => true        // All dynamic attributes are optional
        );
    }

    /**
     * Get attribute value for product
     *
     * @param Mage_Catalog_Model_Product $product
     * @param Mage_Catalog_Model_Resource_Eav_Attribute $attribute
     * @param int|null $storeId Store ID for translations
     * @return mixed
     */
    public function getAttributeValue($product, $attribute, $storeId = null)
    {
        $code = $attribute->getAttributeCode();
        $type = $this->getFieldType($attribute);
        $isConfigurableAttr = ($attribute->getFrontendInput() === 'select' && $attribute->getIsConfigurable());
        $isMultiselect = ($attribute->getFrontendInput() === 'multiselect');
        
        // For configurable products with configurable attributes, aggregate child values
        if ($product->getTypeId() === 'configurable' && $isConfigurableAttr) {
            // Cache child products
            $cacheKey = $product->getId() . '_' . $storeId;
            if (!isset($this->_childProductsCache[$cacheKey])) {
                $this->_childProductsCache[$cacheKey] = $product->getTypeInstance(true)->getUsedProducts(null, $product);
            }
            
            $labels = [];
            foreach ($this->_childProductsCache[$cacheKey] as $childProduct) {
                /**
                 * @var Mage_Catalog_Model_Product $childProduct
                 */
                if (!$childProduct->getStockItem()->getIsInStock()) {
                    continue;
                }
                $optionId = $childProduct->getData($code);
                if ($optionId) {
                    $label = $attribute->getSource()->getOptionText($optionId);
                    if ($label && $label !== false) {
                        $labels[] = (string) $label;
                    }
                }
            }
            
            if (!empty($labels)) {
                return array_values(array_unique($labels));
            }
        }
        
        // Standard value
        $value = null;
        switch ($type) {
            case 'float':
                $value = (float) $product->getData($code);
                break;
            case 'integer':
                $value = (int) $product->getData($code);
                break;
            default:
                if ($attribute->getFrontendInput() === 'select' || $isMultiselect) {
                    // Use source model for correct store translations
                    $optionId = $product->getData($code);
                    if ($optionId) {
                        if($isMultiselect) {
                            $optionIds = explode(',', $optionId);
                            $values = [];
                            foreach ($optionIds as $id) {
                                $label = $attribute->getSource()->getOptionText($id);
                                if ($label && $label !== false) {
                                    $values[] = (string) $label;
                                }
                            }
                            return $values;
                        }
                        $value = (string) $attribute->getSource()->getOptionText($optionId);
                    }
                } else {
                    $value = (string) $product->getData($code);
                }
        }
        
        // Return array for configurable/multiselect attributes
        if ($isConfigurableAttr || $isMultiselect) {
            return $value ? [$value] : [];
        }
        
        return $value;
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
                'optional' => false,
            ),
            'sku' => array(
                'type' => 'text',
                'multiple' => false,
                'filterable' => false,  // Not a facet/filter
                'sortable' => false,
                'searchable' => true,
                'index' => true,
                'infix' => true,
                'optional' => true,
            ),
            'name' => array(
                'type' => 'text',
                'multiple' => false,
                'filterable' => false,
                'sortable' => true,
                'searchable' => true,
                'index' => true,
                'optional' => false,
            ),
            'price' => array(
                'type' => 'float',
                'range_index' => true,
                'multiple' => false,
                'filterable' => true,
                'sortable' => true,
                'searchable' => false,
                'optional' => false,  // Required: used as default sorting field
            ),
            'special_price' => array(
                'type' => 'float',
                'range_index' => true,
                'multiple' => false,
                'filterable' => true,
                'sortable' => true,
                'searchable' => false,
                'optional' => true,
            ),
            'has_discount' => array(
                'type' => 'bool',
                'multiple' => false,
                'filterable' => true,
                'sortable' => false,
                'searchable' => false,
                'optional' => true,
            ),
            'news_from_date' => array(
                'type' => 'text',
                'multiple' => false,
                'filterable' => false,
                'sortable' => false,
                'searchable' => false,
                'optional' => true,
            ),
            'news_to_date' => array(
                'type' => 'text',
                'multiple' => false,
                'filterable' => false,
                'sortable' => false,
                'searchable' => false,
                'optional' => true,
            ),
            'url_key' => array(
                'type' => 'text',
                'multiple' => false,
                'filterable' => false,
                'sortable' => false,
                'searchable' => false,
                'optional' => true,
            ),
            'request_path' => array(
                'type' => 'text',
                'multiple' => false,
                'filterable' => false,
                'sortable' => false,
                'searchable' => false,
                'optional' => true,
            ),
            'category_names' => array(
                'type' => 'text',
                'multiple' => true,
                'filterable' => true,
                'sortable' => false,
                'searchable' => true,
                'index' => true,
                'optional' => true,
            ),
            'thumbnail' => array(
                'type' => 'text',
                'multiple' => false,
                'filterable' => false,
                'sortable' => false,
                'searchable' => false,
                'optional' => true,
            ),
            'thumbnail_small' => array(
                'type' => 'text',
                'multiple' => false,
                'filterable' => false,
                'sortable' => false,
                'searchable' => false,
                'optional' => true,
            ),
            'thumbnail_medium' => array(
                'type' => 'text',
                'multiple' => false,
                'filterable' => false,
                'sortable' => false,
                'searchable' => false,
                'optional' => true,
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
            'name' => (string) $product->getName(),
            'price' => (float) $product->getPrice(),
            'special_price' => (float) $product->getFinalPrice(),
            'has_discount' => (bool) ($product->getFinalPrice() < $product->getPrice()),
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
