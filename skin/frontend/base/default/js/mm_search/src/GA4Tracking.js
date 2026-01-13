/**
 * GA4Tracking - Google Analytics 4 tracking for search events
 * Tracks search queries and result clicks for conversion rate analysis
 */
export class GA4Tracking {
    constructor(config = {}) {
        this._config = config;
        this._eventBus = null;
        this._enabled = this._checkGA4Available();
        this._lastTrackedQuery = '';
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
        if (!this._enabled) {
            console.warn('[GA4Tracking] gtag not available, tracking disabled');
            return;
        }

        this._eventBus = eventBus;

        // Listen for search submission events
        this._eventBus.on('search:submitted', (data) => {
            this.trackSearch(data.query, data.resultsCount);
        });

        // Listen for result click events
        this._eventBus.on('search:result_click', (data) => {
            this.trackSearchResultClick(data.hit, data.position, data.onComplete);
        });

        console.log('[GA4Tracking] Initialized');
    }

    /**
     * Track search event
     * @param {string} query - The search query
     * @param {number} resultsCount - Number of results returned
     */
    trackSearch(query, resultsCount = 0) {
        if (!this._enabled) return;
        if (!query || query.trim().length < 2) return;

        // Avoid duplicate tracking for the same query
        if (query === this._lastTrackedQuery) {
            return;
        }

        try {
            this._lastTrackedQuery = query;

            gtag('event', 'search', {
                search_term: query,
                items: [] // Empty array for compatibility
            });

            console.log('[GA4Tracking] Tracked search:', query, 'Results:', resultsCount);
        } catch (error) {
            console.error('[GA4Tracking] Error tracking search:', error);
        }
    }

    /**
     * Check if tracking is enabled
     */
    isEnabled() {
        return this._enabled;
    }

    /**
     * Track select_item event when user clicks on a search result
     * @param {object} hit - The product hit object
     * @param {number} position - Position in search results (1-based)
     * @param {function} onComplete - Callback to execute after tracking (for navigation)
     */
    trackSearchResultClick(hit, position, onComplete = null) {
        if (!this._enabled) {
            if (onComplete) onComplete();
            return;
        }

        try {
            const price = hit.price || hit.special_price || 0;
            const category = hit.category_names?.[0] || '';

            const eventData = {
                currency: 'EUR',
                value: price,
                items: [{
                    item_id: hit.sku || hit.id || '',
                    item_name: hit.name || '',
                    item_category: category,
                    price: price,
                    index: position
                }]
            };

            // Add callback if provided
            if (onComplete) {
                eventData.event_callback = onComplete;
                eventData.event_timeout = 1000; // 1s timeout safety
            }

            gtag('event', 'select_item', eventData);

            console.log('[GA4Tracking] Tracked select_item:', hit.name, 'Position:', position);
        } catch (error) {
            console.error('[GA4Tracking] Error tracking select_item:', error);
            if (onComplete) onComplete();
        }
    }
}
