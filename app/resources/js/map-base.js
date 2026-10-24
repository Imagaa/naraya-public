// The look every map shares — OpenFreeMap (Fiord) through MapLibre, OSM raster as fallback — used by the
// full map bundle (marketplace, admin) and, loaded on demand, by the sales app's maps (sales-map.js).
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import 'maplibre-gl/dist/maplibre-gl.css';
import { setWorkerUrl } from 'maplibre-gl';
import '@maplibre/maplibre-gl-leaflet';

// Leaflet's default marker icon references image URLs that don't survive
// Vite bundling — build our own from the bundled asset URLs instead of the
// package's baked-in relative paths.
import markerIcon2x from 'leaflet/dist/images/marker-icon-2x.png';
import markerIcon from 'leaflet/dist/images/marker-icon.png';
import markerShadow from 'leaflet/dist/images/marker-shadow.png';

// MapLibre GL resolves its worker via a dynamic import.meta.url pattern Vite's
// static analysis can't see, so the worker chunk is never emitted and the
// map silently renders blank (canvas exists, style/sprites load fine, but no
// tiles — the exact symptom this fixes). Importing it with ?worker&url routes
// it through Vite's worker pipeline instead, bundled with its dependencies.
import maplibreWorkerUrl from 'maplibre-gl/dist/maplibre-gl-worker.mjs?worker&url';
setWorkerUrl(maplibreWorkerUrl);

L.Icon.Default.mergeOptions({
    iconRetinaUrl: markerIcon2x,
    iconUrl: markerIcon,
    shadowUrl: markerShadow,
});

export const JAKARTA = [-6.2088, 106.8456];

/**
 * OpenFreeMap (Fiord) as the primary layer, falling back to plain OSM raster
 * tiles if the style/tiles fail to load (network hiccup, OpenFreeMap
 * outage) — shared by every map on the site so none of them silently
 * render blank.
 */
export function addBaseLayer(map) {
    // padding: 0 — the plugin's default (0.1) deliberately over-renders the
    // canvas by 20% and offsets it via a negative transform, as a pan-ahead
    // buffer. On these mostly-static reference maps that's not worth the
    // tradeoff: the extra canvas area doesn't reliably get repainted after
    // Leaflet's own layout settles, leaving an unpainted (background-
    // colored) strip along the container's edge. padding: 0 sizes the
    // canvas to exactly match the container — simpler, no gaps.
    const primary = L.maplibreGL({ style: 'https://tiles.openfreemap.org/styles/fiord', padding: 0 });

    let fellBack = false;
    primary.on('add', () => {
        primary.getMaplibreMap().on('error', () => {
            if (fellBack) return;
            fellBack = true;
            map.removeLayer(primary);
            L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap contributors',
                maxZoom: 19,
            }).addTo(map);
        });
    });

    primary.addTo(map);

    return primary;
}

/**
 * Nudges MapLibre to match its container's actual size once layout has
 * settled. A delayed second pass previously landed mid-flight during
 * Leaflet's fitBounds() zoom animation and threw ("_latLngToNewLayerPoint"
 * on null) — a single rAF pass avoids that; fitBounds is called with
 * animate:false wherever it's used, so there's no animation window left
 * for this to race against.
 */
export function forceResize(map, primaryLayer) {
    requestAnimationFrame(() => {
        map.invalidateSize();
        primaryLayer.getMaplibreMap()?.resize();
    });
}

export { L };
