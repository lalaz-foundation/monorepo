/**
 * Lalaz Reactive - Reactive Components for Lalaz Framework
 *
 * Requires: Alpine.js and Alpine Morph plugin
 *
 * @package lalaz/reactive
 * @author Gregory Serrao <hello@lalaz.dev>
 * @link https://lalaz.dev
 */

class LalazReactiveComponent {
    constructor(el) {
        this.el = el;
        this.el._lalaz = this; // Link element to this instance

        this.id = el.getAttribute("reactive:id");
        this.name = el.getAttribute("reactive:name");

        this.listeners = JSON.parse(el.getAttribute("reactive:listeners") || "[]");
        this.debouncedHandlers = new Map();
        this.globalHandlers = []; // To keep track of window event listeners

        // Loading state management
        this.loading = false;
        this.loadingTargets = new Set(); // Track which methods are loading

        this.init();
        this.registerGlobalListeners();
    }

    /**
     * Clean up global event listeners to prevent memory leaks and zombie listeners.
     */
    destroy() {
        this.globalHandlers.forEach(({ event, handler }) => {
            window.removeEventListener(event, handler);
        });
        this.globalHandlers = [];
        if (this.el) {
            this.el._lalaz = null;
        }
    }

    /**
     * Initialize event listeners using event delegation.
     */
    init() {
        this.el.addEventListener("click", (e) => {
            const target = e.target.closest("[reactive\\:click]");
            if (target && this.el.contains(target)) {
                e.preventDefault();
                const expression = target.getAttribute("reactive:click");
                const loadingTarget = target.getAttribute("reactive:target") || expression.split("(")[0];
                this.call(expression, {}, loadingTarget);
            }
        });

        this.el.addEventListener("submit", (e) => {
            const target = e.target.closest("[reactive\\:submit]");
            if (target && this.el.contains(target)) {
                e.preventDefault();
                const method = target.getAttribute("reactive:submit");
                const loadingTarget = target.getAttribute("reactive:target") || method;
                this.call(method, {}, loadingTarget);
            }
        });

        const modelHandler = (event, isLazy) => {
            const target = event.target;
            if (!target.hasAttribute("reactive:model")) return;

            const modelAttr = target.getAttribute("reactive:model");
            const modifiers = this.parseModifiers(modelAttr);
            const useDebounce = modifiers.includes("debounce");
            const useLazy = modifiers.includes("lazy");

            if (isLazy !== useLazy && !useLazy) return;
            if (!isLazy && useLazy) return;

            const property = modelAttr.split(".")[0];

            if (useDebounce) {
                if (!this.debouncedHandlers.has(property)) {
                    const delay = this.getDebounceDelay(modelAttr);
                    this.debouncedHandlers.set(
                        property,
                        this.debounce((value) => {
                            this.updateProperty(property, value);
                        }, delay),
                    );
                }
                this.debouncedHandlers.get(property)(target.value);
            } else {
                this.updateProperty(property, target.value);
            }
        };

        this.el.addEventListener("input", (e) => modelHandler(e, false));
        this.el.addEventListener("blur", (e) => modelHandler(e, true));
    }

    /**
     * Register listeners for global events
     */
    registerGlobalListeners() {
        this.listeners.forEach((eventName) => {
            const handler = (e) => {
                // Ensure component is still in the DOM before acting
                if (document.body.contains(this.el)) {
                    this.call(`$listener('${eventName}')`, e.detail);
                }
            };
            this.globalHandlers.push({ event: eventName, handler: handler });
            window.addEventListener(eventName, handler);
        });
    }

    /**
     * Set loading state and update DOM
     */
    setLoading(isLoading, target = null) {
        this.loading = isLoading;

        if (target) {
            if (isLoading) {
                this.loadingTargets.add(target);
            } else {
                this.loadingTargets.delete(target);
            }
        }

        // Update loading indicators in the DOM
        this.updateLoadingIndicators(target);
    }

    /**
     * Check if a specific target is loading
     */
    isTargetLoading(target) {
        return this.loadingTargets.has(target);
    }

