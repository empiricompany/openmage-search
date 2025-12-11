/**
 * InstantSearch Lazy Loader
 *
 * This script handles lazy loading of InstantSearch scripts when user
 * interacts with the search input for the first time.
 *
 * Also auto-loads if URL contains search parameters (#search?...)
 */
(function() {
    'use strict';

    var searchInput = document.getElementById('search');
    if (!searchInput) return;

    var scriptsLoaded = false;
    var HASH_PREFIX = '#search';

    /**
     * Check if current URL hash contains search parameters
     * @returns {boolean}
     */
    var hasSearchParams = function() {
        var hash = window.location.hash;
        return hash.indexOf(HASH_PREFIX) === 0 && hash.indexOf('?') > -1;
    };

    // Prevent form submit on Enter key and close mobile keyboard
    searchInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            e.target.blur(); // Close mobile keyboard
            return false;
        }
    });

    /**
     * Execute a lazy script by creating a new script element
     */
    var executeScript = function(script) {
        return new Promise(function(resolve) {
            var newScript = document.createElement('script');
            newScript.type = 'text/javascript';
            var parent = script.src ? document.head : document.body;
            
            if (script.src) {
                newScript.src = script.src;
                newScript.onload = resolve;
                newScript.onerror = function() {
                    console.error('Failed to load script: ' + script.src);
                    resolve();
                };
            } else {
                newScript.textContent = script.textContent;
            }
            
            parent.appendChild(newScript);
            script.remove();
            
            if (!script.src) {
                resolve();
            }
        });
    };

    /**
     * Activate preloaded CSS by converting to stylesheets
     */
    var activateLazyCss = function() {
        var lazyLinks = document.querySelectorAll('link[data-mm-lazy-css]');
        lazyLinks.forEach(function(link) {
            link.rel = 'stylesheet';
            link.removeAttribute('as');
            link.removeAttribute('data-mm-lazy-css');
        });
    };

    /**
     * Load all lazy scripts and start the app
     * @param {boolean} autoOpenFromUrl - Whether loading was triggered by URL params
     */
    var loadScripts = function(autoOpenFromUrl) {
        if (scriptsLoaded) return;
        scriptsLoaded = true;

        // Store flag for later use in onScriptsLoaded
        window._mmSearchAutoOpen = autoOpenFromUrl === true;

        activateLazyCss();

        var lazyScripts = document.querySelectorAll('script[type="text/mm-instantsearch-lazy"]');
        var scripts = Array.prototype.slice.call(lazyScripts);
        
        var loadNext = function(index) {
            if (index >= scripts.length) {
                onScriptsLoaded();
                return;
            }
            
            executeScript(scripts[index]).then(function() {
                loadNext(index + 1);
            });
        };
        
        loadNext(0);
    };

    /**
     * Called when all scripts are loaded
     */
    var onScriptsLoaded = function() {
        // The app auto-creates itself in instantsearch-app.js
        // Now we just need to open the overlay and start if not already started
        var app = window.instantSearchApp;
        var overlayManager = window.InstantSearchOverlayManager;
        
        if (overlayManager) {
            overlayManager.open();
        }
        
        if (app && !app.started) {
            try {
                app.start();
            } catch (error) {
                console.error('Error starting InstantSearch:', error);
            }
        }
        
        // Clean up
        delete window._mmSearchAutoOpen;
    };

    // Bind trigger events
    var triggerEvents = ['focus', 'click'];
    triggerEvents.forEach(function(event) {
        searchInput.addEventListener(event, function() {
            loadScripts(false);
        }, { once: true });
    });

    // Auto-load if URL has search parameters
    if (hasSearchParams()) {
        console.log('[MMSearch Loader] URL has search params, auto-loading scripts...');
        // Use small delay to ensure DOM is ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                loadScripts(true);
            });
        } else {
            loadScripts(true);
        }
    }
})();