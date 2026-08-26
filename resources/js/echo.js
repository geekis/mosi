// Laravel Echo over Reverb. Exposes window.Echo so Livewire's `echo-private:` listeners
// can subscribe to broadcasts (e.g. the live group-post badge). Configured from the
// VITE_REVERB_* build-time env; if those are unset the client simply never connects,
// which is harmless — features fall back to their server-rendered state.
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

// Only initialise when the Reverb key is present at build time. Without it, Echo/Pusher
// would throw and take the rest of app.js down with it (breaking Livewire, navigation
// and the polls) — so we leave window.Echo undefined and features fall back to polling.
const reverbKey = import.meta.env.VITE_REVERB_APP_KEY;

if (reverbKey) {
    window.Pusher = Pusher;

    window.Echo = new Echo({
        broadcaster: 'reverb',
        key: reverbKey,
        wsHost: import.meta.env.VITE_REVERB_HOST,
        wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
        wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
        forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
        enabledTransports: ['ws', 'wss'],
    });
}
