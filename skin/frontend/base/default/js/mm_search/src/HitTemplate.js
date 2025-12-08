import { HitHelpers } from './HitHelpers.js';

/**
 * Base template class for hit rendering
 * Clients can extend this by using templates.extend('hit', {...})
 */
export class HitTemplate {
    constructor(options = {}) {
        this.placeholderUrl = options.placeholderUrl || 
            '/skin/frontend/rwd/lightclean/images/catalog/product/placeholder/image.jpg';
    }

    /**
     * Override in custom templates
     */
    renderNewBadge(hit, html) {
        if (HitHelpers.isNew(hit)) {
            return html`<span class="new">Novità</span>`;
        }
        return null;
    }

    /**
     * Override in custom templates
     */
    renderDiscountBadge(hit, html) {
        const discount = HitHelpers.calculateDiscount(hit.price, hit.special_price);
        if (discount > 0) {
            return html`<span class="sale">Sconto -${discount}%</span>`;
        }
        return null;
    }

    /**
     * Override in custom templates - default returns null
     */
    renderGenereBadge(hit, html) {
        return null;
    }

    /**
     * Override in custom templates
     */
    renderPrice(hit, html) {
        if (!hit.price) return null;

        if (hit.special_price && hit.special_price < hit.price) {
            const discount = HitHelpers.calculateDiscount(hit.price, hit.special_price);
            return html`
                <span class="old-price">
                    <span class="price">${HitHelpers.formatPrice(hit.price)}</span>
                </span>
                <span class="special-price">
                    <span class="price">${HitHelpers.formatPrice(hit.special_price)}</span>
                </span>
                <p class="discount-price-save save-price">
                    <span class="price">-${discount}%</span>
                </p>`;
        }

        return html`
            <span class="special-price">
                <span class="regular-price">
                    <span class="price">${HitHelpers.formatPrice(hit.price)}</span>
                </span>
            </span>`;
    }

    /**
     * Override in custom templates - default returns null
     */
    renderSizes(hit, html) {
        return null;
    }

    /**
     * Main render method - can be overridden for complete layout control
     */
    render(hit, { html, components }) {
        const imageUrl = HitHelpers.getImageUrl(hit, this.placeholderUrl);
        const productUrl = HitHelpers.getProductUrl(hit);
        const handleImageError = HitHelpers.createImageErrorHandler(this.placeholderUrl);

        const newBadge = this.renderNewBadge(hit, html);
        const discountBadge = this.renderDiscountBadge(hit, html);
        const genereBadge = this.renderGenereBadge(hit, html);
        const priceHtml = this.renderPrice(hit, html);
        const sizesHtml = this.renderSizes(hit, html);

        return html`
            <div class="wrap-media">
                <a href="${productUrl}" class="product-image">
                    <img src="${imageUrl}" alt="${hit.name || 'Prodotto'}"
                        onerror=${handleImageError} />
                </a>
                <div class="product-badges">
                    ${newBadge || ''} ${discountBadge || ''} ${genereBadge || ''}
                </div>
            </div>
            <div class="wrap-flex-sb">
                <div class="product-info">
                    <h2 class="product-name">
                        <a href="${productUrl}">
                            ${components.Highlight({ hit, attribute: 'name' })}
                        </a>
                    </h2>
                    ${sizesHtml || ''}
                </div>
                <div class="actions">
                    <div class="price-box">
                        ${priceHtml}
                    </div>
                </div>
            </div>
        `;
    }
}

export default HitTemplate;