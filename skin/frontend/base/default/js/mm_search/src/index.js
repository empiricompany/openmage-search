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
import { InlineAutocomplete } from './InlineAutocomplete.js';
import { SearchSuggestions } from './SearchSuggestions.js';
import { HashRouter } from './HashRouter.js';

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
    
    // Initialize suggestions components AFTER first render (when searchBox input exists)
    let searchSuggestions = null;
    let inlineAutocomplete = null;
    let suggestionsInitialized = false;
    
    const initSuggestions = () => {
        if (suggestionsInitialized) return;
        
        const searchBoxInput = document.querySelector('#typesense-searchbox input.ais-SearchBox-input');
        
        if (!searchBoxInput) {
            console.warn('[MMSearch] SearchBox input not found yet');
            return;
        }
        
        suggestionsInitialized = true;
        
        // Initialize unified search suggestions (dropdown with recent + suggestions)
        searchSuggestions = new SearchSuggestions(config);
        searchSuggestions.init('#typesense-searchbox input.ais-SearchBox-input');
        
        // Initialize inline autocomplete (typeahead ghost text in input)
        inlineAutocomplete = new InlineAutocomplete(config);
        inlineAutocomplete.init('#typesense-searchbox input.ais-SearchBox-input');
        
        // Save recent search when user presses Enter
        searchBoxInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && searchSuggestions) {
                const currentQuery = searchBoxInput.value.trim();
                if (currentQuery.length >= 2) {
                    searchSuggestions.addRecentSearch(currentQuery);
                }
            }
        });
        
        console.log('[MMSearch] Suggestions initialized');
    };
    
    // Wait for first render to initialize suggestions
    app.events.once('render', () => {
        // Small delay to ensure DOM is updated
        setTimeout(initSuggestions, 50);
    });
    
    // Expose overlay on window for loader compatibility
    window.InstantSearchOverlayManager = overlay;
    
    return {
        app,
        overlay,
        getSearchSuggestions: () => searchSuggestions,
        getInlineAutocomplete: () => inlineAutocomplete
    };
}

// Expose on window for use by instantsearch-custom.js
window.MMSearch = {
    // Core classes
    InstantSearchApp,
    OverlayManager,
    HitTemplate,
    HitHelpers,
    SearchSuggestions,
    InlineAutocomplete,
    HashRouter,
    
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