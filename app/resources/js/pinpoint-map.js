import { L, JAKARTA, addBaseLayer, forceResize } from './map-base';
import { registerAddressFromPin } from './address-from-pin';

document.addEventListener('alpine:init', () => {
    Alpine.data('pinpointPicker', (initialLat, initialLng, wireLatModel, wireLngModel) => ({
        lat: initialLat,
        lng: initialLng,
        map: null,
        marker: null,
        circle: null,
        primaryLayer: null,

        init() {
            this.$nextTick(() => this.initMap());
        },

        initMap() {
            // Guards a double-init if this component's DOM is ever
            // re-mounted (e.g. re-opening a dialog) without a full page reload.
            if (this.map) {
                this.map.remove();
                this.map = null;
            }

            const hasPoint = this.lat && this.lng;
            const center = hasPoint ? [this.lat, this.lng] : JAKARTA;

            this.map = L.map(this.$refs.pinpointMapEl).setView(center, hasPoint ? 16 : 11);
            this.primaryLayer = addBaseLayer(this.map);

            this.marker = L.marker(center, { draggable: true }).addTo(this.map);
            this.marker.on('dragend', () => this.syncFromMarker());
            this.map.on('click', (e) => {
                this.marker.setLatLng(e.latlng);
                this.syncFromMarker();
            });

            if (hasPoint) {
                this.syncFromMarker();
            }

            if (this.pendingRadius) this.setRadius(this.pendingRadius);
            forceResize(this.map, this.primaryLayer);
        },

        syncFromMarker() {
            const pos = this.marker.getLatLng();
            this.circle?.setLatLng(pos);
            // A geofence of a few hundred metres is invisible at city zoom — get close enough to see it.
            if (this.circle && this.map.getZoom() < 15) this.map.setView(pos, 16);
            this.lat = pos.lat;
            this.lng = pos.lng;
            // Only write real moves: re-setting the loaded pin would mark an untouched form as changed (x-ui.save-button).
            const same = (a, b) => a !== null && a !== undefined && Math.abs(Number(a) - b) < 1e-7;
            if (!same(this.$wire.get(wireLatModel), this.lat)) this.$wire.set(wireLatModel, this.lat, false);
            if (!same(this.$wire.get(wireLngModel), this.lng)) this.$wire.set(wireLngModel, this.lng, false);
            window.dispatchEvent(new CustomEvent('pinpoint-moved', { detail: { lat: this.lat, lng: this.lng } }));
        },

        /** Optional geofence preview (Pengaturan Absensi): a circle of `metres` around the pin. */
        setRadius(metres) {
            if (!this.map) { this.pendingRadius = metres; return; }
            if (!metres) return;
            if (this.circle) {
                this.circle.setRadius(metres);
            } else {
                this.circle = L.circle(this.marker.getLatLng(), { radius: metres, color: '#F0B90B', weight: 2, fillOpacity: 0.12 }).addTo(this.map);
                if (this.lat && this.lng) this.map.fitBounds(this.circle.getBounds(), { padding: [24, 24], maxZoom: 18, animate: false });
            }
        },

        /** Called externally ("Gunakan lokasi saya") to put the pin at a known position and save it, even over an existing pin. */
        placeAt(lat, lng) {
            if (!this.map) return;
            this.marker.setLatLng([lat, lng]);
            this.map.setView([lat, lng], 17);
            this.syncFromMarker();
        },

        /** Called externally (e.g. after the address region cascade resolves a village) to recenter without moving an already-placed pin. */
        recenter(lat, lng, zoom = 15) {
            if (!this.map || (this.lat && this.lng)) return;
            this.map.setView([lat, lng], zoom);
        },

        /** Call when the container becomes visible after being hidden (dialog/tab open) — Leaflet miscomputes tile layout on a 0x0 container. */
        onShow() {
            forceResize(this.map, this.primaryLayer);
        },
    }));

    registerAddressFromPin(Alpine);

    Alpine.data('areaCirclesMap', (areas, selectedAreaId) => ({
        selectedAreaId,
        map: null,
        primaryLayer: null,
        circles: {},

        init() {
            this.$nextTick(() => this.initMap());
            this.$watch('selectedAreaId', () => this.restyleCircles());
        },

        initMap() {
            if (this.map) {
                this.map.remove();
                this.map = null;
            }

            if (areas.length === 0) {
                return;
            }

            // A bare L.map() has no projection/CRS transform established
            // until it has an initial view — computing circle bounds (for
            // fitBounds below) before that crashes. setView first, adjust after.
            this.map = L.map(this.$refs.areaMapEl).setView([areas[0].lat, areas[0].lng], 11);
            this.primaryLayer = addBaseLayer(this.map);

            areas.forEach((area) => {
                const circle = L.circle([area.lat, area.lng], {
                    radius: area.radiusKm * 1000,
                    ...this.styleFor(area),
                }).addTo(this.map);

                circle.bindTooltip(area.takenBy ? `${area.name} — ${area.takenBy}` : `${area.name} — kosong`);
                this.circles[area.id] = circle;
            });

            this.map.fitBounds(L.featureGroup(Object.values(this.circles)).getBounds(), { padding: [16, 16], animate: false });

            forceResize(this.map, this.primaryLayer);
        },

        styleFor(area) {
            if (area.id === this.selectedAreaId) {
                return { color: '#2563EB', fillColor: '#2563EB', fillOpacity: 0.35, weight: 3 };
            }

            return area.takenBy
                ? { color: '#C1603A', fillColor: '#C1603A', fillOpacity: 0.15, weight: 1 }
                : { color: '#16A34A', fillColor: '#16A34A', fillOpacity: 0.15, weight: 1 };
        },

        restyleCircles() {
            areas.forEach((area) => this.circles[area.id]?.setStyle(this.styleFor(area)));
        },
    }));

    // Admin "Perjalanan sales": every shift of the day on one map, each sales person in their own colour — route, where
    // they checked in, the stores in order, the last position. Redrawn in place on every poll; the view only moves when
    // the focus changes (a sales card tapped, or back to everyone).
    Alpine.data('trackingMap', (initial) => ({
        map: null,
        layer: null,
        focus: undefined,
        init() {
            this.$nextTick(() => {
                this.map = L.map(this.$refs.mapEl).setView(JAKARTA, 12);
                forceResize(this.map, addBaseLayer(this.map));
                this.layer = L.layerGroup().addTo(this.map);
                this.draw(initial);
                // on phones the map moves into a full-screen sheet when a sales person is picked
                new ResizeObserver(() => this.map.invalidateSize()).observe(this.$refs.mapEl);
            });
        },
        draw(d) {
            if (!this.map || !d) return;
            this.layer.clearLayers();
            const all = [];
            const mine = [];
            d.shifts.forEach((s) => {
                const dim = d.focus !== null && d.focus !== s.id;
                const fit = d.focus === s.id ? mine : all;
                const op = dim ? 0.25 : 1;
                if (s.path.length > 1) {
                    L.polyline(s.path, { color: s.color, weight: dim ? 3 : 4, opacity: 0.85 * op }).bindTooltip(s.name).addTo(this.layer);
                    fit.push(...s.path);
                }
                if (s.start) {
                    L.circleMarker(s.start, { radius: 7, color: s.color, weight: 3, fillColor: '#fff', fillOpacity: op, opacity: op }).bindTooltip(`${s.name} · absen masuk`).addTo(this.layer);
                    fit.push(s.start);
                }
                s.stores.forEach((st, i) => {
                    L.marker(st.at, { opacity: op, icon: L.divIcon({ className: '', html: `<span class="tracking-pin${st.closed ? ' is-closed' : ''}" style="background:${st.closed ? '' : s.color}">${i + 1}</span>`, iconSize: [24, 24], iconAnchor: [12, 12] }) })
                        .bindTooltip(`${s.name} · ${i + 1}. ${st.name} · ${st.time}${st.closed ? ' (tutup)' : ''}`).addTo(this.layer);
                    fit.push(st.at);
                });
                if (s.last) {
                    L.circleMarker(s.last.at, { radius: 9, color: '#fff', weight: 3, fillColor: s.open ? s.color : '#6B7280', fillOpacity: op, opacity: op })
                        .bindTooltip(`${s.name} · ${s.open ? 'posisi terakhir' : 'posisi saat pulang'} ${s.last.time}`, { permanent: s.open && !dim, direction: 'top', offset: [0, -8] }).addTo(this.layer);
                    fit.push(s.last.at);
                }
            });
            const target = d.focus !== null && mine.length ? mine : all.concat(mine);
            if (target.length && this.focus !== d.focus) this.map.fitBounds(L.latLngBounds(target), { padding: [30, 30], maxZoom: 16, animate: false });
            this.focus = d.focus;
        },
    }));

    // Admin "Area sales": each sales person's circle and store pins in their own colour (Admin\Areas\Index::COLORS).
    Alpine.data('salesAreasMap', (groups) => ({
        init() {
            this.$nextTick(() => {
                const map = L.map(this.$refs.mapEl).setView(JAKARTA, 10);
                const base = addBaseLayer(map);
                const layers = [];
                groups.forEach((g) => {
                    if (g.lat !== null && g.radiusKm) {
                        layers.push(L.circle([g.lat, g.lng], { radius: g.radiusKm * 1000, color: g.color, fillColor: g.color, fillOpacity: 0.08, weight: 2 })
                            .bindTooltip(`${g.area ?? ''} — ${g.name}`).addTo(map));
                    }
                    g.stores.forEach((s) => layers.push(L.circleMarker([s.lat, s.lng], { radius: 7, color: '#fff', weight: 2, fillColor: g.color, fillOpacity: 1 })
                        .bindTooltip(`${s.name} · ${g.name}`).addTo(map)));
                });
                if (layers.length) map.fitBounds(L.featureGroup(layers).getBounds(), { padding: [24, 24], maxZoom: 15, animate: false });
                forceResize(map, base);
            });
        },
    }));

    // Sales "Toko" tab: the user's own pinned stores, their area, and other
    // sales users' areas (never their stores — the server doesn't send them).
    Alpine.data('salesStoreMap', ({ stores, areas }) => ({
        map: null,

        init() {
            this.$nextTick(() => this.initMap());
        },

        initMap() {
            const first = stores[0] ?? areas.find((a) => a.own) ?? areas[0];
            this.map = L.map(this.$refs.mapEl).setView([first.lat, first.lng], 12);
            const primaryLayer = addBaseLayer(this.map);
            const focus = [];

            areas.forEach((area) => {
                const circle = L.circle([area.lat, area.lng], {
                    radius: area.radiusKm * 1000,
                    ...(area.own
                        ? { color: '#F0B90B', fillColor: '#F0B90B', fillOpacity: 0.15, weight: 2 }
                        : { color: '#C4C8CF', fillColor: '#C4C8CF', fillOpacity: 0.06, weight: 1, dashArray: '4 4' }),
                }).addTo(this.map);
                circle.bindTooltip(area.sales ? `${area.name} — ${area.sales}` : area.name);
                if (area.own) focus.push(circle);
            });

            stores.forEach((store) => {
                // Store names come from buyers: build the popup with textContent, never HTML.
                const popup = document.createElement('div');
                const name = document.createElement('b');
                name.textContent = store.name;
                const link = document.createElement('a');
                link.href = store.url;
                link.textContent = 'Buka toko';
                popup.append(name, document.createElement('br'), link);

                focus.push(L.circleMarker([store.lat, store.lng], {
                    radius: 7, color: '#FFFFFF', weight: 2, fillColor: '#F0B90B', fillOpacity: 1,
                }).bindPopup(popup).addTo(this.map));
            });

            // Frame the user's own territory; fall back to everything when they have neither area nor pins.
            const bounds = L.featureGroup(focus.length ? focus : Object.values(this.map._layers).filter((l) => l instanceof L.Circle)).getBounds();
            if (bounds.isValid()) this.map.fitBounds(bounds, { padding: [24, 24], maxZoom: 15, animate: false });

            forceResize(this.map, primaryLayer);
        },
    }));

    Alpine.data('routeMap', (destLat, destLng) => ({
        map: null,
        primaryLayer: null,
        loading: true,

        init() {
            this.$nextTick(() => this.initMap());
        },

        initMap() {
            if (this.map) {
                this.map.remove();
                this.map = null;
            }

            this.map = L.map(this.$refs.routeMapEl).setView([destLat, destLng], 12);
            this.primaryLayer = addBaseLayer(this.map);

            L.marker([destLat, destLng]).addTo(this.map).bindTooltip('Tujuan');

            this.locateAndRequestRoute();
            forceResize(this.map, this.primaryLayer);
        },

        locateAndRequestRoute() {
            if (!navigator.geolocation) {
                this.requestRoute(null, null);
                return;
            }

            navigator.geolocation.getCurrentPosition(
                (pos) => this.requestRoute(pos.coords.latitude, pos.coords.longitude),
                () => this.requestRoute(null, null),
                { timeout: 8000 },
            );
        },

        async requestRoute(originLat, originLng) {
            await this.$wire.fetchRoute(originLat, originLng);
            this.loading = false;

            const points = this.$wire.routePoints;

            if (!points || points.length === 0) {
                return;
            }

            L.polyline(points, { color: '#2563EB', weight: 4 }).addTo(this.map);
            this.map.fitBounds(L.latLngBounds(points), { padding: [24, 24], animate: false });
        },
    }));
});