    /**
     * Update all loading indicators in the component
     */
    updateLoadingIndicators(activeTarget = null) {
        // Handle reactive:loading (show/hide elements)
        this.el.querySelectorAll("[reactive\\:loading]").forEach((el) => {
            const target = el.getAttribute("reactive:loading") || "";
            const shouldShow = target
                ? this.isTargetLoading(target)
                : this.loading;

            // Check for .remove modifier
            if (el.hasAttribute("reactive:loading.remove")) {
                el.style.display = shouldShow ? "none" : "";
            } else {
                el.style.display = shouldShow ? "" : "none";
            }
        });

        // Handle reactive:loading.remove (hide when loading)
        this.el.querySelectorAll("[reactive\\:loading\\.remove]").forEach((el) => {
            const target = el.getAttribute("reactive:loading.remove") || "";
            const shouldHide = target
                ? this.isTargetLoading(target)
                : this.loading;
            el.style.display = shouldHide ? "none" : "";
        });

        // Handle reactive:loading.class (add/remove classes)
        this.el.querySelectorAll("[reactive\\:loading\\.class]").forEach((el) => {
            const value = el.getAttribute("reactive:loading.class");
            const [classes, target] = this.parseLoadingValue(value);
            const shouldApply = target
                ? this.isTargetLoading(target)
                : this.loading;

            classes.split(" ").forEach((cls) => {
                if (cls) {
                    if (shouldApply) {
                        el.classList.add(cls);
                    } else {
                        el.classList.remove(cls);
                    }
                }
            });
        });

        // Handle reactive:loading.class.remove (remove classes when loading)
        this.el.querySelectorAll("[reactive\\:loading\\.class\\.remove]").forEach((el) => {
            const value = el.getAttribute("reactive:loading.class.remove");
            const [classes, target] = this.parseLoadingValue(value);
            const shouldRemove = target
                ? this.isTargetLoading(target)
                : this.loading;

            classes.split(" ").forEach((cls) => {
                if (cls) {
                    if (shouldRemove) {
                        el.classList.remove(cls);
                    } else {
                        el.classList.add(cls);
                    }
                }
            });
        });

        // Handle reactive:loading.attr (set attribute when loading)
        this.el.querySelectorAll("[reactive\\:loading\\.attr]").forEach((el) => {
            const value = el.getAttribute("reactive:loading.attr");
            const [attr, target] = this.parseLoadingValue(value);
            const shouldApply = target
                ? this.isTargetLoading(target)
                : this.loading;

            if (shouldApply) {
                el.setAttribute(attr, attr); // e.g., disabled="disabled"
            } else {
                el.removeAttribute(attr);
            }
        });

        // Handle reactive:loading.attr.remove (remove attribute when loading)
        this.el.querySelectorAll("[reactive\\:loading\\.attr\\.remove]").forEach((el) => {
            const value = el.getAttribute("reactive:loading.attr.remove");
            const [attr, target] = this.parseLoadingValue(value);
            const shouldRemove = target
                ? this.isTargetLoading(target)
                : this.loading;

            if (shouldRemove) {
                el.removeAttribute(attr);
            } else {
                el.setAttribute(attr, attr);
            }
        });

        // Handle reactive:loading.delay (show loading only after delay)
        // This is handled in setLoading with setTimeout
    }

    /**
     * Parse loading value like "opacity-50(save)" into [value, target]
     */
    parseLoadingValue(value) {
        const match = value.match(/^(.+?)(?:\((\w+)\))?$/);
        if (match) {
            return [match[1], match[2] || null];
        }
        return [value, null];
    }

    /**
     * Call a component method
     */
    async call(expression, extraData = {}, loadingTarget = null) {
        const { method, params } = this.parseExpression(expression);
        const target = loadingTarget || method;

        // Set loading state
        this.setLoading(true, target);

        try {
            const response = await fetch("/lalaz-reactive/call", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-Requested-With": "XMLHttpRequest",
                },
                body: JSON.stringify({
                    id: this.id,
                    name: this.name,
                    method: method,
                    params: params,
                    state: this.getState(),
                    ...extraData,
                }),
            });

            const html = await response.text();
            const metadata = this.extractMetadata(html);
            const cleanHtml = html.replace(/<!--\s*LALAZ-REACTIVE:.*?-->/g, "");

            if (window.Alpine && window.Alpine.morph) {
                Alpine.morph(this.el, cleanHtml);
            } else {
                const id = this.id;
                this.destroy(); // Destroy old instance's listeners
                this.el.outerHTML = cleanHtml;
                const newEl = document.querySelector(`[reactive\\:id="${id}"]`);
                if (newEl && !newEl._lalaz) {
                    new LalazReactiveComponent(newEl);
                }
            }

