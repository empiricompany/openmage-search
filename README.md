# Search Module for OpenMage

Advanced search module for OpenMage with native multi-engine support (Typesense, Meilisearch) and instantsearch.js for frontend.

## Overview

![instantsearch](https://github.com/user-attachments/assets/919c8179-c37c-49b1-bcfb-ed2979fdf93c)

MM Search replaces the default OpenMage search with modern search engines, providing instant search with rich features and zero performance impact.

## Key Features

### 🎯 Precise SKU Search with Infix
Product SKUs support **partial matching** on Typesense:
- Search "123" finds "ABC-123-XYZ"
- Search "ABC" finds "ABC-123-XYZ"  
- Infix search enabled by default for maximum flexibility

### 📱 Mobile-First Responsive Theme
- **InstantSearch.js** integration with RWD theme compatibility
- Fully responsive search interface
- Optimized for mobile, tablet, and desktop
- Seamless integration with OpenMage RWD base theme

### 🏷️ Smart Product Labels
- **"New" badge**: Products with `news_from_date` and `news_to_date`
- **"Sale" badge**: Products with active `special_price`
- Auto-displayed in search results
- Customizable via templates

### ⚙️ Configurable Attribute Filters
- Any attribute can be a filter facet
- Enable via: **Catalog > Attributes > [Your Attribute] > "Use In Search Results Layered Navigation"**
- Automatic facet generation
- Supports: select, multiselect, price ranges

### ⚡ Zero PageSpeed Impact
- **Lazy loading** of InstantSearch libraries
- Scripts loaded only when needed
- No blocking resources
- Minimal impact on Core Web Vitals
- CDN-ready assets

### 🔧 Multi-Engine Architecture
- **Typesense**: Fast, typo-tolerant (default)
- **Meilisearch**: Alternative engine
- Extensible for Algolia, Elasticsearch, etc.
- Auto-discovery via Composer
- Switch engines without code changes

## Requirements

- OpenMage LTS 19.x or higher
- PHP 7.4 or higher
- Typesense server (self-hosted or cloud)

### Self-Host Typesense with Docker

```yml
services:
  typesense:
    image: typesense/typesense:27.1
    ports:
      - "8108:8108"
    environment:
      TYPESENSE_DATA_DIR: /data
      TYPESENSE_API_KEY: S3CR3T
      TYPESENSE_API_ALLOW_ORIGINS: "*"
    healthcheck:
      test: ["CMD-SHELL", "exit 0"]
      interval: 5s
      timeout: 5s
      retries: 20
    volumes:
      - typesense-data:/data

  typesense-dashboard:
    image: bfritscher/typesense-dashboard
    ports:
      - "5002:80"
    environment:
      TYPESENSE_API_URL: "http://typesense:8108"  
      TYPESENSE_API_KEY: "S3CR3T"
    depends_on:
      - typesense
    restart: always

volumes:
  typesense-data:
```

## Installation

### Via Composer
```bash
composer require empiricompany/openmage-search
```

This installs the module with Typesense support by default.

### Add Meilisearch Support (Optional)
```bash
composer require meilisearch/meilisearch-php
```

## Configuration

1. Go to **System > Configuration > MM Search**

2. **General Settings**:
   - Enable MM Search: **Yes**
   - Search Engine Type: Select **Typesense** or **Meilisearch**
   - Debug Mode: Enable only for troubleshooting

3. **Connection Settings**:
   - Admin API Key: Full access key for indexing
   - Search-Only API Key: Public key for frontend
   - Proxy Frontend Request: Enable if self-hosting without SSL
   - Protocol: **http** or **https**
   - Host: Your server hostname
   - Port: **8108** (Typesense default)
   - Collection Name: Index name (different per store if needed)

4. Save configuration

5. **Reindex**: System > Index Management > Catalog Search Index > Reindex Data

## Indexing

The module completely replaces OpenMage's default search engine.

**To rebuild the index:**
1. System > Index Management
2. Select "Catalog Search Index"
3. Actions: "Reindex Data"
4. Submit

**Force schema recreation** (after config changes):
- Select "Catalog Search Index"
- Enable "Drop Index" option
- Reindex

## Customization

### Templates

Customize search interface:
- `app/design/frontend/base/default/template/mm/search/instantsearch.phtml`

### CSS

Customize appearance:
- `skin/frontend/base/default/css/mm_search/instantsearch.css`

### JavaScript

Customize behavior:
- `skin/frontend/base/default/js/mm_search/instantsearch-custom.js`

## Developing New Search Engines

The module uses a flexible architecture to support multiple search engines. Here's how to add a new engine:

### Step 1: Create Engine Class

Create your engine class in `app/code/community/MM/Search/Model/Search/Engine/`:

```php
<?php
/**
 * Your Search Engine Implementation
 */
class MM_Search_Model_Search_Engine_YourEngine extends MM_Search_Model_Search_Engine_Abstract
{
    /**
     * Initialize your search client
     */
    protected function _initClient()
    {
        $this->_client = new \YourEngine\Client([
            'api_key' => $this->_helper->getAdminApiKey($this->_storeId),
            'host' => $this->_helper->getHost($this->_storeId),
            'port' => (int)$this->_helper->getPort($this->_storeId),
        ]);
    }
    
    /**
     * Create or update collection schema
     *
     * @param string $collectionName
     * @param array $fields Standard field format from Schema helper
     * @return array Collection info
     */
    public function createOrUpdateSchema($collectionName, array $fields)
    {
        // Convert standard format to your engine's schema
        $engineFields = [];
        foreach ($fields as $name => $props) {
            $engineFields[] = [
                'name' => $name,
                'type' => $this->_mapFieldType($props),
                // Map other properties...
            ];
        }
        
        // Create index/collection in your engine
        return $this->_client->createIndex($collectionName, $engineFields);
    }
    
    /**
     * Import a batch of documents
     * 
     * NOTE: Batching is automatic! You only implement the import logic.
     * The parent class handles batching via bulkIndex()
     *
     * @param string $collectionName
     * @param array $batch Documents to import
     * @return array Error messages (empty if successful)
     */
    protected function _importBatch($collectionName, $batch)
    {
        $errors = [];
        
        try {
            $result = $this->_client->addDocuments($collectionName, $batch);
            // Check for errors...
        } catch (Exception $e) {
            $errors[] = $e->getMessage();
        }
        
        return $errors;
    }
    
    /**
     * Delete single document
     */
    public function deleteDocument($collectionName, $documentId)
    {
        $this->_helper->debug(sprintf('Deleting document %s', $documentId));
        $this->_client->deleteDocument($collectionName, $documentId);
    }
    
    /**
     * Drop entire collection
     */
    public function dropCollection($collectionName)
    {
        $this->_helper->debug(sprintf('Dropping collection %s', $collectionName));
        $this->_client->deleteIndex($collectionName);
    }
    
    /**
     * Check if collection exists
     */
    public function collectionExists($collectionName)
    {
        return $this->_client->indexExists($collectionName);
    }
    
    /**
     * Engine type identifier (lowercase, no spaces)
     */
    public static function getType()
    {
        return 'yourengine';
    }
    
    /**
     * Engine display label
     */
    public static function getLabel()
    {
        return 'Your Engine';
    }
    
    /**
     * InstantSearch.js adapter filename
     */
    public static function getInstantSearchAdapterJs()
    {
        return 'yourengine-instantsearch-adapter.min.js';
    }
}
```

### Step 2: Install Engine SDK

Add your engine's SDK to `composer.json`:

```json
{
    "require": {
        "yourcompany/yourengine-php": "^1.0"
    }
}
```

Or install via Composer:
```bash
composer require yourcompany/yourengine-php
```

### Step 3: Auto-Registration

The Factory auto-discovers your engine via Composer:

```php
// Model/Api/Factory.php already handles this:
if (\Composer\InstalledVersions::isInstalled('yourcompany/yourengine-php')) {
    $this->registerEngine('MM_Search_Model_Search_Engine_YourEngine');
}
```

Your engine will automatically appear in **System > Configuration > MM Search > Search Engine Type**.

### Step 4: Add InstantSearch Adapter

Add your engine's InstantSearch.js adapter to:
```
skin/frontend/base/default/js/mm_search/yourengine-instantsearch-adapter.min.js
```

### Standard Field Format

Your engine receives fields in this normalized format:

```php
[
    'field_name' => [
        'type' => 'identifier|text|integer|float',
        'multiple' => bool,      // Array type?
        'filterable' => bool,    // Facet/filter?
        'sortable' => bool,      // Can sort by this?
        'searchable' => bool,    // Full-text search?
        'infix' => bool,         // Partial matching? (optional)
    ]
]
```

Map these to your engine's schema format in `createOrUpdateSchema()`.

### Benefits of Extending Abstract

By extending `MM_Search_Model_Search_Engine_Abstract`:
- ✅ **Automatic batching**: `bulkIndex()` handled for you
