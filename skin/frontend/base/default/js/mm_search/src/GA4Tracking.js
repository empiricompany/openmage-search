/**
 * GA4Tracking - Google Analytics 4 tracking for search events
 * Tracks search queries and result clicks for conversion rate analysis
 */
export class GA4Tracking {
    constructor(config = {}) {
        this._config = config;
        this._eventBus = null;
        this._lastTrackedQuery = '';
        this._currentSearchQuery = '';
        this._cookieName = 'mm_search_products';
        this._cookieMaxDays = 30;
        this._retryAttempts = {};
        this._gtagReady = false;
    }

    /**
     * Check if gtag is available
     */
    _checkGA4Available() {
        return typeof window.gtag === 'function';
    }

    /**
     * Initialize GA4 tracking with EventBus
     * @param {EventBus} eventBus - The EventBus instance
     */
    init(eventBus) {
        this._eventBus = eventBus;

        this._eventBus.on('search:submitted', (data) => {
            this.trackSearch(data.query, data.resultsCount);
        });

        this._eventBus.on('search:result_click', (data) => {
            this.trackSearchResultClick(data.hit, data.position, data.onComplete);
        });

        console.log('[GA4Tracking] Initialized');
    }

    /**
     * Centralized method to track GA4 event with retry logic
     * @param {string} eventName - GA4 event name
     * @param {Object} eventData - Event parameters
     * @param {string} retryKey - Unique key for retry tracking
     * @param {function} onSuccess - Callback on successful tracking
     * @returns {boolean} - True if tracked, false if retrying/failed
     */
    _trackWithRetry(eventName, eventData, retryKey, onSuccess = null) {
        if (!this._gtagReady && !this._checkGA4Available()) {
            const attempts = this._retryAttempts[retryKey] || 0;
            const maxAttempts = 3;
            
            if (attempts < maxAttempts) {
                this._retryAttempts[retryKey] = attempts + 1;
                
                if (attempts === 0) {
                    try {
                        window.dispatchEvent(new MouseEvent('mousemove'));
                        window.dispatchEvent(new TouchEvent('touchstart'));
                        window.dispatchEvent(new Event('scroll'));
                        console.log('[GA4Tracking] Triggered user interaction events to load gtag');
                    } catch (e) {
                        console.warn('[GA4Tracking] Error triggering events:', e);
                    }
                }
                
                setTimeout(() => this._trackWithRetry(eventName, eventData, retryKey, onSuccess), 500);
                console.log(`[GA4Tracking] gtag not ready, retry ${attempts + 1}/${maxAttempts} for ${eventName}`);
            } else {
                console.warn(`[GA4Tracking] gtag unavailable after ${maxAttempts} retries (1.5s), skipping ${eventName}`);
            }
            return false;
        }

        try {
            this._gtagReady = true;
            gtag('event', eventName, eventData);
            delete this._retryAttempts[retryKey];
            if (onSuccess) onSuccess();
            return true;
        } catch (error) {
            console.error(`[GA4Tracking] Error tracking ${eventName}:`, error);
            return false;
        }
    }

    /**
     * Track search event
     * @param {string} query - The search query
     * @param {number} resultsCount - Number of results returned
     */
    trackSearch(query, resultsCount = 0) {
        if (!query || query.trim().length < 2) return;

        if (query === this._lastTrackedQuery) {
            return;
        }

        const tracked = this._trackWithRetry(
            'search',
            {
                search_term: query,
                items: []
            },
            `search_${query}`,
            () => {
                this._lastTrackedQuery = query;
                this._currentSearchQuery = query;
                console.log('[GA4Tracking] Tracked search:', query, 'Results:', resultsCount);
            }
        );

        if (tracked) {
            this._lastTrackedQuery = query;
            this._currentSearchQuery = query;
        }
    }

    /**
     * Get search products from cookie
     * @returns {Object} - Map of SKU to query
     */
    _getSearchProductsCookie() {
        try {
            const cookies = document.cookie.split(';');
            for (let cookie of cookies) {
                const [name, value] = cookie.trim().split('=');
                if (name === this._cookieName) {
                    return JSON.parse(decodeURIComponent(value));
                }
            }
            return {};
        } catch (error) {
            console.error('[GA4Tracking] Error reading cookie:', error);
            return {};
        }
    }

    /**
     * Set search products cookie
     * @param {Object} products - Map of SKU to query
     */
    _setSearchProductsCookie(products) {
        try {
            const maxProducts = 30;
            
            const productArray = Object.entries(products);
            if (productArray.length > maxProducts) {
                products = Object.fromEntries(productArray.slice(-maxProducts));
            }
            
            const value = encodeURIComponent(JSON.stringify(products));
            const expires = new Date();
            expires.setTime(expires.getTime() + (this._cookieMaxDays * 24 * 60 * 60 * 1000));
            
            document.cookie = this._cookieName + '=' + value +
                             '; expires=' + expires.toUTCString() +
                             '; path=/' +
                             '; SameSite=Lax';
            
            console.log('[GA4Tracking] Updated cookie with', Object.keys(products).length, 'products');
        } catch (error) {
            console.error('[GA4Tracking] Error setting cookie:', error);
        }
    }

    /**
     * Add product to search tracking cookie
     * @param {string} sku - Product SKU
     * @param {string} query - Search query
     */
    _markProductInCookie(sku, query) {
        if (!sku || !query) return;
        
        const products = this._getSearchProductsCookie();
        products[sku] = query;
        this._setSearchProductsCookie(products);
    }

    /**
     * Check if tracking is enabled
     */
    isEnabled() {
        return this._checkGA4Available();
    }

    /**
     * Track select_item event when user clicks on a search result
     * @param {object} hit - The product hit object
     * @param {number} position - Position in search results (1-based)
     * @param {function} onComplete - Callback to execute after tracking (for navigation)
     */
    trackSearchResultClick(hit, position, onComplete = null) {
        if (!this.isEnabled()) {
            if (onComplete) onComplete();
            return;
        }

        try {
            const price = hit.price || hit.special_price || 0;
            const category = hit.category_names?.[0] || '';
            const sku = hit.sku || hit.id || '';
            const searchQuery = this._currentSearchQuery || '';

            if (searchQuery && sku) {
                this._markProductInCookie(sku, searchQuery);
            }

            const eventData = {
                currency: 'EUR',
                value: price,
                item_list_name: 'search_results',
                item_list_id: searchQuery || 'search_results',
                items: [{
                    item_id: sku,
                    item_name: hit.name || '',
                    item_category: category,
                    item_list_name: 'search_results',
                    item_list_id: searchQuery || 'search_results',
                    price: price,
                    index: position
                }]
            };

            if (onComplete) {
                eventData.event_callback = onComplete;
                eventData.event_timeout = 1000;
            }

            gtag('event', 'select_item', eventData);

            console.log('[GA4Tracking] Tracked select_item:', hit.name, 'Query:', searchQuery, 'Position:', position);
        } catch (error) {
            console.error('[GA4Tracking] Error tracking select_item:', error);
            if (onComplete) onComplete();
        }
    }
}
