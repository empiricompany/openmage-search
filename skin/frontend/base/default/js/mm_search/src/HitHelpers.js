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
     * Get product URL from hit
     */
    getProductUrl(hit) {
        if (hit.url_key) {
            return `/${hit.url_key}.html`;
        }
        return '#';
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
     * Format price with currency
     */
    formatPrice(price, currency = '€') {
        return `${price} ${currency}`;
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