/*
 * What the map needs that is not MapLibre itself: where its description is to
 * be had, what a popup says, how long a movement takes, how the toggles look.
 */

const EQUATOR = 40075016.686;

/* How wide the world is at zoom 0, in pixels: MapLibre cuts its tiles at 512. */
const WORLD = 512;

/*
 * The markings drawn around whatever a click landed on, one colour each so a
 * popup listing several says which is which. Bright ones: they are drawn over
 * a map of greys and greens and over the data sets' own muted reds and blues,
 * and have to be told from both. None of them is a data set's own colour, or
 * the marking would look like the thing it is marking.
 */
export const MARKS = [
    "#ff6d00",
    "#00e5ff",
    "#76ff03",
    "#d500f9",
    "#ffea00",
    "#ff1744",
    "#1de9b6",
    "#2979ff",
    "#ff4081",
    "#b2ff59",
];
export const MARK_WIDTH = 3;
export const MARK_RADIUS = 9;

/* How far off a marker still counts as being on it. */
export const HIT_TOLERANCE = 6;

/* How long the map takes to arrive, in milliseconds. */
const MIN_TRAVEL = 450;
const MAX_TRAVEL = 1100;
const PER_LEVEL = 130;

/* The attributes the popup leaves out: the source is what the heading says. */
const UNPRINTABLE = ["source"];

/**
 * Metres to the pixel at a zoom level, where a zoom level means whatever the
 * library says it means and a metre does not.
 */
export function groundResolution(latitude, zoom) {
    const width = EQUATOR * Math.cos((latitude * Math.PI) / 180);

    return width / (WORLD * 2 ** zoom);
}

export function zoomForResolution(latitude, resolution) {
    const width = EQUATOR * Math.cos((latitude * Math.PI) / 180);

    return Math.log2(width / (WORLD * resolution));
}

/**
 * Long enough to follow, and longer the further it goes: covering ten zoom
 * levels in the time of one is what makes a jump hard to read.
 */
export function travelTime(from, to) {
    const levels = Math.abs(Math.log2(from / to));

    return Math.min(MAX_TRAVEL, MIN_TRAVEL + levels * PER_LEVEL);
}

/**
 * Slow at both ends and quickest in the middle, so the map appears to set off
 * and arrive rather than to cut.
 */
export function easing(t) {
    return t < 0.5 ? 4 * t * t * t : 1 - (-2 * t + 2) ** 3 / 2;
}

/** Every position of a geometry, whatever its nesting. */
export function positionsOf(coordinates) {
    if (coordinates === null || coordinates === undefined) {
        return [];
    }

    return typeof coordinates[0] === "number"
        ? [coordinates]
        : coordinates.flatMap(positionsOf);
}

/**
 * The top of what was clicked. A popup hangs its bottom edge from wherever it
 * is put, so anchoring it here leaves the thing it describes below it rather
 * than underneath it.
 */
export function northernmost(features) {
    const positions = features.flatMap((feature) =>
        positionsOf(feature.geometry?.coordinates),
    );

    return positions.reduce(
        (top, position) =>
            top === null || position[1] > top[1] ? position : top,
        null,
    );
}

/** The corners of what a group of features covers, west-south to east-north. */
export function boundsOf(features) {
    return features
        .flatMap((feature) => positionsOf(feature.geometry?.coordinates))
        .reduce(
            (bounds, position) =>
                bounds === null
                    ? [[...position], [...position]]
                    : [
                          [
                              Math.min(bounds[0][0], position[0]),
                              Math.min(bounds[0][1], position[1]),
                          ],
                          [
                              Math.max(bounds[1][0], position[0]),
                              Math.max(bounds[1][1], position[1]),
                          ],
                      ],
            null,
        );
}

/**
 * The elements the page offers, and where the map's description is to be had.
 *
 * The map starts itself rather than being handed anything: what it needs is
 * on the page it was loaded onto.
 */
export function target() {
    const element = document.querySelector(".test-map[data-spec]");

    if (element === null) {
        return null;
    }

    return {
        map: element,
        toggles: document.querySelector(".test-map-toggles"),
        spec: element.dataset.spec,
    };
}

export async function loadSpec(url) {
    const response = await fetch(url);

    if (!response.ok) {
        throw new Error(`Kunne ikke hente kortopsætningen: ${response.status}`);
    }

    return response.json();
}

/** One data set's features. */
export async function loadFeatures(url) {
    const response = await fetch(url);

    if (!response.ok) {
        throw new Error(`Kunne ikke hente data: ${response.status}`);
    }

    return response.json();
}

function escapeHtml(value) {
    return String(value)
        .replaceAll("&", "&amp;")
        .replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;")
        .replaceAll('"', "&quot;");
}

/**
 * The popup for whatever a click landed on: a section per feature, headed by
 * the data set it came from in the colour it was marked with.
 *
 * Which attributes an entity carries is the source's business, so this walks
 * whatever arrived rather than naming fields.
 */
export function popupHtml(sections) {
    return sections
        .map(({ title, colour, properties }) => {
            const rows = Object.keys(properties)
                .filter(
                    (key) =>
                        key.charAt(0) !== "_" &&
                        !UNPRINTABLE.includes(key) &&
                        properties[key] !== null &&
                        properties[key] !== "" &&
                        properties[key] !== undefined,
                )
                .map((key) => {
                    const value = Array.isArray(properties[key])
                        ? properties[key].join(", ")
                        : properties[key];

                    return `<dt>${escapeHtml(key)}</dt><dd>${escapeHtml(value)}</dd>`;
                })
                .join("");

            return [
                '<div class="test-map-section">',
                '<div class="test-map-title">',
                `<span class="test-map-swatch" style="background:${escapeHtml(colour)}"></span>`,
                escapeHtml(title),
                "</div>",
                `<dl class="test-map-details">${rows}</dl>`,
                "</div>",
            ].join("");
        })
        .join("");
}

/**
 * A toggle per data set, saying which model it publishes into.
 *
 * The grouped view is left out: it is how the data sets are drawn far out,
 * not a data set of its own to be switched.
 */
export function buildToggles(element, layers, onToggle) {
    const list = document.createElement("div");
    list.className = "test-map-toggle-list";

    for (const layer of layers) {
        const model = document.createElement("span");
        model.className = "test-map-toggle-model";
        model.textContent = layer.model;

        const toggle = document.createElement("button");
        toggle.type = "button";
        toggle.className = "test-map-toggle test-map-toggle-active";
        toggle.setAttribute("aria-pressed", "true");
        toggle.append(layer.title, model);

        toggle.addEventListener("click", () => {
            const on = toggle.getAttribute("aria-pressed") !== "true";

            toggle.setAttribute("aria-pressed", String(on));
            toggle.classList.toggle("test-map-toggle-active", on);
            onToggle(layer, on);
        });

        list.append(toggle);
    }

    element.replaceChildren(list);
}

/**
 * Sized before the map initialises, so it lays itself out at the height it
 * will keep rather than resizing under the user.
 */
export function fitHeight(element, onResize) {
    const fit = () => {
        const top = element.getBoundingClientRect().top + window.scrollY;

        element.style.height = `${Math.max(320, window.innerHeight - top - 24)}px`;
        onResize?.();
    };

    fit();
    window.addEventListener("resize", fit);
    window.addEventListener("load", fit);

    return fit;
}
