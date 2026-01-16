import instantsearch from 'instantsearch.js';
import {
    searchBox,
    stats,
    sortBy,
    currentRefinements,
    refinementList,
    toggleRefinement,
    rangeSlider,
    infiniteHits,
    configure
} from 'instantsearch.js/es/widgets';

import { Utils } from './Utils.js';
import { EventBus } from './EventBus.js';
import { TemplateRegistry } from './TemplateRegistry.js';
import { WidgetRegistry } from './WidgetRegistry.js';
import { HitTemplate } from './HitTemplate.js';
import { HitHelpers } from './HitHelpers.js';
import { HashRouter } from './HashRouter.js';

/**
 * Main InstantSearch Application
 */
export class InstantSearchApp {
    constructor(config) {
        this._config = config;
        this._search = null;
        this._started = false;
        this._hashRouter = null;
        this.currentHits = [];
        
        this.events = new EventBus();
        this.templates = new TemplateRegistry();
        this.widgets = new WidgetRegistry();
        
        this.templates.register('hit', HitTemplate);
        
        this._registerDefaultWidgets();
    }

    _registerDefaultWidgets() {
        const collectionName = this._config.collectionName;

        this.widgets.register('searchBox', {
            container: '#typesense-searchbox',
            placeholder: 'Cerca prodotti...',
            autofocus: true,
            searchAsYouType: true,
            queryHook: (query, refine) => {
                clearTimeout(this._debounceTimer);
                this._debounceTimer = setTimeout(() => refine(query), 200);
            },
            showReset: true,
            showSubmit: false,
            showLoadingIndicator: true,
            cssClasses: {
                root: 'search_mini_form',
                input: 'input-text',
            },
        });

        this.widgets.register('stats', {
            container: '#typesense-stats',
            templates: {
                text: ({ nbHits, processingTimeMS }) => 
                    `<strong>${nbHits}</strong> risultati trovati in ${processingTimeMS}ms`
            },
            cssClasses: {
                text: 'text-muted font-xs',
            },
        });

        this.widgets.register('stats2', {
            container: '#typesense-stats2',
            templates: {
                text: ({ nbHits }) => `${nbHits}`
            },
        });

        this.widgets.register('sortBy', {
            container: '#typesense-sort-by',
            items: [
                { label: 'Rilevanza', value: collectionName },
                { label: 'Prezzo (Da minore a maggiore)', value: `${collectionName}/sort/price:asc` },
                { label: 'Prezzo (Da maggiore a minore)', value: `${collectionName}/sort/price:desc` },
                { label: 'Nome (A-Z)', value: `${collectionName}/sort/name:asc` },
                { label: 'Nome (Z-A)', value: `${collectionName}/sort/name:desc` }
            ],
            cssClasses: {
                root: 'sort-by',
            },
        });

        this.widgets.register('currentRefinements', {
            container: '#current-refinements',
            transformItems: (items) => this._transformRefinements(items)
        });

        this.widgets.register('categoryNames', {
            container: '#typesense-category_names',
            attribute: 'category_names',
            operator: 'or',
            limit: 5,
            showMore: true,
            showMoreLimit: 100,
            searchable: true,
            searchablePlaceholder: 'Cerca categorie...',
            cssClasses: {
                root: '',
                list: '',
                item: '',
                showMore: 'btn btn-xs',
                disabledShowMore: 'btn btn-xs disabled',
                searchableInput: 'input-text'
            },
            templates: {
                showMoreText(data, { html }) {
                    return html`<span>${data.isShowingMore ? 'Mostra meno' : 'Mostra tutti'}</span>`;
                },
            }
        });

        // Default configuration for dynamic facets (can be overridden via facet:attributeName)
        this.widgets.register('_facetDefaults', {
            operator: 'or',
            limit: 10,
            showMore: true,
            showMoreLimit: 100,
            searchable: true,
            searchablePlaceholder: 'Cerca...',
            cssClasses: {
                root: '',
                list: '',
                item: '',
                showMore: 'btn btn-xs',
                disabledShowMore: 'btn btn-xs disabled',
                searchableInput: 'input-text'
            },
            templates: {
                showMoreText(data, { html }) {
                    return html`<span>${data.isShowingMore ? 'Mostra meno' : 'Mostra tutti'}</span>`;
                },
            }
        });

        this.widgets.register('hasDiscount', {
            container: '#typesense-has_discount',
            attribute: 'has_discount',
            on: true,
            templates: {
                labelText({ count }, { html }) {
                    return html` Solo prodotti in offerta`;
                },
            },
        });

        this.widgets.register('infiniteHits', {
            container: '#typesense-hits',
            cssClasses: {
                list: ['products-grid products-grid--max-6-col'],
                item: 'item',
                loadMore: 'button',
                disabledLoadMore: 'button'
            },
            templates: {
                empty: 'Nessun risultato trovato',
                showMoreText: 'Carica altri prodotti'
            },
            showMoreButton: true
        });
    }

