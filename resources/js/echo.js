import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

/**
 * Real-time transport.
 *
 * Production is Hostinger shared hosting, which cannot run a persistent
 * WebSocket process, so this points at Pusher Channels. Reverb speaks the same
 * protocol — moving to a self-hosted server later is an env change, not a code
 * change, which is why the host and port are configurable here.
 */
const connection = import.meta.env.VITE_BROADCAST_CONNECTION;
const key = import.meta.env.VITE_PUSHER_APP_KEY;

if (key && ['pusher', 'reverb'].includes(connection)) {
    window.Pusher = Pusher;

    const host = import.meta.env.VITE_PUSHER_HOST;

    window.Echo = new Echo({
        broadcaster: 'pusher',
        key,
        cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER ?? 'ap2',
        wsHost: host || undefined,
        wsPort: Number(import.meta.env.VITE_PUSHER_PORT ?? 443),
        wssPort: Number(import.meta.env.VITE_PUSHER_PORT ?? 443),
        forceTLS: (import.meta.env.VITE_PUSHER_SCHEME ?? 'https') === 'https',
        enabledTransports: ['ws', 'wss'],
    });

    const pusher = window.Echo.connector.pusher;

    pusher.connection.bind('state_change', ({ current }) => {
        const connected = current === 'connected';

        window.dispatchEvent(new CustomEvent('echo-state', { detail: { connected } }));

        // Anything sent while the socket was down never arrived. Tell the
        // components to re-read from the server rather than trusting what is
        // already on screen.
        if (connected) {
            window.dispatchEvent(new CustomEvent('connection-restored'));
            window.Livewire?.dispatch('connection-restored');
        }
    });
}
