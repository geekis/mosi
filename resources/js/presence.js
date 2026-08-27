// Live presence dots and lines. A single private "presence" channel carries
// { user_id, status } deltas broadcast by app:sync-presence; each on-screen indicator
// registers a callback for its user id and repaints when a matching delta arrives. When
// Reverb is unavailable (window.Echo undefined — see echo.js), indicators simply keep
// their server-rendered state.

const listeners = new Map(); // userId -> Set<(status) => void>

function subscribe(userId, callback) {
    if (!listeners.has(userId)) {
        listeners.set(userId, new Set());
    }

    listeners.get(userId).add(callback);

    return () => listeners.get(userId)?.delete(callback);
}

function labelFor(status) {
    return (window.__presenceLabels ?? {})[status] ?? '';
}

if (window.Echo) {
    // The leading dot tells Echo to match our custom broadcastAs name verbatim.
    window.Echo.private('presence').listen('.UserPresenceChanged', (event) => {
        listeners.get(event.user_id)?.forEach((callback) => callback(event.status));
    });
}

document.addEventListener('alpine:init', () => {
    // The small overlay dot on an avatar. Fill classes are toggled by Alpine object-class
    // syntax in the Blade component; this only tracks the status string and its label.
    window.Alpine.data('presenceDot', (userId, initial) => ({
        status: initial,
        unsubscribe: null,

        init() {
            this.unsubscribe = subscribe(userId, (status) => {
                this.status = status;
            });
        },

        destroy() {
            this.unsubscribe?.();
        },

        get label() {
            return labelFor(this.status);
        },
    }));

    // The "dot + text" presence line (chat header, profile). Keeps its richer initial label
    // (e.g. "Last active 3 min ago") until the first live change, then shows plain states.
    window.Alpine.data('presenceLine', (userId, initialStatus, initialLabel) => ({
        status: initialStatus,
        label: initialLabel,
        unsubscribe: null,

        init() {
            this.unsubscribe = subscribe(userId, (status) => {
                this.status = status;
                this.label = labelFor(status) || this.label;
            });
        },

        destroy() {
            this.unsubscribe?.();
        },
    }));
});