            if (metadata) {
                this.handleMetadata(metadata);
            }
        } catch (error) {
            console.error("Lalaz Reactive Error:", error);
        } finally {
            // Clear loading state
            this.setLoading(false, target);
        }
    }

    /**
     * Update a component property
     */
    async updateProperty(property, value) {
        // Set loading state for property updates
        this.setLoading(true, property);

        try {
            const response = await fetch("/lalaz-reactive/update", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-Requested-With": "XMLHttpRequest",
                },
                body: JSON.stringify({
                    id: this.id,
                    name: this.name,
                    property: property,
                    value: value,
                    state: this.getState(),
                }),
            });

            const html = await response.text();
            const metadata = this.extractMetadata(html);
            const cleanHtml = html.replace(/<!--\s*LALAZ-REACTIVE:.*?-->/g, "");

            if (window.Alpine && window.Alpine.morph) {
                Alpine.morph(this.el, cleanHtml);
            } else {
                const id = this.id;
                this.destroy();
                this.el.outerHTML = cleanHtml;
                const newEl = document.querySelector(`[reactive\\:id="${id}"]`);
                if (newEl && !newEl._lalaz) {
                    new LalazReactiveComponent(newEl);
                }
            }

            if (metadata) {
                this.handleMetadata(metadata);
            }
        } catch (error) {
            console.error("Lalaz Reactive Error:", error);
        } finally {
            this.setLoading(false, property);
        }
    }

    /**
     * Parse expression like "increment(123)" into method and params
     */
    parseExpression(expression) {
        if (expression.startsWith("$listener(")) {
            const match = expression.match(/\$listener\('(.+?)'\)/);
            if (match) {
                return { method: expression, params: [] };
            }
        }

        const match = expression.match(/^(\w+)(?:\((.*)\))?$/);
        if (!match) {
            throw new Error(`Invalid expression: ${expression}`);
        }

        const method = match[1];
        const paramsStr = match[2];
        const params = paramsStr
            ? paramsStr.split(",").map((p) => {
                  const trimmed = p.trim();
                  try {
                      return JSON.parse(trimmed);
                  } catch {
                      return trimmed;
                  }
              })
            : [];
        return { method, params };
    }

    /**
     * Parse modifiers from reactive:model.debounce.500ms
     */
    parseModifiers(attribute) {
        return attribute.split(".").slice(1);
    }

    /**
     * Get debounce delay from reactive:model.debounce.500ms
     */
    getDebounceDelay(attribute) {
        const match = attribute.match(/\.(\d+)ms/);
        return match ? parseInt(match[1]) : 300;
    }

    /**
     * Debounce function
     */
    debounce(func, delay) {
        let timeout;
        return function (...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(this, args), delay);
        };
    }

    /**
     * Get current component state
     */
    getState() {
        return {
            id: this.id,
            name: this.name,
            properties: this.getProperties(),
        };
    }

    /**
     * Get component properties from DOM
     */
    getProperties() {
        let properties = {};

        // Get base state from reactive:state attribute
        const stateAttr = this.el.getAttribute("reactive:state");
        if (stateAttr) {
            try {
                properties = JSON.parse(stateAttr);
            } catch (e) {
                console.error("Failed to parse reactive:state", e);
            }
        }

        // Merge with current input values from reactive:model elements
        // This ensures lazy/debounced inputs have their current values captured
        // Find all elements with attributes starting with reactive:model
        const allElements = this.el.querySelectorAll("input, textarea, select");
        allElements.forEach((el) => {
            // Check for any reactive:model attribute (including .lazy, .debounce variants)
            let modelAttr = null;
            let property = null;

            for (const attr of el.attributes) {
                if (attr.name.startsWith("reactive:model")) {
                    modelAttr = attr.name;
                    property = attr.value.split(".")[0]; // The value is the property name
                    break;
                }
            }

            if (!property) return;

            // Get the current value based on input type
            let newValue;
            if (el.type === "checkbox") {
                newValue = el.checked;
            } else if (el.type === "radio") {
                if (el.checked) {
                    newValue = el.value;
                } else {
                    return; // Skip unchecked radios
                }
            } else if (el.tagName === "SELECT" && el.multiple) {
                newValue = Array.from(el.selectedOptions).map(opt => opt.value);
            } else {
                newValue = el.value;
            }

            // Only update if the new value is not empty, or if the original was also a string
            // This prevents empty inputs from overwriting numeric properties with empty string
            const originalValue = properties[property];
            if (newValue === "" && typeof originalValue === "number") {
                return; // Don't overwrite number with empty string
            }

            properties[property] = newValue;
        });

        return properties;
    }

    /**
     * Extract metadata from HTML comment
     */
    extractMetadata(html) {
        const match = html.match(/<!--\s*LALAZ-REACTIVE:\s*({.*?})\s*-->/);
        if (match) {
            try {
                return JSON.parse(match[1]);
            } catch (e) {
                return null;
            }
        }
        return null;
    }

    /**
     * Handle metadata (dispatches, redirects, notifications)
     */
    handleMetadata(metadata) {
        if (metadata.dispatches && metadata.dispatches.length > 0) {
            metadata.dispatches.forEach((dispatch) => {
                window.LalazReactive.dispatch(dispatch.event, dispatch.data);
            });
        }
        if (metadata.redirect) {
            window.location.href = metadata.redirect;
        }
        if (metadata.notifications && metadata.notifications.length > 0) {
            metadata.notifications.forEach((notification) => {
                window.LalazReactive.notify(notification.message, notification.type);
            });
        }
    }
}

/**
 * Global LalazReactive object
 */
window.LalazReactive = window.LalazReactive || {
    init(root = document) {
        const elements = root.querySelectorAll("[reactive\\:id]");
        elements.forEach((el) => {
            if (!el._lalaz) {
                new LalazReactiveComponent(el);
            }
        });
    },

    dispatch(eventName, data = {}) {
        window.dispatchEvent(new CustomEvent(eventName, { detail: data }));
    },

    notify(message, type = "success") {
        // This can be overridden in your app to show actual notifications
        console.log(`[Lalaz NOTIFY] [${type.toUpperCase()}] ${message}`);
    },
};

/**
 * Auto-initialize all components on DOMContentLoaded
 */
document.addEventListener("DOMContentLoaded", () => {
    window.LalazReactive.init(document.body);
});

/**
 * Re-initialize components after HTMX or other dynamic updates
 */
document.addEventListener("htmx:afterSwap", (event) => {
    window.LalazReactive.init(event.detail.elt);
});
