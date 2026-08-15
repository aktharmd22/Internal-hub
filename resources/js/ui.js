/**
 * Alpine stores and behaviours shared by the app shell.
 *
 * Registered on `alpine:init` — Alpine ships inside Livewire 3, so it is never
 * imported directly here.
 */

const SIDEBAR_KEY = 'rg.sidebar';

document.addEventListener('alpine:init', () => {
    const Alpine = window.Alpine;

    /* ---------------------------------------------------------------- toasts */

    Alpine.store('toasts', {
        items: [],
        seq: 0,

        push({ message, tone = 'neutral', timeout = 4500 }) {
            const id = ++this.seq;
            this.items.push({ id, message, tone });

            if (timeout) {
                setTimeout(() => this.dismiss(id), timeout);
            }
        },

        dismiss(id) {
            this.items = this.items.filter((t) => t.id !== id);
        },
    });

    /* --------------------------------------------------------------- sidebar */

    Alpine.store('sidebar', {
        collapsed: localStorage.getItem(SIDEBAR_KEY) === '1',

        toggle() {
            this.collapsed = !this.collapsed;
            localStorage.setItem(SIDEBAR_KEY, this.collapsed ? '1' : '0');
        },
    });

    /* -------------------------------------------------- sticky page header */

    Alpine.data('stickyHeader', () => ({
        hidden: false,
        lastY: 0,

        init() {
            this.lastY = window.scrollY;

            window.addEventListener(
                'scroll',
                () => {
                    const y = window.scrollY;

                    // Ignore rubber-banding at the extremes and tiny jitter.
                    if (y < 72 || Math.abs(y - this.lastY) < 6) {
                        if (y < 72) this.hidden = false;
                        return;
                    }

                    this.hidden = y > this.lastY;
                    this.lastY = y;
                },
                { passive: true },
            );
        },
    }));

    /* ---------------------------------------------------------- swipe rows */

    /**
     * Swipe-to-reveal for list rows. `width` is the pixel width of the action
     * drawer sitting behind the row. Every action revealed this way must also
     * exist in the row's overflow menu — swiping is not reachable by keyboard
     * or screen reader.
     */
    Alpine.data('swipeRow', (width = 96) => ({
        offset: 0,
        open: false,
        startX: 0,
        startY: 0,
        tracking: false,
        locked: null,

        start(e) {
            if (e.pointerType === 'mouse') return;
            this.startX = e.clientX;
            this.startY = e.clientY;
            this.tracking = true;
            this.locked = null;
        },

        move(e) {
            if (!this.tracking) return;

            const dx = e.clientX - this.startX;
            const dy = e.clientY - this.startY;

            // Decide once whether this gesture is a swipe or a page scroll.
            if (this.locked === null) {
                if (Math.abs(dx) < 8 && Math.abs(dy) < 8) return;
                this.locked = Math.abs(dx) > Math.abs(dy) ? 'x' : 'y';
            }

            if (this.locked !== 'x') return;

            const base = this.open ? -width : 0;
            this.offset = Math.min(0, Math.max(-width, base + dx));
        },

        end() {
            if (!this.tracking) return;
            this.tracking = false;

            if (this.locked === 'x') {
                this.open = this.offset < -width / 2;
            }

            this.offset = this.open ? -width : 0;
            this.locked = null;
        },

        close() {
            this.open = false;
            this.offset = 0;
        },
    }));
});

/* ------------------------------------------------- server-driven toasts */

document.addEventListener('livewire:init', () => {
    window.Livewire.on('toast', (payload) => {
        const data = Array.isArray(payload) ? payload[0] : payload;
        window.Alpine.store('toasts').push(data ?? {});
    });
});
