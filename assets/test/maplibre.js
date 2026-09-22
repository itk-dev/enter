/*
 * The test map, drawn by MapLibre.
 *
 * Where Leaflet has to be told when to stop drawing a data set and when to
 * group it, MapLibre does both itself: a layer carries the zoom levels it is
 * drawn between, and a source groups its own points. What is left here is
 * saying which data sets there are and what a click means, from the same
 * description the Leaflet map is given.
 */

import {
    HIT_TOLERANCE,
    MARKS,
    MARK_RADIUS,
    MARK_WIDTH,
    boundsOf,
    buildToggles,
    easing,
    fitHeight,
    GL_WORLD,
    groundResolution,
    loadFeatures,
    loadSpec,
    northernmost,
    popupHtml,
    stats,
    target,
    travelTime,
    zoomForResolution,
} from "./map.js";

/*
 * A label is drawn from glyphs, which have to be fetched from somewhere;
 * MapLibre's own demo server is the one place they can be had without an
 * account. Only the counts on the groups are written, so nothing but they
 * depend on it.
 */
const GLYPHS = "https://demotiles.maplibre.org/font/{fontstack}/{range}.pbf";
const FONT = ["Noto Sans Regular"];

const CLUSTERS = "groups";
const COUNTS = "group-counts";
const LONE = "group-points";
const MARKED_AREAS = "marked-areas";
const MARKED_POINTS = "marked-points";

const EMPTY = { type: "FeatureCollection", features: [] };

/* How much map is left around a popup that had to be brought into view. */
const POPUP_MARGIN = 12;
const POPUP_PAN = 300;

