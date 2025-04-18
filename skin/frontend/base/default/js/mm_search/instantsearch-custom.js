/**
 * Implementazione di InstantSearch.js per Typesense
 */
document.addEventListener('DOMContentLoaded', function() {    
    // Inizializza l'adapter Typesense per InstantSearch
    const typesenseInstantsearchAdapter = new TypesenseInstantSearchAdapter({
        server: {
            apiKey: window.typesenseConfig.apiKey,
            nodes: [{
                host: window.typesenseConfig.host,
                path: window.typesenseConfig.path,
                protocol: window.typesenseConfig.protocol
            }],
            cacheSearchResultsForSeconds: window.typesenseConfig.cacheSearchResultsForSeconds,
        },
        additionalSearchParameters: {
            query_by: 'name,short_description,sku',
            highlight_full_fields: 'name,short_description,sku',
            per_page: 15,
            facet_by: ['category_names', ...window.typesenseConfig.facetBy].join(','),
        }
    });

    const searchClient = typesenseInstantsearchAdapter.searchClient;

    const search = instantsearch({
        indexName: window.typesenseConfig.collectionName,
        searchClient,
        initialUiState: {
            [window.typesenseConfig.collectionName]: {
                query: document.getElementById('search').value
            }
        }
    });

    // Configura i widget di InstantSearch
    search.addWidgets([
        instantsearch.widgets.searchBox({
            container: '#typesense-searchbox',
            placeholder: 'Cerca prodotti...',
            autofocus: true,
            searchAsYouType: true,
            showReset: false,
            showSubmit: false,
            showLoadingIndicator: true
        }),

        // Aggiungi il widget per mostrare il numero di risultati
        instantsearch.widgets.stats({
            container: '#typesense-stats',
            templates: {
                text: ({ nbHits, processingTimeMS }) => 
                    `${nbHits} risultati trovati in ${processingTimeMS}ms`
            }
        }),

        // Aggiungi il widget per l'ordinamento
        instantsearch.widgets.sortBy({
            container: '#typesense-sort-by',
            items: [
                { label: 'Rilevanza', value: window.typesenseConfig.collectionName },
                { label: 'Prezzo (Da minore a maggiore)', value: `${window.typesenseConfig.collectionName}/sort/price:asc` },
                { label: 'Prezzo (Da maggiore a minore)', value: `${window.typesenseConfig.collectionName}/sort/price:desc` }
            ]
        }),

        // Aggiungi il widget per i facet di categoria
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

        // Aggiungi widget dinamici per i facet configurati
        ...window.typesenseConfig.facetBy.map(facet => {
            return facet === 'price' 
                ? instantsearch.widgets.rangeSlider({
                    container: `#typesense-${facet}`,
                    attribute: facet,
                    templates: {
                        header: 'Prezzo'
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

        // Sostituisci hits con infiniteHits per implementare l'infinite scroll
        instantsearch.widgets.infiniteHits({
            container: '#typesense-hits',
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
                    
                    // Costruisci l'URL del prodotto con il prefisso dello store code
                    let productUrl = '#';
                    if (hit.request_path) {
                        // Se c'è uno store code, lo aggiungiamo come prefisso
                        if (window.typesenseConfig.storeCode) {
                            productUrl = `/${window.typesenseConfig.storeCode}/${hit.request_path}`;
                        } else {
                            productUrl = `/${hit.request_path}`;
                        }
                    }
                    
                    // Formatta il prezzo
                    const price = hit.price ? `${hit.price} €` : 'Prezzo non disponibile';
                    
                    return html`
                        <article>
                            <a href="${productUrl}">
                                <img src="${imageUrl}" alt="${hit.name || 'Prodotto'}" />
                            </a>
                            <div class="product-content">
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
                        </article>
                    `;
                },
                showMoreText: 'Carica altri prodotti'
            },
            showMoreButton: true
        })
    ]);

    // Inizializza la ricerca quando l'overlay viene aperto
    const overlay = document.getElementById('typesense-overlay');
    const mainInput = document.getElementById('search');
    let searchStarted = false;

    // Apri l'overlay al click sull'input
    mainInput.addEventListener('click', function() {
        overlay.classList.add('active');
        document.body.style.overflow = 'hidden'; // Blocca lo scroll della pagina
        
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

    // Chiudi l'overlay al click sul bottone di chiusura
    document.querySelector('.typesense-close-btn').addEventListener('click', function() {
        overlay.classList.remove('active');
        document.body.style.overflow = ''; // Ripristina lo scroll della pagina
    });

    // Chiudi l'overlay premendo ESC
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && overlay.classList.contains('active')) {
            overlay.classList.remove('active');
            document.body.style.overflow = '';
        }
    });

    // Sincronizza il valore dell'input principale con InstantSearch
    mainInput.addEventListener('input', function(e) {
        if (searchStarted) {
            try {
                search.helper.setQuery(e.target.value).search();
            } catch (error) {
                console.error('Error updating search query:', error);
            }
        }
    });

    // Debug degli eventi di ricerca
    search.on('render', function() {
        //console.log('Search results rendered');
    });

    search.on('error', function(error) {
        console.error('Search error:', error);
    });
});