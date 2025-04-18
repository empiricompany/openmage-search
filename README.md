# TypeSense Search for OpenMage

Advanced search module for OpenMage using Typesense as search engine.

## Overview

![instantsearch](https://github.com/user-attachments/assets/7b3c4210-d537-456e-9848-3207d826c025)

MM Search is a module that replaces the default search engine in OpenMage with Typesense, a fast and typo-tolerant search engine. This module provides:

- Instant search with autocomplete
- Faceted search
- Typo tolerance
- Fast and relevant search results
- Easy configuration

## Requirements

- OpenMage LTS 19.x or higher
- PHP 7.4 or higher
- Typesense server (self-hosted or cloud)

### docker-compose.yml example for self-host a TypeSense instance with a dashboard
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
      - "5002:80"  # Puoi cambiare la porta se necessario
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

1. Add the Firegento repository to your Composer configuration:

```bash
composer config -g repositories.firegento composer https://packages.firegento.com
```

2. Install the module:

```bash
composer require empiricompany/openmage-search
```

This will automatically install the latest stable version of the module.

### Manual Installation

1. Download the latest release
2. Extract the files to your OpenMage root directory
3. Clear the cache

## Configuration

1. Go to System > Configuration > MM Search
2. Enable the module
3. Configure the Typesense connection settings:
   - Admin API Key
   - Search-Only API Key
   - Host
   - Port
   - Protocol
   - Path
   - Collection Name
4. Save the configuration

## Indexing

This module completely replaces the default search engine in OpenMage with Typesense. When products are indexed, they are automatically added to Typesense.

To rebuild the index:

1. Go to System > Index Management
2. Select "Catalog Search Index"
3. Choose "Reindex Data" from the actions dropdown
4. Click "Submit"

## Features

### Instant Search

The module replaces the default search box with an instantsearch.js interface that shows results as you type.

### Faceted Search

Users can filter search results by:
- Categories
- Manufacturers
- Price range
and other attributes with "Use In Search Results Layered Navigation": Yes

### Typo Tolerance

Typesense's typo tolerance ensures that users find what they're looking for even if they make typos.

## Customization

### Templates

You can customize the search interface by modifying the following templates:

- `app/design/frontend/base/default/template/mm/search/instantsearch.phtml`

### CSS

You can customize the appearance by modifying the CSS files:

- `skin/frontend/base/default/css/mm_search/instantsearch.css`

### JavaScript

You can customize the behavior by modifying the JavaScript file:

- `skin/frontend/base/default/js/mm_search/instantsearch-custom.js`

## Troubleshooting

### Common Issues

1. **Connection Failed**: Make sure your Typesense server is running and accessible from your OpenMage server.
2. **No Results**: Check if the products are properly indexed in Typesense. Try rebuilding the Catalog Search Index.
3. **JavaScript Errors**: Check the browser console for any JavaScript errors.

## License

This module is licensed under the MIT License.