export async function drawMap({ map: element, toggles, panel, spec: specUrl }) {
    const maplibregl = window.maplibregl;
    const readout = stats(panel, "MapLibre");

    const spec = await loadSpec(specUrl);
    const { longitude, latitude, resolution } = spec.view;

    /*
     * The same two resolutions the widget is given, said as zoom levels: a
     * layer is drawn between zooms rather than between metres, and the map
     * never leaves the latitude it opened at by enough to matter.
     */
    const switchZoom = zoomForResolution(
        latitude,
        spec.cluster.untilResolution,
        GL_WORLD,
    );
    const closestZoom = zoomForResolution(
        latitude,
        spec.cluster.closestResolution,
        GL_WORLD,
    );

    const map = new maplibregl.Map({
        container: element,
        style: {
            version: 8,
            glyphs: GLYPHS,
            sources: {
                background: {
                    type: "raster",
                    tiles: [spec.background.tiles],
                    tileSize: 256,
                    maxzoom: spec.background.maxZoom,
                    attribution: spec.background.attribution,
                },
            },
            layers: [
                { id: "background", type: "raster", source: "background" },
            ],
        },
        center: [longitude, latitude],
        zoom: zoomForResolution(latitude, resolution, GL_WORLD),
    });

    fitHeight(element, () => map.resize());

    const titles = Object.fromEntries(
        spec.layers.map((layer) => [layer.id, layer.title]),
    );

    /* Which layers belong to which data set, for the toggles to switch. */
    const belonging = {};

    const popup = new maplibregl.Popup({
        className: "test-map-popup",
        anchor: "bottom",
        maxWidth: "620px",
        closeButton: true,
        /*
         * Closing on a click is this popup's own doing, not MapLibre's. Left
         * to MapLibre, opening it registers a listener that closes it on the
         * next click of the map — and the next click of the map is how the
         * next popup is asked for. Ours runs first, opens the popup, and that
         * older listener then closes the very popup it just opened, leaving a
         * marked shape and nothing to read. Clicking nothing still closes it:
         * that is the empty selection below.
         */
        closeOnClick: false,
    });

    function box(point) {
        return [
            [point.x - HIT_TOLERANCE, point.y - HIT_TOLERANCE],
            [point.x + HIT_TOLERANCE, point.y + HIT_TOLERANCE],
        ];
    }

    /**
     * What a click landed on, once over.
     *
     * A shape drawn by both a fill and an outline is hit twice, and the popup
     * has no business saying the same thing twice. Points come first, as they
     * are what sits on top.
     */
    function pick(hits) {
        const seen = new Set();
        const found = [];

        for (const hit of hits) {
            const id = hit.properties?.id ?? JSON.stringify(hit.geometry);

            if (!seen.has(id)) {
                seen.add(id);
                found.push({
                    type: "Feature",
                    geometry: hit.geometry,
                    properties: hit.properties,
                });
            }
        }

        found.sort(
            (a, b) =>
                Number(b.geometry.type === "Point") -
                Number(a.geometry.type === "Point"),
        );

        return found.slice(0, spec.featuresPerClick);
    }

    /**
     * Whatever a click landed on, drawn again on top of everything in a colour
     * of its own — which is also the only way a point under an area can be seen
     * to be marked at all.
     */
    function select(features, at) {
        const marked = features.map((feature, position) => ({
            ...feature,
            properties: {
                ...feature.properties,
                _mark: MARKS[position % MARKS.length],
            },
        }));

        map.getSource("marks").setData({
            type: "FeatureCollection",
            features: marked,
        });

        if (features.length === 0) {
            popup.remove();

            return;
        }

        /*
         * A click hands back the geometry as the tile holds it, which for a
         * shape crossing a tile edge can be the part of it that is not here.
         * Falling back on where the click landed is better than dropping a
         * popup we have the attributes for.
         */
        const top = northernmost(features) ?? [at.lng, at.lat];

        popup
            .setLngLat(top)
            .setHTML(
                popupHtml(
                    marked.map((feature) => ({
                        title:
                            titles[feature.properties.dataset] ??
                            feature.properties.dataset,
                        colour: feature.properties._mark,
                        properties: feature.properties,
                    })),
                ),
            )
            .addTo(map);

        window.requestAnimationFrame(panPopupIntoView);
    }

    /**
     * The map moved until the popup is on it.
     *
     * The popup hangs above the northernmost corner of what was clicked, which
     * for a shape near the top of the screen puts it off the map entirely —
     * indistinguishable, to the reader, from having clicked nothing at all.
     * Leaflet and the widget both move the map to bring their popup into view;
     * MapLibre leaves it where it falls, so it is moved here.
     */
    function panPopupIntoView() {
        const node = popup.getElement();

        if (!node) {
            return;
        }

        const canvas = map.getCanvas().getBoundingClientRect();
        const box = node.getBoundingClientRect();

        const over = (low, high, edge, extent) => {
            if (low < edge + POPUP_MARGIN) {
                return low - edge - POPUP_MARGIN;
            }

            return high > edge + extent - POPUP_MARGIN
                ? high - edge - extent + POPUP_MARGIN
                : 0;
        };

        const x = over(box.left, box.right, canvas.left, canvas.width);
        const y = over(box.top, box.bottom, canvas.top, canvas.height);

        if (x !== 0 || y !== 0) {
            map.panBy([x, y], { duration: POPUP_PAN, easing });
        }
    }

    /**
     * A group says how many points are under it but not where they are.
     * Clicking it moves to where they are, which is the only way to reach them
     * short of guessing at the zoom.
     */
    async function zoomToGroup(group) {
        const leaves = await map
            .getSource(spec.cluster.id)
            .getClusterLeaves(group.properties.cluster_id, Infinity, 0);

        const bounds = boundsOf(leaves);

        if (bounds === null) {
            return;
        }

        map.fitBounds(bounds, {
            padding: spec.cluster.padding,
            maxZoom: closestZoom,
            duration: travelTime(
                groundResolution(map.getCenter().lat, map.getZoom(), GL_WORLD),
                spec.cluster.closestResolution,
            ),
            easing,
        });
    }

    const ready = new Promise((resolve) => map.on("load", resolve));

    let fetched = 0;
    let features = 0;

    const collections = await Promise.all(
        [...spec.layers.map((layer) => layer.url), spec.cluster.url].map(
            async (url) => {
                const loaded = await loadFeatures(url);
                fetched = Math.max(fetched, loaded.milliseconds);

                return loaded.collection;
            },
        ),
    );

    await ready;

    const started = performance.now();

    spec.layers.forEach((layer, position) => {
        const collection = collections[position];
        features += collection.features.length;

        map.addSource(layer.id, { type: "geojson", data: collection });

        belonging[layer.id] = [];

        /* Drawn from where the groups leave off and inwards. */
        const shared = { source: layer.id, minzoom: switchZoom };

        if (layer.areas) {
            map.addLayer({
                ...shared,
                id: `${layer.id}-fill`,
                type: "fill",
                filter: ["==", ["geometry-type"], "Polygon"],
                paint: {
                    "fill-color": layer.colour,
                    "fill-opacity": layer.fillOpacity,
                },
            });
            map.addLayer({
                ...shared,
                id: `${layer.id}-line`,
                type: "line",
                filter: ["==", ["geometry-type"], "Polygon"],
                paint: {
                    "line-color": layer.outline,
                    "line-width": spec.point.width,
                },
            });

            belonging[layer.id].push(`${layer.id}-fill`, `${layer.id}-line`);
        }

        /*
         * A source that brings areas may bring single bays alongside them, so
         * every data set is given somewhere to draw points.
         */
        map.addLayer({
            ...shared,
            id: `${layer.id}-circle`,
            type: "circle",
            filter: ["==", ["geometry-type"], "Point"],
            paint: {
                "circle-color": layer.colour,
                "circle-opacity": layer.fillOpacity,
                "circle-radius": spec.point.radius,
                "circle-stroke-color": layer.outline,
                "circle-stroke-width": spec.point.width,
            },
        });

        belonging[layer.id].push(`${layer.id}-circle`);
    });

    const coinciding = collections[collections.length - 1];

    map.addSource(spec.cluster.id, {
        type: "geojson",
        data: coinciding,
        cluster: true,
        clusterRadius: spec.cluster.distance,
        clusterMaxZoom: Math.ceil(switchZoom),
    });

    /* The grouped view is what is drawn while the map is too far out. */
    const grouped = { source: spec.cluster.id, maxzoom: switchZoom };

    map.addLayer({
        ...grouped,
        id: LONE,
        type: "circle",
        filter: ["!", ["has", "point_count"]],
        paint: {
            "circle-color": [
                "match",
                ["get", "dataset"],
                ...spec.layers.flatMap((layer) => [layer.id, layer.colour]),
                spec.cluster.fill,
            ],
            "circle-opacity": 0.9,
            "circle-radius": spec.point.radius,
            "circle-stroke-color": spec.point.outline,
            "circle-stroke-width": 1,
        },
    });

    map.addLayer({
        ...grouped,
        id: CLUSTERS,
        type: "circle",
        filter: ["has", "point_count"],
        paint: {
            /*
             * A group can hold more than one data set, so it is drawn in
             * neither of their colours rather than in a colour that would
             * claim it belongs to one of them.
             */
            "circle-color": spec.cluster.fill,
            "circle-opacity": 0.95,
            "circle-radius": spec.cluster.radius,
            "circle-stroke-color": spec.cluster.outline,
            "circle-stroke-width": spec.point.width,
        },
    });

    map.addLayer({
        ...grouped,
        id: COUNTS,
        type: "symbol",
        filter: ["has", "point_count"],
        layout: {
            "text-field": ["get", "point_count_abbreviated"],
            "text-font": FONT,
            "text-size": 12,
            "text-allow-overlap": true,
        },
        paint: { "text-color": "#ffffff" },
    });

    map.addSource("marks", { type: "geojson", data: EMPTY });

    map.addLayer({
        id: MARKED_AREAS,
        source: "marks",
        type: "line",
        filter: ["==", ["geometry-type"], "Polygon"],
        paint: { "line-color": ["get", "_mark"], "line-width": MARK_WIDTH },
    });

    map.addLayer({
        id: MARKED_POINTS,
        source: "marks",
        type: "circle",
        filter: ["==", ["geometry-type"], "Point"],
        paint: {
            "circle-color": "rgba(0, 0, 0, 0)",
            "circle-radius": MARK_RADIUS,
            "circle-stroke-color": ["get", "_mark"],
            "circle-stroke-width": MARK_WIDTH,
        },
    });

    const clickable = [LONE, ...Object.values(belonging).flat()].filter(
        (id) => !id.endsWith("-line"),
    );

    map.on("click", async (event) => {
        const groups = map.queryRenderedFeatures(box(event.point), {
            layers: [CLUSTERS],
        });

        /*
         * A group is a move, not a selection: what it stands for is only worth
         * marking once the map has arrived at it.
         */
        if (groups.length > 0) {
            await zoomToGroup(groups[0]);

            return;
        }

        select(
            pick(
                map.queryRenderedFeatures(box(event.point), {
                    layers: clickable,
                }),
            ),
            event.lngLat,
        );
    });

    map.on("mouseenter", CLUSTERS, () => {
        map.getCanvas().style.cursor = "pointer";
    });

    map.on("mouseleave", CLUSTERS, () => {
        map.getCanvas().style.cursor = "";
    });

    buildToggles(toggles, spec.layers, (layer, on) => {
        for (const id of belonging[layer.id]) {
            map.setLayoutProperty(id, "visibility", on ? "visible" : "none");
        }
    });

    /* Reachable from the console, as the widget is on the Septima tab. */
    window.enterMap = map;

    map.once("idle", () => {
        readout.loaded(features, fetched, performance.now() - started);
    });
}

const where = target();

if (where !== null) {
    drawMap(where);
}
