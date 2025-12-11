/**
 * HashRouter - Custom router for InstantSearch that uses URL hash
 * 
 * Generates URLs in the format: #search?q=query&page=2&brand=Nike&brand=Adidas
 * This allows SEO-friendly URLs without page reload
 */

import qs from 'qs';

export class HashRouter {
    constructor(options = {}) {
        this._options = {
            hashPrefix: '#search',
            writeDelay: 400,
            createURL: null,
            parseURL: null,
            ...options
        };
        
        this._writeTimer = null;
        this._listeners = [];
        this._isDisposed = false;
        this._lastState = null;
        
        // Bind methods
        this._onHashChange = this._onHashChange.bind(this);
    }

    /**
     * Get the current route state from the URL hash
     * @returns {Object} Route state
     */
    read() {
        if (this._options.parseURL) {
            return this._options.parseURL({
                qsModule: qs,
                location: window.location
            });
        }
        
        return this._defaultParseURL();
    }

    /**
     * Write the route state to the URL hash
     * @param {Object} routeState - The route state to write
     */
    write(routeState) {
        // Clear any pending write
        if (this._writeTimer) {
            clearTimeout(this._writeTimer);
        }
        
        // Debounce the write
        this._writeTimer = setTimeout(() => {
            if (this._isDisposed) return;
            
            const url = this.createURL(routeState);
            this._lastState = routeState;
            
            // Use replaceState to avoid polluting browser history
            window.history.replaceState(
                { routeState },
                '',
                url
            );
        }, this._options.writeDelay);
    }

    /**
     * Create a URL from the route state
     * @param {Object} routeState - The route state
     * @returns {string} The URL with hash
     */
    createURL(routeState) {
        if (this._options.createURL) {
            return this._options.createURL({
                qsModule: qs,
                routeState,
                location: window.location
            });
        }
        
        return this._defaultCreateURL(routeState);
    }

    /**
     * Subscribe to route changes (hashchange events)
     * @param {Function} callback - Called when route changes
     * @returns {Function} Unsubscribe function
     */
    onUpdate(callback) {
        this._listeners.push(callback);
        
        // Add hashchange listener if this is the first subscriber
        if (this._listeners.length === 1) {
            window.addEventListener('hashchange', this._onHashChange);
        }
        
        return () => {
            const index = this._listeners.indexOf(callback);
            if (index > -1) {
                this._listeners.splice(index, 1);
            }
            
            if (this._listeners.length === 0) {
                window.removeEventListener('hashchange', this._onHashChange);
            }
        };
    }

    /**
     * Dispose the router
     */
    dispose() {
        this._isDisposed = true;
        
        if (this._writeTimer) {
            clearTimeout(this._writeTimer);
        }
        
        window.removeEventListener('hashchange', this._onHashChange);
        this._listeners = [];
        
        // Clean URL if configured
        if (this._options.cleanUrlOnDispose) {
            this.clearUrl();
        }
    }

    /**
     * Clear the search URL hash
     */
    clearUrl() {
        const url = window.location.href.split('#')[0];
        window.history.replaceState(null, '', url);
    }

    /**
     * Check if the current URL has search parameters
     * @returns {boolean}
     */
    hasSearchParams() {
        const hash = window.location.hash;
        return hash.startsWith(this._options.hashPrefix) && hash.includes('?');
    }

    /**
     * Handle hashchange event
     * @private
     */
    _onHashChange() {
        const routeState = this.read();
        
        this._listeners.forEach(callback => {
            callback(routeState);
        });
    }

    /**
     * Default URL parser for hash
     * @private
     * @returns {Object} Parsed route state
     */
    _defaultParseURL() {
        const hash = window.location.hash;
        
        // Check if hash starts with our prefix
        if (!hash.startsWith(this._options.hashPrefix)) {
            return {};
        }
        
        // Extract query string after prefix
        const queryString = hash.slice(this._options.hashPrefix.length);
        
        // If no query string, return empty
        if (!queryString || queryString === '?') {
            return {};
        }
        
        // Parse query string (remove leading ?)
        const parsed = qs.parse(queryString.slice(1), {
            arrayFormat: 'repeat',
            decoder: (str, defaultDecoder, charset, type) => {
                if (type === 'value') {
                    try {
                        return decodeURIComponent(str);
                    } catch {
                        return str;
                    }
                }
                return defaultDecoder(str);
            }
        });
        
        // Normalize array values
        Object.keys(parsed).forEach(key => {
            // Ensure arrays are always arrays (qs sometimes returns single value as string)
            if (key !== 'q' && key !== 'page' && key !== 'sort' && 
                !key.endsWith('_min') && !key.endsWith('_max')) {
                if (!Array.isArray(parsed[key]) && parsed[key] !== undefined) {
                    parsed[key] = [parsed[key]];
                }
            }
            
            // Convert page to number
            if (key === 'page' && parsed[key]) {
                parsed[key] = parseInt(parsed[key], 10);
            }
            
            // Convert min/max to numbers
            if ((key.endsWith('_min') || key.endsWith('_max')) && parsed[key]) {
                parsed[key] = parseFloat(parsed[key]);
            }
        });
        
        return parsed;
    }

    /**
     * Default URL creator for hash
     * @private
     * @param {Object} routeState - Route state to serialize
     * @returns {string} URL with hash
     */
    _defaultCreateURL(routeState) {
        // Filter out undefined/null/empty values
        const cleanState = {};
        
        Object.keys(routeState).forEach(key => {
            const value = routeState[key];
            
            if (value === undefined || value === null) return;
            if (value === '') return;
            if (Array.isArray(value) && value.length === 0) return;
            
            // Don't include page=1 (it's the default)
            if (key === 'page' && value === 1) return;
            
            cleanState[key] = value;
        });
        
        // If no state, return base URL without hash
        if (Object.keys(cleanState).length === 0) {
            return window.location.pathname + window.location.search;
        }
        
        // Build query string
        const queryString = qs.stringify(cleanState, {
            arrayFormat: 'repeat',
            encodeValuesOnly: true,
            encoder: (str) => {
                return encodeURIComponent(str);
            }
        });
        
        return `${window.location.pathname}${window.location.search}${this._options.hashPrefix}?${queryString}`;
    }
}

export default HashRouter;
