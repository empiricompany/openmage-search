document.addEventListener('DOMContentLoaded', function() {    
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

    search.addWidgets([
        instantsearch.widgets.searchBox({
            container: '#typesense-searchbox',
            placeholder: 'Cerca prodotti...',
            autofocus: true,
            searchAsYouType: true,
            showReset: false,
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
                    `${nbHits} risultati trovati in ${processingTimeMS}ms`
            },        
            cssClasses: {
                text: 'text-muted',
            },
        }),
        instantsearch.widgets.hitsPerPage({
            container: '#typesense-per-page',
            items: [
            { label: '12 per page', value: 12, default: true },
            { label: '24 per page', value: 24 },
            ],
            cssClasses: {
                select: '',
            },
        }),
        instantsearch.widgets.sortBy({
            container: '#typesense-sort-by',
            items: [
                { label: 'Rilevanza', value: window.instantSearchConfig.collectionName },
                { label: 'Prezzo (Da minore a maggiore)', value: `${window.instantSearchConfig.collectionName}/sort/price:asc` },
                { label: 'Prezzo (Da maggiore a minore)', value: `${window.instantSearchConfig.collectionName}/sort/price:desc` }
            ],
            cssClasses: {
                root: 'sort-by',
            },
        }),

        instantsearch.widgets.refinementList({
            container: '#typesense-categories',
            attribute: 'category_names',
            operator: 'or',
            header: 'Categorie',
            limit: 5,
            showMore: true,
            showMoreLimit: 10,
            searchable: false,
            searchablePlaceholder: 'Cerca categorie...',
            templates: {
                header: 'Categorie'
            }
        }),

        ...window.instantSearchConfig.facetBy.map(facet => {
            return facet === 'price' 
                ? instantsearch.widgets.rangeSlider({
                    container: `#typesense-${facet}`,
                    attribute: facet,
                    pips: false,
                    tooltips: true,
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
                    limit: 5,
                    showMore: true,
                    showMoreLimit: 10,
                    searchable: false,
                    templates: {
                        header: facet.charAt(0).toUpperCase() + facet.slice(1).replace(/_/g, ' ')
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
                    let imageUrl = '/skin/frontend/base/default/images/catalog/product/placeholder/image.jpg';
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
                    
                    const price = hit.price ? `${hit.price} €` : 'Prezzo non disponibile';
                    
                    return html`
                        <a href="${productUrl}" class="product-image">
                            <img src="${imageUrl}" alt="${hit.name || 'Prodotto'}" />
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
                                <p class="product-price">
                                    <span class="price">${price}</span>
                                </p>
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
});