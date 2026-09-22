/*
 * The test map, drawn by Leaflet.
 *
 * Leaflet has no notion of a data set that stops being drawn at a resolution,
 * nor of asking the map what a click landed on, so both are done here. What is
 * drawn, in which colours, and where the map opens comes from the same
 * description the MapLibre map is given.
 */

import {
    HIT_TOLERANCE,
    MARKS,
    MARK_RADIUS,
    MARK_WIDTH,
    boundsOf,
    buildToggles,
    fitHeight,
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

/* Leaflet stacks its own panes from 400; the data sets go above that. */
const PANE_BASE = 400;
const MARKS_PANE = "test-marks";
const MARKS_Z = 700;

/*
 * How far the wheel has to travel, in pixels, to move a whole zoom level.
 * Chosen to answer a trackpad at the rate MapLibre answers it, so that the
 * same gesture asks the same of both tabs.
 */
const WHEEL_PX_PER_LEVEL = 640;

/* What a wheel event's units are worth in pixels, per its deltaMode. */
const WHEEL_LINE = 40;

/* How long after the last turn of the wheel the map is settled for good. */
const WHEEL_SETTLE = 150;

/**
 * The wheel, read as it arrives.
 *
 * Leaflet's own handler waits for the wheel to settle, then makes a single
 * animated step of everything it gathered while waiting — which is why a
 * trackpad seems to drag the map along behind it, and why it takes so much
 * scrolling to get anywhere. MapLibre moves the map on every event instead.
 * Doing the same here means a gesture asks the same of both tabs, so what is
 * left to tell them apart is how quickly they draw the answer.
 *
 * The move is made the way a pinch makes it. Setting the view outright is the
 * obvious way to do it and the wrong one: every setting of it rebuilds the
 * tile grid and abandons the tiles still arriving, so a gesture made of fifty
 * events spends the whole time throwing away the background it is in the
 * middle of fetching — which is the grey. A pinch is the case Leaflet already
 * has for a zoom that arrives in many small pieces: the tiles and the drawn
 * features are stretched to suit, and only once the wheel stops is the view
 * reset and everything redrawn at its proper size.
 *
 * This reaches inside Leaflet to do it, as Leaflet's own pinch handler does.
 * The version is pinned, so what it reaches for will not move underneath it.
 */
function readWheel(map, element) {
    let settling = null;

    element.addEventListener(
        "wheel",
        (event) => {
            event.preventDefault();

            const pixels =
                event.deltaMode === 1
                    ? event.deltaY * WHEEL_LINE
                    : event.deltaMode === 2
                      ? event.deltaY * map.getSize().y
                      : event.deltaY;

            const zoom = map._limitZoom(
                map.getZoom() - pixels / WHEEL_PX_PER_LEVEL,
            );

            if (zoom === map.getZoom()) {
                return;
            }

            /* Whatever is under the pointer stays under the pointer. */
            const half = map.getSize().divideBy(2);
            const at = map.mouseEventToContainerPoint(event);
            const centre = map.containerPointToLatLng(
                half.add(
                    at
                        .subtract(half)
                        .multiplyBy(1 - 1 / map.getZoomScale(zoom)),
                ),
            );

            map._move(centre, zoom, { pinch: true, round: false });

            window.clearTimeout(settling);
            settling = window.setTimeout(
                () => map._resetView(map.getCenter(), map.getZoom()),
                WHEEL_SETTLE,
            );
        },
        { passive: false },
    );
}

export async function drawMap({ map: element, toggles, panel, spec: specUrl }) {
    const L = window.L;
    const readout = stats(panel, "Leaflet");

    const spec = await loadSpec(specUrl);
    const { longitude, latitude, resolution } = spec.view;

    const map = L.map(element, {
        /*
         * Thousands of circles as thousands of SVG elements is not a fair
         * showing for Leaflet: drawing them into a canvas is what it offers
         * for data of this size, so that is what it is given.
         */
        preferCanvas: true,
        /*
         * Fractional zoom, so that "two metres to the pixel" lands where it
         * says rather than at the nearest whole level. It is also what
         * MapLibre does, which keeps the two switching at the same moment.
         */
        zoomSnap: 0,
        /*
         * Leaflet gives up on animating a zoom of more than four levels and
         * jumps instead, and moving to a group is regularly six. Jumping is
         * what makes the movement hard to follow, so it is allowed to animate
         * as far as it ever has to go.
         */
        zoomAnimationThreshold: 10,
        /* The wheel is read below rather than by Leaflet's own handler. */
        scrollWheelZoom: false,
        center: [latitude, longitude],
        zoom: zoomForResolution(latitude, resolution),
    });

    readWheel(map, element);

    fitHeight(element, () => map.invalidateSize());

    L.tileLayer(spec.background.tiles, {
        maxZoom: spec.background.maxZoom,
        attribution: spec.background.attribution,
        /*
         * No fetching part way through a gesture. Crossing a whole zoom level
         * while the wheel is still turning otherwise sends Leaflet after a
         * fresh set of tiles it will have to replace again a moment later, and
         * until they arrive there is nothing to draw — the grey. Stretching
         * the tiles already in hand is soft for a moment; they are fetched
         * once, sharp, when the wheel stops.
         */
        updateWhenZooming: false,
        /*
         * A little more map than is on screen, so that what a gesture brings
         * into view has usually been fetched before it gets there.
         */
        keepBuffer: 4,
    }).addTo(map);

    map.createPane(MARKS_PANE).style.zIndex = MARKS_Z;

    const titles = Object.fromEntries(
        spec.layers.map((layer) => [layer.id, layer.title]),
    );

    /* Cleared and redrawn on every click, above everything else. */
    const marks = L.layerGroup([], { pane: MARKS_PANE }).addTo(map);

    const drawn = [];
    let cluster = null;

    /*
     * A click on a group is a move, not a selection, and the map is told about
     * it after the group's own handler has run. The flag is dropped again
     * before anything else can happen.
     */
    let handled = false;

    const handle = () => {
        handled = true;
        window.setTimeout(() => {
            handled = false;
        }, 0);
    };

    /* One canvas per data set, rather than one per feature drawn on it. */
    const renderers = {};

    function paneOf(layer) {
        const name = `test-data-${layer.zIndex}`;

        if (!map.getPane(name)) {
            map.createPane(name).style.zIndex = PANE_BASE + layer.zIndex;
        }

        return name;
    }

    function rendererOf(layer) {
        const pane = paneOf(layer);

        return (renderers[pane] ??= L.canvas({ pane }));
    }

    function styleOf(layer) {
        return {
            pane: paneOf(layer),
            radius: spec.point.radius,
            color: layer.outline,
            weight: spec.point.width,
            fillColor: layer.colour,
            fillOpacity: layer.fillOpacity,
        };
    }

    /** What a click landed on, in the order the popup lists it. */
    function hits(latlng, point) {
        const found = [];
        const limit = spec.featuresPerClick;

        const test = (child) => {
            if (found.length >= limit || !child.feature) {
                return;
            }

            if (typeof child.getLatLng === "function") {
                const at = map.latLngToContainerPoint(child.getLatLng());
                const reach = (child.options.radius ?? 0) + HIT_TOLERANCE;

                if (at.distanceTo(point) <= reach) {
                    found.push(child.feature);
                }

                return;
            }

            if (child.getBounds().contains(latlng) && covers(child, latlng)) {
                found.push(child.feature);
            }
        };

        /* Points before areas, so the popup reads from the top down. */
        for (const item of [...drawn].reverse()) {
            if (map.hasLayer(item.group)) {
                item.group.eachLayer(test);
            }
        }

        return found;
    }

    /**
     * The widget marks what was clicked by drawing it again, selected. These
     * features are drawn again here instead, on top of everything, which is
     * also the only way a point under an area can be seen to be marked at all.
     */
    function mark(features) {
        marks.clearLayers();

        return features.map((feature, position) => {
            const colour = MARKS[position % MARKS.length];

            L.geoJSON(feature, {
                pane: MARKS_PANE,
                pointToLayer: (ignored, at) =>
                    L.circleMarker(at, {
                        pane: MARKS_PANE,
                        radius: MARK_RADIUS,
                        color: colour,
                        weight: MARK_WIDTH,
                        fill: false,
                    }),
                style: () => ({
                    color: colour,
                    weight: MARK_WIDTH,
                    fill: false,
                }),
            }).addTo(marks);

            return colour;
        });
    }

    function select(features) {
        const colours = mark(features);
        const top = northernmost(features);

        if (top === null) {
            map.closePopup();

            return;
        }

        L.popup({ className: "test-map-popup", maxWidth: 620 })
            .setLatLng([top[1], top[0]])
            .setContent(
                popupHtml(
                    features.map((feature, position) => ({
                        title:
                            titles[feature.properties.dataset] ??
                            feature.properties.dataset,
                        colour: colours[position],
                        properties: feature.properties,
                    })),
                ),
            )
            .openOn(map);
    }

    /**
     * A group says how many points are under it but not where they are.
     * Clicking it moves to where they are, which is the only way to reach them
     * short of guessing at the zoom.
     */
    function zoomToGroup(markers) {
        const bounds = boundsOf(markers.map((marker) => marker.feature));

        if (bounds === null) {
            return;
        }

        const closest = zoomForResolution(
            latitude,
            spec.cluster.closestResolution,
        );
        const here = groundResolution(map.getCenter().lat, map.getZoom());

        /*
         * Flown rather than fitted. Fitting animates the zoom as a stretch of
         * the tiles it already has, over a fixed quarter of a second, which
         * arrives as a snap; flying works the view out frame by frame over
         * however long it is given, as the widget and MapLibre both do. A
         * group is near where the map already is, so the long way round that
         * flying is known for does not arise here.
         */
        map.flyToBounds(
            [
                [bounds[0][1], bounds[0][0]],
                [bounds[1][1], bounds[1][0]],
            ],
            {
                padding: [spec.cluster.padding, spec.cluster.padding],
                maxZoom: closest,
                duration:
                    travelTime(here, spec.cluster.closestResolution) / 1000,
            },
        );
    }

    /**
     * Far out the points pile up and are drawn as one grouped layer; close in
     * each data set is drawn on its own, where the shapes can be seen.
     */
    function refresh() {
        const here = groundResolution(map.getCenter().lat, map.getZoom());
        const grouped = here >= spec.cluster.untilResolution;

        for (const item of drawn) {
            const wanted = !grouped && item.on;

            if (wanted !== map.hasLayer(item.group)) {
                if (wanted) {
                    item.group.addTo(map);
                } else {
                    map.removeLayer(item.group);
                }
            }
        }

        if (cluster !== null && grouped !== map.hasLayer(cluster)) {
            if (grouped) {
                cluster.addTo(map);
            } else {
                map.removeLayer(cluster);
            }
        }
    }

    map.on("zoomend moveend", refresh);

    map.on("click", (event) => {
        if (handled) {
            return;
        }

        select(hits(event.latlng, map.latLngToContainerPoint(event.latlng)));
    });

    buildToggles(toggles, spec.layers, (layer, on) => {
        const item = drawn.find((candidate) => candidate.spec.id === layer.id);

        if (item) {
            item.on = on;
            refresh();
        }
    });

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

    const started = performance.now();

    spec.layers.forEach((layer, position) => {
        const collection = collections[position];
        features += collection.features.length;

        drawn.push({
            spec: layer,
            on: true,
            group: L.geoJSON(collection, {
                pane: paneOf(layer),
                renderer: rendererOf(layer),
                pointToLayer: (ignored, at) =>
                    L.circleMarker(at, styleOf(layer)),
                style: () => styleOf(layer),
            }),
        });
    });

    const coinciding = collections[collections.length - 1].features;

    const grouping = new Promise((resolve) => {
        if (coinciding.length === 0) {
            resolve();
        }

        cluster = L.markerClusterGroup({
            maxClusterRadius: spec.cluster.distance,
            /* The page zooms to the group itself, once it knows its extent. */
            zoomToBoundsOnClick: false,
            showCoverageOnHover: false,
            spiderfyOnMaxZoom: false,
            /* What Leaflet offers for grouping sets of this size. */
            chunkedLoading: true,
            chunkProgress: (processed, total) => {
                if (processed >= total) {
                    resolve();
                }
            },
            iconCreateFunction: (group) =>
                L.divIcon({
                    className: "test-map-cluster",
                    html: `<div class="test-map-cluster-marker" style="background:${spec.cluster.fill};border-color:${spec.cluster.outline}">${group.getChildCount()}</div>`,
                    iconSize: [
                        spec.cluster.radius * 2,
                        spec.cluster.radius * 2,
                    ],
                }),
        });

        cluster.on("clusterclick", (event) => {
            handle();
            zoomToGroup(event.layer.getAllChildMarkers());
        });

        cluster.on("click", (event) => {
            handle();
            select(event.layer.feature ? [event.layer.feature] : []);
        });

        cluster.addLayers(
            coinciding.map((feature) => {
                const marker = L.circleMarker(
                    [
                        feature.geometry.coordinates[1],
                        feature.geometry.coordinates[0],
                    ],
                    {
                        radius: spec.point.radius,
                        color: spec.point.outline,
                        weight: 1,
                        fillColor:
                            spec.layers.find(
                                (layer) =>
                                    layer.id === feature.properties.dataset,
                            )?.colour ?? spec.cluster.fill,
                        fillOpacity: 0.9,
                    },
                );
                marker.feature = feature;

                return marker;
            }),
        );
    });

    /* Reachable from the console, as the widget is on the Septima tab. */
    window.enterMap = map;

    refresh();
    await grouping;

    readout.loaded(features, fetched, performance.now() - started);
}

/**
 * Whether an area covers a point, counting the crossings of a ray cast east
 * from it: an odd number means it started inside. Rings within rings are holes
 * and cancel out, which falls out of the counting.
 */
function covers(polygon, latlng) {
    let inside = false;

    for (const ring of ringsOf(polygon.getLatLngs())) {
        for (let i = 0, j = ring.length - 1; i < ring.length; j = i++) {
            const a = ring[i];
            const b = ring[j];

            if (
                a.lat > latlng.lat !== b.lat > latlng.lat &&
                latlng.lng <
                    ((b.lng - a.lng) * (latlng.lat - a.lat)) / (b.lat - a.lat) +
                        a.lng
            ) {
                inside = !inside;
            }
        }
    }

    return inside;
}

function ringsOf(latlngs) {
    return Array.isArray(latlngs[0]) ? latlngs.flatMap(ringsOf) : [latlngs];
}

const where = target();

if (where !== null) {
    drawMap(where);
}
