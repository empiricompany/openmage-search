/**
 * Manages search overlay UI interactions
 * Handles URL routing integration for SEO-friendly search URLs
 */
export class OverlayManager {
    constructor(app, options = {}) {
        this._app = app;
        this._overlay = null;
        this._mainInput = null;
        this._loadMoreObserver = null;
        this._hashPrefix = options.hashPrefix || '#search';
        
        this._selectors = {
            overlay: options.overlaySelector || '#typesense-overlay',
            mainInput: options.inputSelector || '#search',
            closeBtn: options.closeBtnSelector || '.typesense-close-btn',
            searchBoxInput: options.searchBoxInputSelector || '#typesense-searchbox input.ais-SearchBox-input',
            loadMoreBtn: '#typesense-hits .ais-InfiniteHits-loadMore:not(.ais-InfiniteHits-loadMore--disabled)'
        };
        
        // Bind methods
        this._onHashChange = this._onHashChange.bind(this);
    }

    init() {
        this._overlay = document.querySelector(this._selectors.overlay);
        this._mainInput = document.querySelector(this._selectors.mainInput);
        
        if (!this._overlay || !this._mainInput) {
            console.warn('OverlayManager: Required elements not found');
            return;
        }

        this._bindEvents();
        this._setupInfiniteScrollOnRender();
        
        // Check if URL has search parameters - if so, open overlay automatically (without focus)
        if (this._hasSearchParams()) {
            console.log('[OverlayManager] URL has search params, opening overlay automatically');
            this._openOverlay({ skipFocus: true });
        }
    }

    /**
     * Check if current URL hash contains search parameters
     * @returns {boolean}
     */
    _hasSearchParams() {
        const hash = window.location.hash;
        return hash.startsWith(this._hashPrefix) && hash.includes('?');
    }

    _bindEvents() {
        // Open overlay on input click
        this._mainInput.addEventListener('click', () => this._openOverlay());

        // Close button
        const closeBtn = document.querySelector(this._selectors.closeBtn);
        if (closeBtn) {
            closeBtn.addEventListener('click', () => this._closeOverlay());
        }

        // Escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this._isActive()) {
                this._closeOverlay();
            }
        });

        // The main input is just a trigger - user types in the searchBox inside overlay
        // No need to sync input with search

        // Prevent form submit on Enter - intercept all forms in overlay
        this._overlay.addEventListener('submit', (e) => {
            e.preventDefault();
            return false;
        }, true);
        
        // Listen for hash changes to handle browser back/forward
        window.addEventListener('hashchange', this._onHashChange);
    }

    /**
     * Handle browser hash change events (back/forward navigation)
     * @private
     */
    _onHashChange() {
        if (this._hasSearchParams()) {
            // URL has search params, open overlay if not already open (without focus)
            if (!this._isActive()) {
                this._openOverlay({ skipFocus: true });
            }
        } else {
            // URL has no search params, close overlay if open
            if (this._isActive()) {
                this._closeOverlayWithoutClearingUrl();
            }
        }
    }

    /**
     * Open the overlay
     * @param {Object} options - Options
     * @param {boolean} options.skipFocus - Skip focusing the search input (used for auto-open from URL)
     * @private
     */
    _openOverlay(options = {}) {
        const { skipFocus = false } = options;
        
        this._overlay.classList.add('active');
        this._overlay.removeAttribute('x-cloak');
        document.body.style.overflow = 'hidden';

        // Blur the placeholder input to prevent typing there
        if (this._mainInput) {
            this._mainInput.blur();
        }

        if (!this._app.isStarted()) {
            try {
                this._app.start();
            } catch (error) {
                console.error('Error starting InstantSearch:', error);
            }
        }

        // Focus on the searchBox input inside the overlay (unless skipFocus is true)
        if (!skipFocus) {
            this._focusSearchBox();
        }
    }

    /**
     * Open the overlay programmatically (public method)
     * Checks window._mmSearchAutoOpen flag to skip focus on auto-open from URL
     */
    open() {
        const skipFocus = window._mmSearchAutoOpen === true;
        this._openOverlay({ skipFocus });
    }

    /**
     * Close the overlay programmatically (public method)
     */
    close() {
        this._closeOverlay();
    }

    _focusSearchBox() {
        // Small delay to ensure the searchBox is rendered
        setTimeout(() => {
            const searchBoxInput = document.querySelector(this._selectors.searchBoxInput);
            if (searchBoxInput) {
                searchBoxInput.focus();
            }
        }, 100);
    }

    /**
     * Close overlay and clear the URL hash
     * @private
     */
    _closeOverlay() {
        this._overlay.classList.remove('active');
        document.body.style.overflow = '';
        
        // Clear the URL hash when closing the overlay
        this._clearSearchUrl();
    }

    /**
     * Close overlay without clearing URL (used for browser navigation)
     * @private
     */
    _closeOverlayWithoutClearingUrl() {
        this._overlay.classList.remove('active');
        document.body.style.overflow = '';
    }

    /**
     * Clear the search URL hash
     * @private
     */
    _clearSearchUrl() {
        // Only clear if there's actually a search hash
        if (this._hasSearchParams()) {
            const url = window.location.href.split('#')[0];
            window.history.replaceState(null, '', url);
        }
    }

    _isActive() {
        return this._overlay?.classList.contains('active');
    }

    /**
     * Check if overlay is currently active (public method)
     * @returns {boolean}
     */
    isActive() {
        return this._isActive();
    }

    _setupInfiniteScrollOnRender() {
        this._app.events.on('render', () => {
            this._setupInfiniteScrollObserver();
        });
    }

    _setupInfiniteScrollObserver() {
        const loadMoreButton = document.querySelector(this._selectors.loadMoreBtn);

        if (!loadMoreButton) {
            if (this._loadMoreObserver) {
                this._loadMoreObserver.disconnect();
                this._loadMoreObserver = null;
            }
            return;
        }

        if (this._loadMoreObserver && loadMoreButton.dataset.observed === 'true') {
            return;
        }

        if (this._loadMoreObserver) {
            this._loadMoreObserver.disconnect();
        }

        this._loadMoreObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const button = entry.target;
                    if (!button.classList.contains('ais-InfiniteHits-loadMore--disabled')) {
                        button.click();
                    }
                }
            });
        }, {
            root: null,
            rootMargin: '200px',
            threshold: 0.1
        });

        this._loadMoreObserver.observe(loadMoreButton);
        loadMoreButton.dataset.observed = 'true';
    }

    /**
     * Clean up event listeners
     */
    dispose() {
        window.removeEventListener('hashchange', this._onHashChange);
        
        if (this._loadMoreObserver) {
            this._loadMoreObserver.disconnect();
            this._loadMoreObserver = null;
        }
    }
}

export default OverlayManager;