import { Utils } from './Utils.js';

/**
 * Registry for widget configurations
 */
export class WidgetRegistry {
    constructor() {
        this._configs = new Map();
        this._disabled = new Set();
    }

    register(name, config) {
        this._configs.set(name, config);
    }

    configure(name, overrides) {
        const existing = this._configs.get(name);
        this._configs.set(name, existing ? Utils.deepMerge(existing, overrides) : overrides);
    }

    remove(name) {
        this._disabled.add(name);
    }

    enable(name) {
        this._disabled.delete(name);
    }

    getConfig(name) {
        if (this._disabled.has(name)) return null;
        return this._configs.get(name) ?? null;
    }

    getActiveNames() {
        return [...this._configs.keys()].filter(name => !this._disabled.has(name));
    }
}

export default WidgetRegistry;