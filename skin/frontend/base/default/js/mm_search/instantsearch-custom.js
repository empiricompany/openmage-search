/**
 * InstantSearch Custom Configuration (Base/Default)
 *
 * Themes can override this file to customize the InstantSearch application.
 * Copy to your theme's js/mm_search/ directory and modify as needed.
 */
(() => {
    'use strict';

    const config = window.instantSearchConfig;
    if (!config) {
        console.warn('InstantSearch config not found');
        return;
    }

    if (!window.MMSearch) {
        console.error('MMSearch bundle not loaded');
        return;
    }

    const { app, overlay } = window.MMSearch.initSearch(config, (app) => {
        // Customizations go here (before init)
        // const helpers = window.MMSearch.HitHelpers;
        
        /*
        // --- Hit Template Customization ---
        app.templates.extend('hit', {
            placeholderUrl: '/skin/frontend/mytheme/images/placeholder.jpg',

            renderCustomBadge(hit, html) {
                return hit.custom_attr
                    ? html`<span class="custom">${hit.custom_attr}</span>`
                    : '';
            }
        });

        // --- Widget Configuration ---
        app.widgets.configure('stats', {
            cssClasses: { text: 'my-custom-class' }
        });

        // --- Remove Widget ---
        app.widgets.remove('categoryNames');

        // --- Disable Swatches ---
        // To disable swatches for all facets, clear the swatches config:
        // config.swatches = {};
        //
        // Or disable for a specific facet:
        // if (config.swatches) delete config.swatches['colore'];
        */
    });

    window.instantSearchApp = app;

})();