    _transformRefinements(items) {
        return items.map(item => {
            const labelElement = document.getElementById('typesense-' + item.attribute);
            const readableLabel = labelElement
                ? labelElement.previousElementSibling.textContent.trim()
                : Utils.humanize(item.attribute);

            const transformedRefinements = item.refinements.map(ref => {
                if (ref.value === "true" || ref.value === "false") {
                    return { ...ref, label: readableLabel };
                }
                return ref;
            });

            return {
                ...item,
                label: readableLabel,
                refinements: transformedRefinements,
            };
        });
    }

    _createFacetWidgets() {
        const widgets = [];
        const facets = this._config.facetBy || [];
        const swatches = this._config.swatches || {};

        // Get default facet configuration from registry
        const facetDefaults = this.widgets.getConfig('_facetDefaults') || {};

        facets.forEach(facet => {
            if (facet === 'price') {
                // Price slider - check for override first
                const priceOverride = this.widgets.getConfig('facet:price') || {};
                const priceConfig = Utils.deepMerge({
                    container: `#typesense-${facet}`,
                    attribute: facet,
                    pips: false,
                    tooltips: {
                        format: (value) => '€' + Math.round(value).toLocaleString()
                    },
                    cssClasses: {
                        root: 'price-range-slider',
                    }
                }, priceOverride);
                
                widgets.push(rangeSlider(priceConfig));
            } else {
                const swatchConfig = swatches[facet];
                
                // Get facet-specific override (e.g., facet:color)
                const facetOverride = this.widgets.getConfig(`facet:${facet}`) || {};
                
                // Start with defaults, then merge override
                let widgetConfig = Utils.deepMerge(facetDefaults, facetOverride);
                
                // Set container and attribute (always override these)
                widgetConfig.container = `#typesense-${facet}`;
                widgetConfig.attribute = facet;

                // Apply swatch configuration if available
                if (swatchConfig && swatchConfig.options) {
                    widgetConfig.searchable = false;
                    // Create a new templates object to avoid mutating defaults
                    widgetConfig.templates = { ...(widgetConfig.templates || {}) };
                    widgetConfig.templates.item = (item, { html }) => {
                        const labelText = item.label;
                        const imageUrl = swatchConfig.options[labelText];
                        const count = item.count;
                        const isRefined = item.isRefined;
                        const dimensions = swatchConfig.dimensions || { outerWidth: 23, outerHeight: 23, innerWidth: 21, innerHeight: 21 };
                        const labelStyle = `height: ${dimensions.outerHeight}px; width: ${dimensions.outerWidth}px;`;

                        if (imageUrl) {
                            const linkClass = isRefined ? 'swatch-link has-image selected' : 'swatch-link has-image';
                            return html`
                                <label class="ais-RefinementList-label ${linkClass}" title="${labelText}">
                                    <span class="ais-RefinementList-labelText swatch-label" style="${labelStyle}">
                                        <img
                                            src="${imageUrl}"
                                            alt="${labelText}"
                                            title="${labelText}"
                                            width="${dimensions.innerWidth}"
                                            height="${dimensions.innerHeight}"
                                        />
                                    </span>
                                    <span class="ais-RefinementList-count">${count}</span>
                                </label>
                            `;
                        } else {
                            const linkClass = isRefined ? 'swatch-link selected' : 'swatch-link';
                            return html`
                                <label class="ais-RefinementList-label ${linkClass}" title="${labelText}">
                                    <span class="ais-RefinementList-labelText swatch-label">
                                        ${labelText}
                                    </span>
                                    <span class="ais-RefinementList-count">${count}</span>
                                </label>
                            `;
                        }
                    };
                    // Merge swatch cssClasses with existing ones
                    widgetConfig.cssClasses = Utils.deepMerge(widgetConfig.cssClasses || {}, {
                        list: 'configurable-swatch-list',
                        item: '',
                        label: ''
                    });
                }

                widgets.push(refinementList(widgetConfig));
            }
        });

        return widgets;
    }

