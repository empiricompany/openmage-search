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
        app.templates.extend('hit', {
            placeholderUrl: '/skin/frontend/mytheme/images/placeholder.jpg',

            renderCustomBadge(hit, html) {
                return hit.custom_attr
                    ? html`<span class="custom">${hit.custom_attr}</span>`
                    : '';
            }
        });

        app.widgets.configure('stats', {
            cssClasses: { text: 'my-custom-class' }
        });

        app.widgets.remove('categoryNames');
        */
    });

    window.instantSearchApp = app;

})();