/**
 * Theme switching.
 *
 * The `.dark` class is applied by an inline script in the document head before
 * first paint (see layouts/partials/theme-script.blade.php) so there is no flash
 * of the wrong theme. This module only handles changes made after boot.
 *
 * Three states are stored: 'light', 'dark', or absent (follow the OS).
 */
const STORAGE_KEY = 'rg.theme';

function systemPrefersDark() {
    return window.matchMedia('(prefers-color-scheme: dark)').matches;
}

function apply(mode) {
    const dark = mode === 'dark' || (mode === 'system' && systemPrefersDark());
    document.documentElement.classList.toggle('dark', dark);
    document.documentElement.style.colorScheme = dark ? 'dark' : 'light';
}

export function currentMode() {
    return localStorage.getItem(STORAGE_KEY) || 'system';
}

export function setMode(mode) {
    if (mode === 'system') {
        localStorage.removeItem(STORAGE_KEY);
    } else {
        localStorage.setItem(STORAGE_KEY, mode);
    }

    apply(mode);
}

// Follow the OS in real time, but only while the user has not chosen explicitly.
window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
    if (currentMode() === 'system') {
        apply('system');
    }
});

document.addEventListener('alpine:init', () => {
    window.Alpine.store('theme', {
        mode: currentMode(),

        get isDark() {
            return document.documentElement.classList.contains('dark');
        },

        set(mode) {
            this.mode = mode;
            setMode(mode);
        },

        toggle() {
            this.set(this.isDark ? 'light' : 'dark');
        },
    });
});
