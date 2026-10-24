document.addEventListener('alpine:init', () => {
    // Investasi calculator on the marketplace card: presets 1/10/30/50 or the slider.
    Alpine.data('investCalc', (qty, price, monthly, months) => ({
        qty,
        rp: (n) => 'Rp' + Math.round(n).toLocaleString('id-ID'),
        get modal() { return this.rp(this.qty * price); },
        get perMonth() { return this.rp(this.qty * monthly); },
        get perYear() { return this.rp(this.qty * monthly * months); },
        get total() { return this.rp(this.qty * (monthly * months + price)); },
        pick(n) { this.qty = n; },
    }));

    // Home hero: auto-advances every 6s, pauses on hover/focus, swipeable,
    // and never auto-advances for reduced-motion users or a single slide.
    Alpine.data('heroCarousel', (total) => ({
        slide: 0,
        paused: false,
        startX: null,
        init() {
            if (total < 2 || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
            setInterval(() => { if (! this.paused) this.next(); }, 6000);
        },
        next() { this.slide = (this.slide + 1) % total; },
        prev() { this.slide = (this.slide - 1 + total) % total; },
        touchStart(e) { this.startX = e.touches[0].clientX; },
        touchEnd(e) {
            if (this.startX === null) return;
            const dx = e.changedTouches[0].clientX - this.startX;
            this.startX = null;
            if (Math.abs(dx) >= 40) (dx < 0 ? this.next() : this.prev());
        },
    }));

    // Themed horizontal scroller (.hscroll): tracks whether there's more to
    // either side, which drives the edge fades and the ‹ › buttons, so no row
    // ever shows the browser's own scrollbar. The track is the x-ref "track".
    Alpine.data('hscroll', () => ({
        canLeft: false,
        canRight: false,
        init() {
            const track = this.$refs.track;
            const update = () => {
                this.canLeft = track.scrollLeft > 2;
                this.canRight = track.scrollLeft + track.clientWidth < track.scrollWidth - 2;
            };
            track.addEventListener('scroll', update, { passive: true });
            new ResizeObserver(update).observe(track);
            this.$nextTick(update);
        },
        go(dir) {
            const track = this.$refs.track;
            track.scrollBy({ left: dir * Math.max(track.clientWidth * 0.8, 160), behavior: 'smooth' });
        },
    }));

    // Header search (x-mp.header > SearchBox). ↑/↓ move a highlight over the
    // panel's .sbox-opt rows; Enter opens the highlighted row, otherwise the
    // form submits to the full results page as usual.
    Alpine.data('searchBox', () => ({
        open: false,
        active: -1,
        options() { return [...this.$root.querySelectorAll('.sbox-opt')]; },
        move(dir) {
            const rows = this.options();
            if (! rows.length) return;
            this.open = true;
            this.active = (this.active + dir + rows.length) % rows.length;
            rows.forEach((row, i) => row.classList.toggle('is-active', i === this.active));
            rows[this.active].scrollIntoView({ block: 'nearest' });
        },
        submit(event) {
            const row = this.options()[this.active];
            if (this.open && row) { event.preventDefault(); row.click(); }
        },
        close() { this.open = false; this.active = -1; this.$refs.input.blur(); },
    }));

    // Product page "Salin tautan". Falls back to a prompt the buyer can copy
    // from when the Clipboard API is missing or refused.
    Alpine.data('copyLink', (url) => ({
        copied: false,
        copy() {
            const done = () => { this.copied = true; setTimeout(() => (this.copied = false), 1600); };
            if (navigator.clipboard) {
                navigator.clipboard.writeText(url).then(done).catch(() => window.prompt('Salin tautan ini:', url));
            } else {
                window.prompt('Salin tautan ini:', url);
            }
        },
    }));
});
