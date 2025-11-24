const searchClient = window.instantSearchConfig.instantsearchAdapter.searchClient;
const search = instantsearch({
    indexName: window.instantSearchConfig.collectionName,
    searchClient,
    numberLocale: 'it',
    routing: true,
    initialUiState: {
        [window.instantSearchConfig.collectionName]: {
            query: document.getElementById('search').value
        }
    }
});

let InstantSearchtimerId;
let InstantSearchtimeout = 200; // Debounce timeout in milliseconds

search.addWidgets([
    
    instantsearch.widgets.searchBox({
        container: '#typesense-searchbox',
        placeholder: 'Cerca prodotti...',
        autofocus: true,
        searchAsYouType: true,
        queryHook(query, refine) {
            clearTimeout(InstantSearchtimerId);
            InstantSearchtimerId = setTimeout(() => refine(query), InstantSearchtimeout);
        },
        showReset: true,
        showSubmit: false,
        showLoadingIndicator: true,
        cssClasses: {
            root: 'search_mini_form',
            input: 'input-text',
        },
    }),

    instantsearch.widgets.stats({
        container: '#typesense-stats',
        templates: {
            text: ({ nbHits, processingTimeMS }) => 
                `<strong>${nbHits}</strong> risultati trovati in ${processingTimeMS}ms`
        },        
        cssClasses: {
            text: 'text-muted',
        },
    }),
    instantsearch.widgets.stats({
        container: '#typesense-stats2',
        templates: {
            text: ({ nbHits, processingTimeMS }) => 
                `<strong>${nbHits}</strong> risultati trovati in ${processingTimeMS}ms`
        },        
        cssClasses: {
            text: 'text-muted',
        },
    }),
    /* instantsearch.widgets.hitsPerPage({
        container: '#typesense-per-page',
        items: [
            { label: '24 per page', value: 24, default: true },
            { label: '36 per page', value: 36 },
            { label: '48 per page', value: 48 }
        ],
        cssClasses: {
            select: '',
        },
    }), */
    instantsearch.widgets.sortBy({
        container: '#typesense-sort-by',
        items: [
            { label: 'Rilevanza', value: `${window.instantSearchConfig.collectionName}/sort/_text_match:desc` },
            { label: 'Prezzo (Da minore a maggiore)', value: `${window.instantSearchConfig.collectionName}/sort/price:asc` },
            { label: 'Prezzo (Da maggiore a minore)', value: `${window.instantSearchConfig.collectionName}/sort/price:desc` },
            { label: 'Nome (A-Z)', value: `${window.instantSearchConfig.collectionName}/sort/name:asc` },
            { label: 'Nome (Z-A)', value: `${window.instantSearchConfig.collectionName}/sort/name:desc` }
        ],
        cssClasses: {
            root: 'sort-by',
        },
    }),

    instantsearch.widgets.refinementList({
        container: '#typesense-category_names',
        attribute: 'category_names',
        operator: 'or',
        header: 'Categorie',
        limit: 10,
        showMore: true,
        showMoreLimit: 100,
        searchable: true,
        searchablePlaceholder: 'Cerca categorie...',
        templates: {
            header: 'Categorie',
            showMoreText(data, { html }) {
                return html`<span class="btn btn-xs">${data.isShowingMore ? 'Mostra meno' : 'Mostra tutti'}</span>`;
            },
        }
    }),

    instantsearch.widgets.currentRefinements({
        container: "#current-refinements",

        transformItems(items) {
            return items.map(item => {
                const labelElement = document.getElementById('typesense-' + item.attribute);
                const readableLabel = labelElement
                    ? labelElement.previousElementSibling.textContent.trim()
                    : item.attribute.charAt(0).toUpperCase() + item.attribute.slice(1).replace(/_/g, ' ');

                const transformedRefinements = item.refinements.map(ref => {
                    // Se il valore è booleano, mostra solo l’etichetta del filtro
                    if (ref.value === "true" || ref.value === "false") {
                    return {
                        ...ref,
                        label: readableLabel
                    };
                    }

                    // Altrimenti lascia invariato
                    return ref;
                });

                return {
                    ...item,
                    label: readableLabel,
                    refinements: transformedRefinements,
                };
            });
        },
    }),

    instantsearch.widgets.toggleRefinement({
        container: '#typesense-has_discount',
        attribute: 'has_discount',
        on: true,
        operator: 'and',
        label: 'Solo prodotti in offerta',
        templates: {
            labelText({ count }, { html }) {
                return html` Solo prodotti in offerta`;
            },
        },
    }),

    ...window.instantSearchConfig.facetBy.map(facet => {
        return facet === 'price' 
            ? instantsearch.widgets.rangeSlider({
                container: `#typesense-${facet}`,
                attribute: facet,
                operator: 'and',
                pips: false,
                tooltips: {
                    format: function(rawValue) {
                        return '€' + Math.round(rawValue).toLocaleString();
                    }
                },
                templates: {
                    header: 'Prezzo'
                },
                cssClasses: {
                    root: 'price-range-slider',
                }
            })
            : instantsearch.widgets.refinementList({
                container: `#typesense-${facet}`,
                attribute: facet,
                operator: 'or',
                limit: 10,
                showMore: true,
                showMoreLimit: 100,
                searchable: true,
                searchablePlaceholder: 'Cerca...',
                templates: {
                    header: facet.charAt(0).toUpperCase() + facet.slice(1).replace(/_/g, ' '),
                    showMoreText(data, { html }) {
                        return html`<span class="btn btn-xs">${data.isShowingMore ? 'Mostra meno' : 'Mostra tutti'}</span>`;
                    },
                }
            })
        }
    ),

    instantsearch.widgets.infiniteHits({
        container: '#typesense-hits',
        cssClasses: {
            list: [
            'products-grid products-grid--max-6-col',
            ],
            item: 'item',
            loadMore: 'button',
            disabledLoadMore: 'button'
        },
        templates: {
            empty: 'Nessun risultato trovato',
            item: (hit, { html, components }) => {
                // Usa l'immagine ridimensionata se disponibile
                const placeholderUrl = `${window.location.origin}/skin/frontend/base/default/images/catalog/product/placeholder/image.jpg`;
                let imageUrl = placeholderUrl;
                if (hit.thumbnail_medium) {
                    imageUrl = hit.thumbnail_medium;
                } else if (hit.thumbnail_small) {
                    imageUrl = hit.thumbnail_small;
                } else if (hit.thumbnail) {
                    imageUrl = `/media/catalog/product${hit.thumbnail}`;
                }
                if (hit.thumbnail_medium) {
                    imageUrl = hit.thumbnail_medium;
                } else if (hit.thumbnail_small) {
                    imageUrl = hit.thumbnail_small;
                } else if (hit.thumbnail) {
                    imageUrl = `/media/catalog/product${hit.thumbnail}`;
                }
                
                let productUrl = '#';
                if (hit.request_path) {
                    if (window.instantSearchConfig.storeCode) {
                        productUrl = `/${window.instantSearchConfig.storeCode}/${hit.request_path}`;
                    } else {
                        productUrl = `/${hit.request_path}`;
                    }
                }
                
                let newBadge = null;
                const now = new Date();
                
                const fromDate = hit.news_from_date ? new Date(hit.news_from_date) : null;
                const toDate = hit.news_to_date ? new Date(hit.news_to_date) : null;
                
                const isNew = (
                    (fromDate && now >= fromDate) && 
                    (!toDate || now <= toDate)
                );
                
                if (isNew) {
                    newBadge = html`<span class="badges__new">NOVITÀ</span>`;
                }
                let priceHtml, discountBadge;
                if (hit.price) {
                    if (hit.special_price && hit.special_price < hit.price) {
                        priceHtml = html`
                            <span class="old-price">
                                <span class="price">${hit.price} €</span>
                            </span>
                            <span class="special-price">
                                <span class="price">${hit.special_price} €</span>
                            </span>`;
                        let discount = ((hit.price - hit.special_price) / hit.price) * 100;
                        discountBadge = html`<span class="badges__discount">-${Math.round(discount)}%</span>`;
                    } else {
                        priceHtml = html`<span class="regular-price">
                            <span class="price">${hit.price} €</span>
                        </span>`;
                    }
                } else {
                    priceHtml = html`Prezzo non disponibile`;
                }
                const handleImageError = (e) => {
                    if (e.target.src === placeholderUrl) {
                        return;
                    }
                    e.target.onerror = null;
                    e.target.src = placeholderUrl;
                };
                return html`
                    <div class="badges">
                        ${newBadge || ''}
                        ${discountBadge || ''}
                    </div>
                    <a href="${productUrl}" class="product-image">
                        <img src="${imageUrl}" alt="${hit.name || 'Prodotto'}"
                        onerror=${handleImageError} />
                    </a>
                    <div class="">
                        <h2 class="product-name">
                            <a href="${productUrl}">
                                ${components.Highlight({ hit, attribute: 'name' })}
                            </a>
                        </h2>
                        <p class="product-sku">
                            SKU: ${components.Highlight({ hit, attribute: 'sku' })}
                        </p>
                        <div class="price-box">
                            ${priceHtml}
                        </div>
                    </div>
                `;
            },
            showMoreText: 'Carica altri prodotti'
        },
        showMoreButton: true
    })
]);

