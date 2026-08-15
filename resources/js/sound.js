/**
 * Notification tones.
 *
 * Synthesised with the Web Audio API rather than shipped as files: three short
 * tones cost nothing to download and never 404.
 *
 * Browsers block audio until the user has interacted with the page, so the
 * context is created lazily and resumed on the first real gesture. The
 * preference is explicit and remembered — an app that starts making noise
 * unasked gets muted at the OS level, permanently.
 */
const STORAGE_KEY = 'rg.sound';

let context = null;
let unlocked = false;

const TONES = {
    task: [660, 880],
    message: [520],
    expiry: [440, 350, 440],
};

export function soundEnabled() {
    return localStorage.getItem(STORAGE_KEY) === '1';
}

export function setSoundEnabled(on) {
    localStorage.setItem(STORAGE_KEY, on ? '1' : '0');

    if (on) unlock();
}

export function unlock() {
    if (unlocked) return;

    try {
        context = context ?? new (window.AudioContext || window.webkitAudioContext)();
        if (context.state === 'suspended') context.resume();
        unlocked = true;
    } catch {
        /* No audio available. Web push and the in-app badge still work. */
    }
}

export function play(name) {
    if (!soundEnabled()) return;

    unlock();

    if (!context) return;

    const notes = TONES[name] ?? TONES.message;
    const now = context.currentTime;

    notes.forEach((frequency, index) => {
        const oscillator = context.createOscillator();
        const gain = context.createGain();

        oscillator.type = 'sine';
        oscillator.frequency.value = frequency;

        const start = now + index * 0.11;

        // A short envelope: a square-edged tone clicks.
        gain.gain.setValueAtTime(0.0001, start);
        gain.gain.exponentialRampToValueAtTime(0.09, start + 0.015);
        gain.gain.exponentialRampToValueAtTime(0.0001, start + 0.1);

        oscillator.connect(gain).connect(context.destination);
        oscillator.start(start);
        oscillator.stop(start + 0.12);
    });
}

// The first gesture anywhere unlocks audio for the rest of the session.
['pointerdown', 'keydown'].forEach((event) => {
    window.addEventListener(event, () => soundEnabled() && unlock(), { once: true, passive: true });
});

document.addEventListener('livewire:init', () => {
    // Never for the user's own action — the server tags each payload with the
    // sound it wants, and only pushes them to other people.
    window.Echo?.private(`App.Models.User.${window.rgUserId}`)
        ?.notification((payload) => play(payload.sound ?? 'message'));
});
