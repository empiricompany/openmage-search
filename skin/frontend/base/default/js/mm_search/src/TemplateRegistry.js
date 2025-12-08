/**
 * Registry for templates with prototype-based inheritance
 */
export class TemplateRegistry {
    constructor() {
        this._classes = new Map();
        this._overrides = new Map();
    }

    register(name, templateClass) {
        this._classes.set(name, templateClass);
    }

    get(name) {
        return this._classes.get(name) ?? null;
    }

    extend(name, overrides) {
        const existing = this._overrides.get(name) || {};
        this._overrides.set(name, { ...existing, ...overrides });
    }

    has(name) {
        return this._classes.has(name);
    }

    create(name, options = {}) {
        const TemplateClass = this._classes.get(name);
        if (!TemplateClass) return null;
        
        const instance = new TemplateClass(options);
        const overrides = this._overrides.get(name);
        
        if (overrides) {
            Object.keys(overrides).forEach(key => {
                if (typeof overrides[key] === 'function') {
                    instance[key] = overrides[key].bind(instance);
                } else {
                    instance[key] = overrides[key];
                }
            });
        }
        
        return instance;
    }
}

export default TemplateRegistry;
