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
        // ==========================================
        // Hit Template Customization
        // ==========================================
        app.templates.extend('hit', {
            placeholderUrl: '/skin/frontend/mytheme/images/placeholder.jpg',

            renderCustomBadge(hit, html) {
                return hit.custom_attr
                    ? html`<span class="custom">${hit.custom_attr}</span>`
                    : '';
            }
        });

        // ==========================================
        // Widget Configuration
        // ==========================================
        
        // --- Stats Widget ---
        app.widgets.configure('stats', {
            cssClasses: { text: 'my-custom-class' }
        });

        // --- Category Names Widget ---
        // Customize cssClasses for the "Show More" button
        app.widgets.configure('categoryNames', {
            operator: 'and',  // Change from 'or' to 'and'
            cssClasses: {
                showMore: 'btn btn-primary',
                disabledShowMore: 'btn btn-primary disabled',
                searchableInput: 'form-control'
            },
            templates: {
                showMoreText(data, { html }) {
                    return html`<span>${data.isShowingMore ? 'Show Less' : 'Show All'}</span>`;
                }
            }
        });

        // --- Infinite Hits Widget ---
        app.widgets.configure('infiniteHits', {
            cssClasses: {
                list: 'products-grid my-custom-grid',
                item: 'item',
                loadMore: 'btn btn-lg btn-primary',
                disabledLoadMore: 'btn btn-lg btn-primary disabled'
            }
        });

        // ==========================================
        // Dynamic Facets Configuration (NEW!)
        // ==========================================
        
        // --- Default configuration for ALL dynamic facets ---
        // This affects all facets defined in config.facetBy
        app.widgets.configure('_facetDefaults', {
            limit: 8,
            showMoreLimit: 50,
            cssClasses: {
                showMore: 'btn btn-sm btn-outline',
                disabledShowMore: 'btn btn-sm btn-outline disabled',
                searchableInput: 'form-control input-sm'
            },
            templates: {
                showMoreText(data, { html }) {
                    return html`<span>${data.isShowingMore ? 'Less' : 'More'}</span>`;
                }
            }
        });

        // --- Override for a specific facet (e.g., 'color') ---
        app.widgets.configure('facet:color', {
            searchable: false,  // Disable search for color facet
            limit: 20,
            cssClasses: {
                list: 'color-facet-list',
                showMore: 'btn-link text-primary'
            }
        });

        // --- Override for price range slider ---
        app.widgets.configure('facet:price', {
            cssClasses: {
                root: 'my-custom-price-slider'
            },
            tooltips: {
                format: (value) => '$' + Math.round(value).toLocaleString()
            }
        });

        // ==========================================
        // Remove/Disable Widgets
        // ==========================================
        app.widgets.remove('categoryNames');

        // ==========================================
        // Swatch Configuration
        // ==========================================
        // To disable swatches for all facets, clear the swatches config:
        // config.swatches = {};
        //
        // Or disable for a specific facet:
        // if (config.swatches) delete config.swatches['colore'];
        */
    });

    window.instantSearchApp = app;

})();