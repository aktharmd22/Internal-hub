{{-- Runs before first paint so the page never flashes the wrong theme. --}}
<script>
    (function () {
        try {
            var stored = localStorage.getItem('rg.theme');
            var dark = stored === 'dark'
                || (stored === null && window.matchMedia('(prefers-color-scheme: dark)').matches);

            document.documentElement.classList.toggle('dark', dark);
            document.documentElement.style.colorScheme = dark ? 'dark' : 'light';
        } catch (e) {
            /* Private mode with storage disabled: fall through to light. */
        }
    })();
</script>