    _buildWidgets() {
        const widgets = [];
        const hitTemplateInstance = this.templates.create('hit', this._config);

        // SearchBox - disable autofocus if auto-opening from URL
        const searchBoxConfig = this.widgets.getConfig('searchBox');
        if (searchBoxConfig) {
            // Check if auto-open from URL - disable autofocus to prevent dropdown showing
            if (window._mmSearchAutoOpen === true) {
                searchBoxConfig.autofocus = false;
            }
            widgets.push(searchBox(searchBoxConfig));
        }

        // Stats
        const statsConfig = this.widgets.getConfig('stats');
        if (statsConfig) widgets.push(stats(statsConfig));

        const stats2Config = this.widgets.getConfig('stats2');
        if (stats2Config) widgets.push(stats(stats2Config));

        // SortBy
        const sortByConfig = this.widgets.getConfig('sortBy');
        if (sortByConfig) widgets.push(sortBy(sortByConfig));

        // Current Refinements
        const currentRefConfig = this.widgets.getConfig('currentRefinements');
        if (currentRefConfig) widgets.push(currentRefinements(currentRefConfig));

        // Category Names
        const categoryConfig = this.widgets.getConfig('categoryNames');
        if (categoryConfig) widgets.push(refinementList(categoryConfig));

        // Has Discount Toggle
        const discountConfig = this.widgets.getConfig('hasDiscount');
        if (discountConfig) widgets.push(toggleRefinement(discountConfig));

        // Facet widgets
        widgets.push(...this._createFacetWidgets());

        // Infinite Hits with template
        const hitsConfig = this.widgets.getConfig('infiniteHits');
        if (hitsConfig) {
            widgets.push(infiniteHits({
                ...hitsConfig,
                transformItems: (items) => {
                    this.currentHits = items;
                    return items;
                },
                templates: {
                    ...hitsConfig.templates,
                    item: (hit, opts) => hitTemplateInstance.render(hit, opts)
                }
            }));
        }

        return widgets;
    }

