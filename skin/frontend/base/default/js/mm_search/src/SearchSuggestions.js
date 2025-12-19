/**
 * SearchSuggestions - Unified component for recent searches + query suggestions
 * Shows recent searches first, then Typesense suggestions, in a dropdown below the input
 */
export class SearchSuggestions {
    constructor(config) {
        this._config = config;
        this._queriesCollection = config.queriesCollection || `${config.collectionName}-product_queries`;
        this._storageKey = 'typesense_recent_searches';
        this._maxRecentSearches = 5;
        this._maxSuggestions = 5;
        
        this._container = null;
        this._input = null;
        this._dropdown = null;
        this._onSelect = null;
        
        this._debounceTimer = null;
        this._debounceMs = 200;
        this._minChars = 3;
        this._currentQuery = '';
        this._isVisible = false;
        this._highlightedIndex = -1;
    }

    /**
     * Initialize the search suggestions component
     * @param {string} inputSelector - CSS selector for the search input
     * @param {function} onSelect - Callback when a suggestion is selected
     */
    init(inputSelector, onSelect) {
        this._input = document.querySelector(inputSelector);
        this._onSelect = onSelect;

        if (!this._input) {
            console.warn('SearchSuggestions: input not found');
            return;
        }

        this._setupDropdown();
        this._bindEvents();
        
        // Initial render with recent searches
        this._renderAll();
    }

    /**
     * Create the dropdown element below the input
     */
    _setupDropdown() {
        const searchBox = this._input.closest('.ais-SearchBox');
        this._container = searchBox || this._input.parentElement;
        
        // Add relative positioning
        this._container.style.position = 'relative';
        
        // Create dropdown
        this._dropdown = document.createElement('div');
        this._dropdown.className = 'search-suggestions-dropdown';
        this._dropdown.setAttribute('role', 'listbox');
        
        // Style dropdown
        Object.assign(this._dropdown.style, {
            position: 'absolute',
            top: '100%',
            left: '0',
            right: '0',
            background: '#fff',
            border: '1px solid #dee2e6',
            borderTop: 'none',
            borderRadius: '0 0 8px 8px',
            boxShadow: '0 4px 12px rgba(0,0,0,0.1)',
            zIndex: '1000',
            display: 'none',
            maxHeight: '400px',
            overflowY: 'auto'
        });
        
        this._container.appendChild(this._dropdown);
    }

    /**
     * Bind events
     */
    _bindEvents() {
        // On input change
        this._input.addEventListener('input', (e) => {
            clearTimeout(this._debounceTimer);
            this._debounceTimer = setTimeout(() => {
                this._currentQuery = e.target.value.trim();
                this._renderAll();
            }, this._debounceMs);
        });

        // Show on focus
        this._input.addEventListener('focus', () => {
            this._renderAll();
            this._show();
        });

        // Hide on blur (with delay for click handling)
        this._input.addEventListener('blur', () => {
            setTimeout(() => this._hide(), 200);
        });

        // Keyboard navigation
        this._input.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                this._hide();
                return;
            }
            
            if (!this._isVisible) return;
            
