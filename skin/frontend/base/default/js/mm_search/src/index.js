/**
 * InstantSearch Bundle Entry Point
 * This file is bundled by Vite and exposes the API on window
 */

// Re-export instantsearch and adapter for direct usage if needed
import instantsearch from 'instantsearch.js';
import TypesenseInstantSearchAdapter from 'typesense-instantsearch-adapter';

// Import all modules
import { Utils } from './Utils.js';
import { EventBus } from './EventBus.js';
import { TemplateRegistry } from './TemplateRegistry.js';
import { WidgetRegistry } from './WidgetRegistry.js';
import { HitHelpers } from './HitHelpers.js';
import { HitTemplate } from './HitTemplate.js';
import { InstantSearchApp } from './InstantSearchApp.js';
import { OverlayManager } from './OverlayManager.js';

/**
 * Factory function to create and initialize the search application
 * Called by instantsearch-custom.js after customizations
 */
function createSearchApp(config) {
    const app = new InstantSearchApp(config);
    return app;
}

/**
 * Initialize the full search with overlay management
 */
function initSearch(config, customizeFn = null) {
    const app = createSearchApp(config);
    
    // Allow customizations before init
    if (typeof customizeFn === 'function') {
        customizeFn(app);
    }
    
    app.init();
    
    const overlay = new OverlayManager(app);
    overlay.init();
    
    return { app, overlay };
}

// Expose on window for use by instantsearch-custom.js
window.MMSearch = {
    // Core classes
    InstantSearchApp,
    OverlayManager,
    HitTemplate,
    HitHelpers,
    
    // Registries
    TemplateRegistry,
    WidgetRegistry,
    EventBus,
    
    // Utilities
    Utils,
    
    // Factory functions
    createSearchApp,
    initSearch,
    
    // Original libraries (for advanced usage)
    instantsearch,
    TypesenseInstantSearchAdapter
};

// Also expose instantsearch globally for compatibility
window.instantsearch = instantsearch;
window.TypesenseInstantSearchAdapter = TypesenseInstantSearchAdapter;

console.log('[MMSearch] Bundle loaded');