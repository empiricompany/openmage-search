/**
 * Utility functions
 */
export const Utils = {
    isObject: (item) => item && typeof item === 'object' && !Array.isArray(item),

    deepMerge(target, source) {
        const output = { ...target };
        if (this.isObject(target) && this.isObject(source)) {
            Object.keys(source).forEach(key => {
                if (this.isObject(source[key])) {
                    output[key] = key in target 
                        ? this.deepMerge(target[key], source[key]) 
                        : source[key];
                } else {
                    output[key] = source[key];
                }
            });
        }
        return output;
    },

    debounce(func, wait) {
        let timeout;
        return (...args) => {
            clearTimeout(timeout);
            timeout = setTimeout(() => func(...args), wait);
        };
    },

    humanize: (str) => str.charAt(0).toUpperCase() + str.slice(1).replace(/_/g, ' ')
};

export default Utils;