    /**
     * Build routing configuration for InstantSearch
     * Uses HashRouter for SEO-friendly URLs with hash
     * @private
     * @returns {Object} Routing configuration
     */
    _buildRoutingConfig() {
        const collectionName = this._config.collectionName;
        const facets = this._config.facetBy || [];
        
        // Create the HashRouter instance
        this._hashRouter = new HashRouter({
            hashPrefix: '#search',
            writeDelay: 400,
            cleanUrlOnDispose: false
        });
        
        return {
            router: this._hashRouter,
            stateMapping: {
                /**
                 * Convert InstantSearch UI state to URL route state
                 */
                stateToRoute: (uiState) => {
                    const indexState = uiState[collectionName] || {};
                    const route = {};
                    
                    // Query
                    if (indexState.query) {
                        route.q = indexState.query;
                    }
                    
                    // Sort - extract just the sort part (e.g., "price:asc" from "collection/sort/price:asc")
                    if (indexState.sortBy && indexState.sortBy !== collectionName) {
                        const sortMatch = indexState.sortBy.match(/\/sort\/(.+)$/);
                        if (sortMatch) {
                            route.sort = sortMatch[1]; // e.g., "price:asc"
                        }
                    }
                    
                    // Refinement lists (facets)
                    if (indexState.refinementList) {
                        Object.keys(indexState.refinementList).forEach(key => {
                            const values = indexState.refinementList[key];
                            if (values && values.length > 0) {
                                route[key] = values;
                            }
                        });
                    }
                    
                    // Range (price, etc.) - The range widget stores values as string "min:max"
                    if (indexState.range) {
                        Object.keys(indexState.range).forEach(key => {
                            const rangeValue = indexState.range[key];
                            if (rangeValue !== undefined && rangeValue !== null && rangeValue !== '') {
                                // Format is "min:max" string
                                if (typeof rangeValue === 'string') {
                                    const parts = rangeValue.split(':');
                                    if (parts[0] && parts[0] !== '') {
                                        route[`${key}_min`] = parts[0];
                                    }
                                    if (parts[1] && parts[1] !== '') {
                                        route[`${key}_max`] = parts[1];
                                    }
                                }
                            }
                        });
                    }
                    
                    // Toggle refinements
                    if (indexState.toggle) {
                        Object.keys(indexState.toggle).forEach(key => {
                            if (indexState.toggle[key]) {
                                route[key] = 'true';
                            }
                        });
                    }
                    
                    return route;
                },
                
                /**
                 * Convert URL route state to InstantSearch UI state
                 */
                routeToState: (routeState) => {
                    const refinementList = {};
                    const rangeTemp = {}; // Temporary storage for min/max values
                    const toggle = {};
                    
                    // Process route state to extract refinements
                    Object.keys(routeState).forEach(key => {
                        // Skip known keys
                        if (['q', 'sort'].includes(key)) return;
                        
                        // Range min/max - store temporarily
                        if (key.endsWith('_min')) {
                            const facetKey = key.replace('_min', '');
                            if (!rangeTemp[facetKey]) rangeTemp[facetKey] = { min: '', max: '' };
                            rangeTemp[facetKey].min = routeState[key];
                            return;
                        }
                        if (key.endsWith('_max')) {
                            const facetKey = key.replace('_max', '');
                            if (!rangeTemp[facetKey]) rangeTemp[facetKey] = { min: '', max: '' };
                            rangeTemp[facetKey].max = routeState[key];
                            return;
                        }
                        
                        // Toggle values
                        if (routeState[key] === 'true' || routeState[key] === true) {
                            toggle[key] = true;
                            return;
                        }
                        
                        // Refinement lists (arrays)
                        const value = routeState[key];
                        if (Array.isArray(value)) {
                            refinementList[key] = value;
                        } else if (value) {
                            refinementList[key] = [value];
                        }
                    });
                    
                    // Convert rangeTemp to range with "min:max" format
                    const range = {};
                    Object.keys(rangeTemp).forEach(key => {
                        const { min, max } = rangeTemp[key];
                        // Build "min:max" string format
                        range[key] = `${min || ''}:${max || ''}`;
                    });
                    
                    const state = {
                        [collectionName]: {}
                    };
                    
                    // Query
                    if (routeState.q) {
                        state[collectionName].query = routeState.q;
                    }
                    
                    // Sort - rebuild the full sortBy value (e.g., "collection/sort/price:asc")
                    if (routeState.sort) {
                        state[collectionName].sortBy = `${collectionName}/sort/${routeState.sort}`;
                    }
                    
                    // Refinements
                    if (Object.keys(refinementList).length > 0) {
                        state[collectionName].refinementList = refinementList;
                    }
                    
                    // Range - use "min:max" string format
                    if (Object.keys(range).length > 0) {
                        state[collectionName].range = range;
                    }
                    
                    // Toggle
                    if (Object.keys(toggle).length > 0) {
                        state[collectionName].toggle = toggle;
                    }
                    
                    return state;
                }
            }
        };
    }

    init() {
        this.events.emit('beforeInit', this);

        const adapter = this._config.instantsearchAdapter;
        
        this._search = instantsearch({
            indexName: this._config.collectionName,
            searchClient: adapter.searchClient,
            numberLocale: 'it',
            routing: this._buildRoutingConfig()
        });

        this._search.addWidgets(this._buildWidgets());

        this._search.on('render', () => {
            this.events.emit('render', this);
        });

        this._search.on('error', (error) => {
            this.events.emit('error', error);
            console.error('Search error:', error);
        });

        this.events.emit('afterInit', this);
    }

    start() {
        if (this._started) return;

        this.events.emit('beforeStart', this);
        this._search.start();
        this._started = true;
        this.events.emit('afterStart', this);
    }

    isStarted() {
        return this._started;
    }

    getSearch() {
        return this._search;
    }

    /**
     * Get the HashRouter instance
     * @returns {HashRouter|null}
     */
    getHashRouter() {
        return this._hashRouter;
    }
}

// Export HitHelpers for use in custom templates
export { HitHelpers };

export default InstantSearchApp;