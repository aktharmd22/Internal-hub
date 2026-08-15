/**
 * PWA registration and web push subscription.
 *
 * The service worker is a plain file in public/ rather than a Vite plugin
 * build step: the cache list is small and hand-written, which keeps the
 * offline story easy to reason about and the deploy to shared hosting a
 * straight file copy.
 */
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(() => {
            /* Offline support is a bonus, never a requirement. */
        });
    });
}

function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const raw = window.atob(base64);

    return Uint8Array.from([...raw].map((char) => char.charCodeAt(0)));
}

/**
 * Sound only works while the tab is open, so push is the other half of the
 * story — it is what reaches somebody who has closed the app entirely.
 */
export async function subscribeToPush() {
    const key = window.rgVapidKey;

    if (!key || !('serviceWorker' in navigator) || !('PushManager' in window)) {
        return { ok: false, reason: 'Not supported on this browser.' };
    }

    const permission = await Notification.requestPermission();

    if (permission !== 'granted') {
        return { ok: false, reason: 'Notifications were blocked.' };
    }

    const registration = await navigator.serviceWorker.ready;

    const subscription = await registration.pushManager.subscribe({
        userVisibleOnly: true,
        applicationServerKey: urlBase64ToUint8Array(key),
    });

    const json = subscription.toJSON();

    await window.axios.post('/push-subscriptions', {
        endpoint: subscription.endpoint,
        keys: json.keys,
    });

    return { ok: true };
}

document.addEventListener('alpine:init', () => {
    window.Alpine.data('pushToggle', () => ({
        busy: false,
        enabled: false,

        init() {
            this.enabled = window.Notification?.permission === 'granted';
        },

        async enable() {
            this.busy = true;

            const result = await subscribeToPush();

            this.busy = false;
            this.enabled = result.ok;

            window.Alpine.store('toasts').push({
                message: result.ok ? 'Push notifications on for this device.' : result.reason,
                tone: result.ok ? 'ok' : 'warn',
            });
        },
    }));
});
