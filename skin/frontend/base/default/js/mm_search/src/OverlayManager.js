/**
 * Manages search overlay UI interactions
 */
export class OverlayManager {
    constructor(app, options = {}) {
        this._app = app;
        this._overlay = null;
        this._mainInput = null;
        this._loadMoreObserver = null;
        
        this._selectors = {
            overlay: options.overlaySelector || '#typesense-overlay',
            mainInput: options.inputSelector || '#search',
            closeBtn: options.closeBtnSelector || '.typesense-close-btn',
            loadMoreBtn: '#typesense-hits .ais-InfiniteHits-loadMore:not(.ais-InfiniteHits-loadMore--disabled)'
        };
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
        
        this._openOverlay();
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

        // Sync input with search
        this._mainInput.addEventListener('input', (e) => {
            if (this._app.isStarted()) {
                this._app.setQuery(e.target.value);
            }
        });

        // Prevent form submit on Enter - intercept all forms in overlay
        this._overlay.addEventListener('submit', (e) => {
            e.preventDefault();
            return false;
        }, true);
    }

    _openOverlay() {
        this._overlay.classList.add('active');
        this._overlay.removeAttribute('x-cloak');
        document.body.style.overflow = 'hidden';

        if (!this._app.isStarted()) {
            try {
                this._app.start();
            } catch (error) {
                console.error('Error starting InstantSearch:', error);
            }
        }
    }

    _closeOverlay() {
        this._overlay.classList.remove('active');
        document.body.style.overflow = '';
    }

    _isActive() {
        return this._overlay?.classList.contains('active');
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
}

export default OverlayManager;