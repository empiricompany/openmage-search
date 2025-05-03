<?php

declare(strict_types=1);

use CmsIg\Seal\Schema\Field;
use CmsIg\Seal\Schema\Schema;
use CmsIg\Seal\Schema\Index;

class MM_Search_Helper_Schema extends Mage_Core_Helper_Abstract
{
    /**
     * Get complete schema with all fields (base + attributes)
     */
    public function getCompleteSchema(string $collectionName): Schema
    {
        $fields = $this->getAllSchemaFields();

        return new Schema([
            $collectionName => new Index($collectionName, $fields),
        ]);
    }

    /**
     * Get all schema fields (base + attributes)
     */
    public function getAllSchemaFields(): array
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
     */
    public function getCompleteProductData(Mage_Catalog_Model_Product $product, int $storeId): array
    {
        // Start with base data
        $productData = $this->getBaseProductData($product, $storeId);

        // Add attribute data
        $attributeCollection = $this->getSearchableAttributes();

        foreach ($attributeCollection as $attribute) {
            $code = $attribute->getAttributeCode();
            $productData[$code] = $this->getAttributeValue($product, $attribute);
        }

        return $productData;
    }

    /**
     * Get searchable attributes
     */
    public function getSearchableAttributes(): Mage_Catalog_Model_Resource_Product_Attribute_Collection
    {
        return Mage::getResourceModel('catalog/product_attribute_collection')
            ->addIsSearchableFilter();
    }

    /**
     * Get field type for attribute
     */
    public function getFieldType(Mage_Catalog_Model_Resource_Eav_Attribute $attribute): string
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
     * Create schema field for attribute
     */
    public function createSchemaField(Mage_Catalog_Model_Resource_Eav_Attribute $attribute): Field\AbstractField
    {
        $code = $attribute->getAttributeCode();
        $type = $this->getFieldType($attribute);
        $multiple = $attribute->getFrontendInput() === 'multiselect';
        $filterable = (bool) $attribute->getIsFilterableInSearch();
        $sortable = (bool) $attribute->getUsedForSortBy();

        return match($type) {
            'float' => new Field\FloatField(
                $code,
                multiple: $multiple,
                filterable: $filterable,
                sortable: $sortable,
                searchable: false
            ),
            'integer' => new Field\IntegerField(
                $code,
                multiple: $multiple,
                filterable: $filterable,
                sortable: $sortable,
                searchable: false
            ),
            default => new Field\TextField(
                $code,
                multiple: $multiple,
                filterable: $filterable,
                sortable: $sortable,
                searchable: true
            )
        };
    }

    /**
     * Get attribute value for product
     */
    public function getAttributeValue($product, $attribute): mixed
    {
        $code = $attribute->getAttributeCode();
        $type = $this->getFieldType($attribute);

        return match($type) {
            'float' => (float) $product->getData($code),
            'integer' => (int) $product->getData($code),
            default => $attribute->getFrontendInput() === 'select'
                ? (string) $product->getAttributeText($code)
                : (string) $product->getData($code)
        };
    }

    /**
     * Get base field definitions
     */
    public function getBaseFieldDefinitions(): array
    {
        return [
            'id' => [
                'type' => 'identifier',
                'multiple' => false,
                'filterable' => false,
                'sortable' => false,
                'searchable' => false,
            ],
            'sku' => [
                'type' => 'text',
                'multiple' => false,
                'filterable' => true,
                'sortable' => false,
                'searchable' => true,
            ],
            'url_key' => [
                'type' => 'text',
                'multiple' => false,
                'filterable' => false,
                'sortable' => false,
                'searchable' => false,
            ],
            'request_path' => [
                'type' => 'text',
                'multiple' => false,
                'filterable' => false,
                'sortable' => false,
                'searchable' => false,
            ],
            'category_names' => [
                'type' => 'text',
                'multiple' => true,
                'filterable' => true,
                'sortable' => false,
                'searchable' => true,
            ],
            'thumbnail' => [
                'type' => 'text',
                'multiple' => false,
                'filterable' => false,
                'sortable' => false,
                'searchable' => false,
            ],
            'thumbnail_small' => [
                'type' => 'text',
                'multiple' => false,
                'filterable' => false,
                'sortable' => false,
                'searchable' => false,
            ],
            'thumbnail_medium' => [
                'type' => 'text',
                'multiple' => false,
                'filterable' => false,
                'sortable' => false,
                'searchable' => false,
            ],
        ];
    }

    /**
     * Get base schema fields
     */
    public function getBaseSchemaFields(): array
    {
        $fields = [];
        $definitions = $this->getBaseFieldDefinitions();

        foreach ($definitions as $name => $props) {
            $fields[$name] = match($props['type']) {
                'identifier' => new Field\IdentifierField($name),
                'float' => new Field\FloatField(
                    $name,
                    multiple: $props['multiple'],
                    filterable: $props['filterable'],
                    sortable: $props['sortable'],
                    searchable: $props['searchable']
                ),
                'integer' => new Field\IntegerField(
                    $name,
                    multiple: $props['multiple'],
                    filterable: $props['filterable'],
                    sortable: $props['sortable'],
                    searchable: $props['searchable']
                ),
                default => new Field\TextField(
                    $name,
                    multiple: $props['multiple'],
                    filterable: $props['filterable'],
                    sortable: $props['sortable'],
                    searchable: $props['searchable']
                )
            };
        }

        return $fields;
    }

    /**
     * Get base product data
     */
    public function getBaseProductData(Mage_Catalog_Model_Product $product, int $storeId): array
    {
        $data = [];
        $definitions = $this->getBaseFieldDefinitions();

        foreach (array_keys($definitions) as $field) {
            $data[$field] = match($field) {
                'id' => (string) $product->getId(),
                'sku' => (string) $product->getSku(),
                'url_key' => (string) $product->getUrlKey(),
                'request_path' => (string) $product->getRequestPath() ?: 'catalog/product/view/id/' . $product->getId(),
                'category_names' => $this->_getCategoryNames($product, $storeId),
                'thumbnail' => (string) $product->getThumbnail(),
                'thumbnail_small' => (string) $this->_getResizedImageUrl($product, 100, 100),
                'thumbnail_medium' => (string) $this->_getResizedImageUrl($product, 300, 300)
            };
        }

        return $data;
    }

    /**
     * Get category names for product
     */
    private function _getCategoryNames(Mage_Catalog_Model_Product $product, int $storeId): array
    {
        $categoryCollection = $product->getCategoryCollection()
            ->setStore($storeId)
            ->addAttributeToSelect('name')
            ->addAttributeToFilter('is_active', true);
        return $categoryCollection->getColumnValues('name');
    }

    /**
     * Get resized image URL
     */
    private function _getResizedImageUrl(Mage_Catalog_Model_Product $product, int $width, int $height): string
    {
        try {
            $imageHelper = Mage::helper('catalog/image');
            $imageUrl = $imageHelper->init($product, 'thumbnail')
                ->resize($width, $height);
            return (string) $imageUrl ?: Mage::getDesign()->getSkinUrl('images/catalog/product/placeholder/image.jpg');
        } catch (Exception $e) {
            return Mage::getDesign()->getSkinUrl('images/catalog/product/placeholder/image.jpg');
        }
    }

    /**
     * Get index name for a store
     */
    public function getIndexName(int $storeId): string
    {
        return Mage::getSingleton('mm_search/api')->getCollectionName($storeId);
    }
}