const overlay = document.getElementById('typesense-overlay');
const mainInput = document.getElementById('search');
let searchStarted = false;

mainInput.addEventListener('click', function() {
    overlay.classList.add('active');
    // remove x-clock
    overlay.removeAttribute('x-cloak');
    document.body.style.overflow = 'hidden';
    
    if (!searchStarted) {
        try {
            //console.log('Starting InstantSearch...');
            search.start();
            searchStarted = true;
            //console.log('InstantSearch started successfully');
        } catch (error) {
            console.error('Error starting InstantSearch:', error);
        }
    }
});

document.querySelector('.typesense-close-btn').addEventListener('click', function() {
    overlay.classList.remove('active');
    document.body.style.overflow = '';
});

document.querySelectorAll('.skip-sidebar, .block-layered-nav .typesense-close-btn').forEach(function(link) {
    link.addEventListener('click', function(e) {
        e.preventDefault();
        const targetId = this.getAttribute('data-target-element');
        const targetElement = document.querySelector(targetId);
        if (targetElement) {
            targetElement.classList.toggle('skip-content');
            /* if(!targetElement.classList.contains('skip-content')) {
            } */
            
        }
    });
});


document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && overlay.classList.contains('active')) {
        overlay.classList.remove('active');
        document.body.style.overflow = '';
    }
});

mainInput.addEventListener('input', function(e) {
    if (searchStarted) {
        try {
            search.helper.setQuery(e.target.value).search();
        } catch (error) {
            console.error('Error updating search query:', error);
        }
    }
});

search.on('render', function() {
    //console.log('Search results rendered');
});

search.on('error', function(error) {
    console.error('Search error:', error);
});