            const items = this._dropdown.querySelectorAll('.ss-item');
            if (items.length === 0) return;
            
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                this._highlightedIndex = Math.min(this._highlightedIndex + 1, items.length - 1);
                this._updateHighlight(items);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                this._highlightedIndex = Math.max(this._highlightedIndex - 1, -1);
                this._updateHighlight(items);
            } else if (e.key === 'Enter' && this._highlightedIndex >= 0) {
                e.preventDefault();
                const query = items[this._highlightedIndex].dataset.query;
                if (query) {
                    this._selectQuery(query);
                }
            }
        });
        
        // Handle reset button click - behave like clearing input manually
        const resetButton = this._container?.querySelector('.ais-SearchBox-reset');
        if (resetButton) {
            resetButton.addEventListener('mousedown', (e) => {
                // Prevent blur from hiding dropdown before we can show it
                e.preventDefault();
            });
            resetButton.addEventListener('click', () => {
                // Reset and show suggestions like when input is cleared manually
                this._currentQuery = '';
                this._renderAll();
                this._input?.focus();
            });
        }
    }

    /**
     * Render the complete dropdown
     */
    async _renderAll() {
        const query = this._currentQuery;
        const recentSearches = this._getRecentSearches();
        let suggestions = [];
        
        // Fetch suggestions if query is long enough
        if (query.length >= this._minChars) {
            suggestions = await this._fetchSuggestions(query);
        } else if (query.length === 0) {
            suggestions = await this._fetchPopular();
        }
        
        // Filter recent searches by query
        const filteredRecent = query.length > 0
            ? recentSearches.filter(s => s.toLowerCase().includes(query.toLowerCase()))
            : recentSearches;
        
        // Remove duplicates (recent searches that are also in suggestions)
        const suggestionTexts = new Set(suggestions.map(s => s.text.toLowerCase()));
        const uniqueRecent = filteredRecent.filter(s => !suggestionTexts.has(s.toLowerCase()));
        
        // Build HTML
        let html = '';
        
        // Recent searches section
        if (uniqueRecent.length > 0) {
            html += `
                <div class="ss-section ss-section--recent">
                    <div class="ss-section-header">
                        <span class="ss-section-title">Ricerche recenti</span>
                    </div>
                    <ul class="ss-list">
                        ${uniqueRecent.slice(0, this._maxRecentSearches).map(term => `
                            <li class="ss-item ss-item--recent" data-query="${this._escapeHtml(term)}">
                                <span class="ss-item-text">${this._highlightMatch(term, query)}</span>
                                <button type="button" class="ss-item-delete" data-action="remove" title="Rimuovi">×</button>
                            </li>
                        `).join('')}
                    </ul>
                </div>
            `;
        }
        
        // Suggestions section
        if (suggestions.length > 0) {
            html += `
                <div class="ss-section ss-section--suggestions">
                    ${uniqueRecent.length > 0 ? '<div class="ss-section-header"><span class="ss-section-title">Suggerimenti</span></div>' : ''}
                    <ul class="ss-list">
                        ${suggestions.map(s => `
                            <li class="ss-item ss-item--suggestion" data-query="${this._escapeHtml(s.text)}">
                                <span class="ss-item-text">${s.highlighted || this._highlightMatch(s.text, query)}</span>
                            </li>
                        `).join('')}
                    </ul>
                </div>
            `;
        }
        
        // No results - hide dropdown instead of showing message
        
        this._dropdown.innerHTML = html;
        this._highlightedIndex = -1;
        this._bindItemEvents();
        
        // Show/hide dropdown
        if (html && document.activeElement === this._input) {
            this._show();
        } else if (!html) {
            this._hide();
        }
    }

    /**
     * Bind click events to items
     */
    _bindItemEvents() {
        // Item click
        this._dropdown.querySelectorAll('.ss-item').forEach(item => {
            item.addEventListener('click', (e) => {
                // Check if delete button was clicked
                if (e.target.closest('.ss-item-delete')) {
                    e.stopPropagation();
                    const query = item.dataset.query;
                    this._removeRecentSearch(query);
                    this._renderAll();
                    return;
                }
                
                const query = item.dataset.query;
                if (query) {
                    this._selectQuery(query);
                }
            });
        });
    }

    /**
     * Select a query
     */
    _selectQuery(query) {
        // Add to recent searches
        this._addRecentSearch(query);
        
        // Update input
        if (this._input) {
            this._input.value = query;
            this._input.dispatchEvent(new Event('input', { bubbles: true }));
        }
        
        // Call callback
        if (this._onSelect) {
            this._onSelect(query);
        }
        
        this._hide();
    }

    /**
     * Fetch popular suggestions
     */
    async _fetchPopular() {
        try {
            const client = this._getTypesenseClient();
            if (!client) return [];

            const response = await client
                .collections(this._queriesCollection)
                .documents()
                .search({
                    q: '*',
                    query_by: 'q',
                    sort_by: 'count:desc',
                    per_page: this._maxSuggestions * 3
                });

            return response.hits
                .map(hit => ({
                    text: hit.document.q,
                    count: hit.document.count
                }))
                .filter(s => s.text && s.text.trim().length >= 4)
                .slice(0, this._maxSuggestions);
        } catch (error) {
            console.error('SearchSuggestions: error fetching popular', error);
            return [];
        }
    }

    /**
     * Fetch suggestions for query
     */
    async _fetchSuggestions(query) {
        try {
            const client = this._getTypesenseClient();
            if (!client) return [];

            const response = await client
                .collections(this._queriesCollection)
                .documents()
                .search({
                    q: query,
                    query_by: 'q',
                    sort_by: '_text_match:desc,count:desc',
                    per_page: this._maxSuggestions * 3,
                    prefix: true
                });

            return response.hits
                .map(hit => ({
                    text: hit.document.q,
                    highlighted: hit.highlight?.q?.snippet || hit.document.q,
                    count: hit.document.count
                }))
                .filter(s => s.text && s.text.trim().length >= 2)
                .slice(0, this._maxSuggestions);
        } catch (error) {
            console.error('SearchSuggestions: error fetching suggestions', error);
            return [];
        }
    }

    /**
     * Get Typesense client
     */
    _getTypesenseClient() {
        if (this._config.instantsearchAdapter && this._config.instantsearchAdapter.typesenseClient) {
            return this._config.instantsearchAdapter.typesenseClient;
        }
        return null;
    }

    // Recent searches localStorage methods
    _getRecentSearches() {
        try {
            const stored = localStorage.getItem(this._storageKey);
            return stored ? JSON.parse(stored) : [];
        } catch (e) {
            return [];
        }
    }

    _addRecentSearch(query) {
        if (!query || query.trim().length < 2) return;
        
        const normalized = query.trim();
        let items = this._getRecentSearches();
        items = items.filter(item => item.toLowerCase() !== normalized.toLowerCase());
        items.unshift(normalized);
        items = items.slice(0, 10);
        
        try {
            localStorage.setItem(this._storageKey, JSON.stringify(items));
        } catch (e) {}
    }

    /**
     * Public method to add a recent search (called externally)
     */
    addRecentSearch(query) {
        this._addRecentSearch(query);
    }

    /**
     * Get current query
     */
    getCurrentQuery() {
        return this._currentQuery;
    }

    _removeRecentSearch(query) {
        const normalized = query.toLowerCase();
        const items = this._getRecentSearches().filter(item => item.toLowerCase() !== normalized);
        try {
            localStorage.setItem(this._storageKey, JSON.stringify(items));
        } catch (e) {}
    }

    // UI helpers
    _show() {
        if (this._dropdown) {
            this._dropdown.style.display = 'block';
            this._isVisible = true;
        }
    }

    _hide() {
        if (this._dropdown) {
            this._dropdown.style.display = 'none';
            this._isVisible = false;
        }
    }

    /**
     * Update visual highlight on items
     */
    _updateHighlight(items) {
        items.forEach((item, index) => {
            if (index === this._highlightedIndex) {
                item.classList.add('ss-item--highlighted');
                item.scrollIntoView({ block: 'nearest' });
            } else {
                item.classList.remove('ss-item--highlighted');
            }
        });
    }

    _escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    _highlightMatch(text, query) {
        if (!query) return this._escapeHtml(text);
        
        const escaped = this._escapeHtml(text);
        const regex = new RegExp(`(${this._escapeRegex(query)})`, 'gi');
        return escaped.replace(regex, '<mark>$1</mark>');
    }

    _escapeRegex(str) {
        return str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }

    /**
     * Destroy the component
     */
    destroy() {
        if (this._dropdown && this._dropdown.parentElement) {
            this._dropdown.parentElement.removeChild(this._dropdown);
        }
        this._dropdown = null;
        this._input = null;
    }
}

export default SearchSuggestions;