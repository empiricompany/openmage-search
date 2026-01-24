/**
 * Static helper functions for hit templates
 */
export const HitHelpers = {
    /**
     * Get the best available image URL
     */
    getImageUrl(hit, placeholderUrl) {
        let imageUrl = placeholderUrl;
        
        if (hit.thumbnail_medium && hit.thumbnail_medium.search('placeholder') === -1) {
            imageUrl = hit.thumbnail_medium;
        } else if (hit.thumbnail_small && hit.thumbnail_small.search('placeholder') === -1) {
            imageUrl = hit.thumbnail_small;
        } else if (hit.thumbnail && hit.thumbnail.search('placeholder') === -1) {
            imageUrl = hit.thumbnail;
        }
        
        // Add origin if relative URL
        if (!imageUrl.startsWith('http://') && !imageUrl.startsWith('https://')) {
            imageUrl = window.location.origin + imageUrl;
        }
        
        return imageUrl;
    },

    /**
     * Get product URL from hit with UTM parameters
     * @param {Object} hit - The hit object from search results
     */
    getProductUrl(hit) {
        if (!hit.request_path) {
            return '#';
        }
        
        let url = `/${hit.request_path}`;
        if (window.instantSearchConfig?.useStoreCode) {
            const storeCode = window.instantSearchConfig?.storeCode;
            url = `/${storeCode}${url}`;
        }
        
        // Add UTM parameters for tracking
        const params = new URLSearchParams();
        //params.append('utm_source', 'internal_search');
        params.append('utm_medium', 'search');
        params.append('utm_campaign', 'site_search');
        url += `?${params.toString()}`;
        
        return url;
    },

    /**
     * Check if product is new based on dates
     */
    isNew(hit) {
        const now = new Date();
        const fromDate = hit.news_from_date ? new Date(hit.news_from_date) : null;
        const toDate = hit.news_to_date ? new Date(hit.news_to_date) : null;
        
        return (fromDate && now >= fromDate) && (!toDate || now <= toDate);
    },

    /**
     * Calculate discount percentage
     */
    calculateDiscount(price, specialPrice) {
        if (!price || !specialPrice || specialPrice >= price) return 0;
        return Math.round(((price - specialPrice) / price) * 100);
    },

    /**
     * Format price with currency using Magento's price format configuration
     */
    formatPrice(price, currency = null) {
        if (!price && price !== 0) return '';
        
        const numPrice = parseFloat(price);
        if (isNaN(numPrice)) return `${price}`;
        
        // Get price format configuration from instantSearchConfig
        const config = window.instantSearchConfig?.priceFormat;
        
        if (!config) {
            // Fallback to simple format if config not available
            return `${numPrice.toFixed(2)} ${currency || '€'}`;
        }
        
        // Round to required precision
        const precision = config.requiredPrecision || config.precision || 2;
        const rounded = numPrice.toFixed(precision);
        
        // Split into integer and decimal parts
        const [integerPart, decimalPart] = rounded.split('.');
        
        // Add thousand separators
        let formattedInteger = integerPart;
        if (config.groupSymbol && config.groupLength) {
            const groupLength = config.groupLength || 3;
            const regex = new RegExp(`\\B(?=(\\d{${groupLength}})+(?!\\d))`, 'g');
            formattedInteger = integerPart.replace(regex, config.groupSymbol);
        }
        
        // Combine with decimal symbol
        const formattedPrice = decimalPart
            ? `${formattedInteger}${config.decimalSymbol}${decimalPart}`
            : formattedInteger;
        
        // Apply pattern (e.g., "%s €" or "€ %s")
        const currencySymbol = currency || config.currencySymbol || '€';
        const pattern = config.pattern || '%s %s';
        
        return pattern.replace('%s', formattedPrice).replace('%s', currencySymbol).trim();
    },

    /**
     * Create image error handler
     */
    createImageErrorHandler(placeholderUrl) {
        return (e) => {
            if (e.target.src === placeholderUrl) return;
            e.target.onerror = null;
            e.target.src = placeholderUrl;
        };
    }
};

export default HitHelpers;