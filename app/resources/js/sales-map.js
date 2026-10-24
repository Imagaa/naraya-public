// The sales app's maps (the "Toko baru" pin, the Rute map), loaded on demand by sales.js, so pages without a map
// stay light; same Fiord look as every other map (map-base.js).
import { L, JAKARTA, addBaseLayer, forceResize } from './map-base';

/** A draggable pin; onMove(lat, lng) on every real move. */
export function pinpointMap(el, lat, lng, onMove) {
    const has = lat && lng;
    const start = has ? [lat, lng] : JAKARTA;
    const map = L.map(el, { zoomControl: true }).setView(start, has ? 16 : 11);
    const base = addBaseLayer(map);
    const marker = L.marker(start, { draggable: true }).addTo(map);
    const moved = () => { const p = marker.getLatLng(); onMove(p.lat, p.lng); };
    marker.on('dragend', moved);
    map.on('click', (e) => { marker.setLatLng(e.latlng); moved(); });
    forceResize(map, base);

    return {
        map,
        placeAt(lat, lng) {
            marker.setLatLng([lat, lng]);
            map.setView([lat, lng], 17);
            moved();
        },
    };
}

/** Stores on the Rute page; with `path`, numbered in order and joined by the road (OSRM), dashed until it loads. */
export function routeMap(el, points, path) {
    const map = L.map(el).setView([points[0].lat, points[0].lng], 13);
    const base = addBaseLayer(map);
    const latlngs = points.map((p) => [p.lat, p.lng]);
    const markers = new Map(); // id => marker, so a tick can re-colour one pin without rebuilding the map
    points.forEach((p, i) => {
        const icon = L.divIcon({ className: 'route-pin' + (p.on ? ' is-on' : ''), html: p.no != null ? String(p.no) : (path ? String(i + 1) : ''), iconSize: [26, 26] });
        const label = document.createElement('span'); // store names come from buyers: text, never HTML
        label.textContent = p.name;
        markers.set(p.id, L.marker([p.lat, p.lng], { icon }).addTo(map).bindTooltip(label));
    });
    map.fitBounds(L.latLngBounds(latlngs), { padding: [28, 28], maxZoom: 15, animate: false });
    forceResize(map, base);

    if (path && latlngs.length > 1) {
        const line = L.polyline(latlngs, { color: '#2563EB', weight: 4, dashArray: '6 6' }).addTo(map);
        const coords = latlngs.map(([lat, lng]) => `${lng},${lat}`).join(';');
        fetch(`https://router.project-osrm.org/route/v1/driving/${coords}?overview=full&geometries=geojson`)
            .then((r) => r.json())
            .then((json) => {
                const road = json.routes?.[0]?.geometry?.coordinates;
                if (road) line.setLatLngs(road.map(([lng, lat]) => [lat, lng])).setStyle({ dashArray: null });
            })
            .catch(() => {}); // offline: the dashed straight line stays
    }

    return {
        map,
        /** Highlight exactly these store ids (the ticked ones). */
        mark(ids) {
            const on = new Set(ids.map(Number));
            markers.forEach((m, id) => m.getElement()?.classList.toggle('is-on', on.has(id)));
        },
    };
}
