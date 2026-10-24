// Shared by the full map bundle (marketplace, admin) and the light sales bundle.
export function registerAddressFromPin(Alpine) {
    // Fills an address field from the pin (OpenStreetMap Nominatim reverse geocoding, ~1 request a
    // second at most by its usage policy, hence the debounce) until the user types in it themselves.
    Alpine.data('addressFromPin', (wireModel) => ({
        touched: false,
        busy: false,
        timer: null,
        moved(lat, lng) {
            if (this.touched || !lat || !lng) return;
            clearTimeout(this.timer);
            this.timer = setTimeout(() => this.lookup(lat, lng), 800);
        },
        async lookup(lat, lng) {
            this.busy = true;
            try {
                const res = await fetch(`https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${lat}&lon=${lng}&zoom=18&accept-language=id`);
                const place = await res.json();
                if (!this.touched && place.display_name) this.$wire.set(wireModel, place.display_name.replace(/, Indonesia$/, ''), false);
            } catch (e) {
                // offline or rate-limited: the field stays editable by hand
            } finally {
                this.busy = false;
            }
        },
    }));
}
