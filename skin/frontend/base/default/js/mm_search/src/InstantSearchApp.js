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

/**
 * Main InstantSearch Application
 */
export class InstantSearchApp {
    constructor(config) {
        this._config = config;
        this._search = null;
        this._started = false;
        
        this.events = new EventBus();
        this.templates = new TemplateRegistry();
        this.widgets = new WidgetRegistry();
        
        // Register default hit template
        this.templates.register('hit', HitTemplate);
        
        // Register default widget configs
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
            templates: {
                showMoreText(data, { html }) {
                    return html`<span class="btn btn-xs">${data.isShowingMore ? 'Mostra meno' : 'Mostra tutti'}</span>`;
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

        facets.forEach(facet => {
            if (facet === 'price') {
                widgets.push(rangeSlider({
                    container: `#typesense-${facet}`,
                    attribute: facet,
                    pips: false,
                    tooltips: {
                        format: (value) => '€' + Math.round(value).toLocaleString()
                    },
                    cssClasses: {
                        root: 'price-range-slider',
                    }
                }));
            } else {
                const swatchConfig = swatches[facet];
                
                const widgetConfig = {
                    container: `#typesense-${facet}`,
                    attribute: facet,
                    operator: 'or',
                    limit: 10,
                    showMore: true,
                    showMoreLimit: 100,
                    searchable: true,
                    searchablePlaceholder: 'Cerca...',
                    templates: {
                        showMoreText(data, { html }) {
                            return html`<span class="btn btn-xs">${data.isShowingMore ? 'Mostra meno' : 'Mostra tutti'}</span>`;
                        },
                    }
                };

                // Apply swatch configuration if available
                if (swatchConfig && swatchConfig.options) {
                    widgetConfig.searchable = false;
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
                    widgetConfig.cssClasses = {
                        list: 'configurable-swatch-list',
                        item: '',
                        label: ''
                    };
                }

                widgets.push(refinementList(widgetConfig));
            }
        });

        return widgets;
    }

    _buildWidgets() {
        const widgets = [];
        const hitTemplateInstance = this.templates.create('hit');

        // SearchBox
        const searchBoxConfig = this.widgets.getConfig('searchBox');
        if (searchBoxConfig) widgets.push(searchBox(searchBoxConfig));

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
                templates: {
                    ...hitsConfig.templates,
                    item: (hit, opts) => hitTemplateInstance.render(hit, opts)
                }
            }));
        }

        return widgets;
    }

    init() {
        this.events.emit('beforeInit', this);

        const adapter = this._config.instantsearchAdapter;
        
        this._search = instantsearch({
            indexName: this._config.collectionName,
            searchClient: adapter.searchClient,
            numberLocale: 'it',
            routing: false,
            initialUiState: {
                [this._config.collectionName]: {
                    query: document.getElementById('search')?.value || ''
                }
            }
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

    setQuery(query) {
        if (this._search && this._search.helper) {
            this._search.helper.setQuery(query).search();
        }
    }

    getSearch() {
        return this._search;
    }
}

// Export HitHelpers for use in custom templates
export { HitHelpers };

export default InstantSearchApp;