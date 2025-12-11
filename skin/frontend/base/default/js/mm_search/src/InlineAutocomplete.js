/**
 * Inline Autocomplete - shows typeahead suggestion in the input field
 * The suggestion appears in gray text that completes what the user is typing (Google-style)
 */
export class InlineAutocomplete {
    constructor(config) {
        this._config = config;
        this._queriesCollection = config.queriesCollection || `${config.collectionName}-product_queries`;
        this._input = null;
        this._ghost = null;
        this._wrapper = null;
        this._currentSuggestion = '';
        this._debounceTimer = null;
        this._debounceMs = 200;
        this._minChars = 3;
    }

    /**
     * Initialize the inline autocomplete
     * @param {string} inputSelector - CSS selector for the search input
     */
    init(inputSelector) {
        this._input = document.querySelector(inputSelector);
        
        if (!this._input) {
            console.warn('InlineAutocomplete: input not found');
            return;
        }

        this._setupGhostElement();
        this._bindEvents();
    }

    /**
     * Create the ghost element that shows the suggestion overlayed on input
     */
    _setupGhostElement() {
        // Find the form wrapper
        const form = this._input.closest('.ais-SearchBox-form') || this._input.parentElement;
        this._wrapper = form;
        
        // Add relative positioning to wrapper
        this._wrapper.style.position = 'relative';
        
        // Create ghost element
        this._ghost = document.createElement('span');
        this._ghost.className = 'inline-autocomplete-ghost';
        this._ghost.setAttribute('aria-hidden', 'true');
        
        // Get computed styles from input
        const inputStyles = getComputedStyle(this._input);
        
        // Get the left offset (leave space for search icon)
        const paddingLeft = inputStyles.paddingLeft || '2.5rem';
        
        // Style the ghost to overlay the input (text area only)
        Object.assign(this._ghost.style, {
            position: 'absolute',
            top: '0',
            left: paddingLeft,
            right: '0',
            bottom: '0',
            pointerEvents: 'none',
            display: 'flex',
            alignItems: 'center',
            paddingRight: inputStyles.paddingRight || '12px',
            fontFamily: inputStyles.fontFamily,
            fontSize: inputStyles.fontSize,
            fontWeight: inputStyles.fontWeight,
            letterSpacing: inputStyles.letterSpacing,
            lineHeight: inputStyles.lineHeight,
            color: 'transparent',
            whiteSpace: 'pre',
            overflow: 'hidden',
            zIndex: '1',
            background: 'transparent'
        });
        
        // Add class instead of inline style (CSS handles background)
        this._input.classList.add('inline-autocomplete-input');
        
        // Insert ghost before input
        this._wrapper.insertBefore(this._ghost, this._input);
    }

    /**
     * Bind input events
     */
    _bindEvents() {
        // On input change, fetch suggestions
        this._input.addEventListener('input', (e) => {
            clearTimeout(this._debounceTimer);
            this._debounceTimer = setTimeout(() => {
                this._onInput(e.target.value);
            }, this._debounceMs);
        });

        // Tab/ArrowRight to accept suggestion
        this._input.addEventListener('keydown', (e) => {
            if ((e.key === 'Tab' || e.key === 'ArrowRight') && this._currentSuggestion) {
                // Only accept if cursor is at the end
                if (this._input.selectionStart === this._input.value.length) {
                    e.preventDefault();
                    this._acceptSuggestion();
                }
            }
        });

        // Clear ghost on blur
        this._input.addEventListener('blur', () => {
            this._clearGhost();
        });

        // Show ghost on focus if we have a suggestion
        this._input.addEventListener('focus', () => {
            if (this._input.value.length >= this._minChars) {
                this._onInput(this._input.value);
            }
        });
    }

    /**
     * Handle input change
     * @param {string} query - Current input value
     */
    async _onInput(query) {
        if (query.length < this._minChars) {
            this._clearGhost();
            return;
        }

        try {
            const suggestion = await this._fetchBestSuggestion(query);
            
            // Only show if suggestion starts with query and is different
            if (suggestion && 
                suggestion.toLowerCase().startsWith(query.toLowerCase()) &&
                suggestion.toLowerCase() !== query.toLowerCase()) {
                this._showSuggestion(query, suggestion);
            } else {
                this._clearGhost();
            }
        } catch (error) {
            console.error('InlineAutocomplete: error fetching suggestion', error);
            this._clearGhost();
        }
    }

    /**
     * Fetch the best matching suggestion from Typesense
     * @param {string} query - The search query
     * @returns {Promise<string|null>}
     */
    async _fetchBestSuggestion(query) {
        const client = this._getTypesenseClient();
        if (!client) return null;

        try {
            const response = await client
                .collections(this._queriesCollection)
                .documents()
                .search({
                    q: query,
                    query_by: 'q',
                    sort_by: 'count:desc',
                    per_page: 5, // Get a few to filter
                    prefix: true
                });

            if (response.hits && response.hits.length > 0) {
                // Find first valid suggestion (not empty, at least 2 chars)
                for (const hit of response.hits) {
                    const suggestion = hit.document.q;
                    if (suggestion && suggestion.trim().length >= 2) {
                        return suggestion;
                    }
                }
            }
        } catch (error) {
            // Collection might not exist
            console.debug('InlineAutocomplete: fetch error', error);
        }

        return null;
    }

    /**
     * Get the Typesense client from config
     * @returns {Object|null}
     */
    _getTypesenseClient() {
        if (this._config.instantsearchAdapter && this._config.instantsearchAdapter.typesenseClient) {
            return this._config.instantsearchAdapter.typesenseClient;
        }
        return null;
    }

    /**
     * Show the suggestion in the ghost element
     * @param {string} typed - What the user has typed
     * @param {string} suggestion - The full suggestion
     */
    _showSuggestion(typed, suggestion) {
        this._currentSuggestion = suggestion;
        
        // The ghost shows: invisible typed text + gray completion
        const completion = suggestion.substring(typed.length);
        
        // Create the ghost content
        this._ghost.innerHTML = '';
        
        // Invisible part (matches what user typed - same width but transparent)
        const invisiblePart = document.createElement('span');
        invisiblePart.textContent = typed;
        invisiblePart.style.visibility = 'hidden';
        
        // Visible gray part (the completion)
        const visiblePart = document.createElement('span');
        visiblePart.textContent = completion;
        visiblePart.style.color = '#999';
        
        this._ghost.appendChild(invisiblePart);
        this._ghost.appendChild(visiblePart);
    }

    /**
     * Clear the ghost suggestion
     */
    _clearGhost() {
        this._currentSuggestion = '';
        if (this._ghost) {
            this._ghost.innerHTML = '';
        }
    }

    /**
     * Accept the current suggestion
     */
    _acceptSuggestion() {
        if (this._currentSuggestion && this._input) {
            this._input.value = this._currentSuggestion;
            this._input.dispatchEvent(new Event('input', { bubbles: true }));
            this._clearGhost();
        }
    }

    /**
     * Destroy the component
     */
    destroy() {
        if (this._ghost && this._ghost.parentElement) {
            this._ghost.parentElement.removeChild(this._ghost);
        }
        this._ghost = null;
        this._input = null;
    }
}

export default InlineAutocomplete;