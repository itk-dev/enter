/*
 * What the Leaflet and the MapLibre map have in common.
 *
 * The two are here to be compared, so everything that is not the library
 * itself — where the map opens, what a popup says, how long a movement takes,
 * how the toggles look — is decided once and shared. A difference between the
 * two tabs is then a difference between the libraries.
 */

const EQUATOR = 40075016.686;

/*
 * How wide the world is at zoom 0, which is not something the libraries agree
 * on: Leaflet and the slippy-map convention put it at 256 pixels, MapLibre at
 * 512. The same zoom number is therefore a different scale in each, so the
 * conversions below are told which world they are converting for.
 */
export const SLIPPY_WORLD = 256;
export const GL_WORLD = 512;

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

/* The attributes the popup leaves out, as the widget's template does. */
const UNPRINTABLE = ["geometry", "source", "features"];

/**
 * Metres to the pixel at a zoom level, where a zoom level means whatever the
 * library says it means and a metre does not.
 */
export function groundResolution(latitude, zoom, world = SLIPPY_WORLD) {
    const width = EQUATOR * Math.cos((latitude * Math.PI) / 180);

    return width / (world * 2 ** zoom);
}

export function zoomForResolution(latitude, resolution, world = SLIPPY_WORLD) {
    const width = EQUATOR * Math.cos((latitude * Math.PI) / 180);

    return Math.log2(width / (world * resolution));
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
 * The elements a map page offers, and where its description is to be had.
 *
 * Each map is an entrypoint of its own, so it starts itself rather than being
 * handed anything: what it needs is on the page it was loaded onto.
 */
export function target() {
    const element = document.querySelector(".test-map[data-spec]");

    if (element === null) {
        return null;
    }

    return {
        map: element,
        toggles: document.querySelector(".test-map-toggles"),
        panel: document.querySelector(".test-map-stats"),
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

/**
 * One data set's features, and how long they took to arrive.
 */
export async function loadFeatures(url) {
    const started = performance.now();
    const response = await fetch(url);

    if (!response.ok) {
        throw new Error(`Kunne ikke hente data: ${response.status}`);
    }

    const collection = await response.json();

    return { collection, milliseconds: performance.now() - started };
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
 * whatever arrived rather than naming fields — the same as the template the
 * widget is given, so that all three popups say the same thing.
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
                '<div class="widget-simple-title">',
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
 * A toggle per data set, rendered where the widget detaches its own.
 *
 * The grouped view is left out, as it is for the widget: it is how the data
 * sets are drawn far out, not a data set of its own to be switched.
 */
export function buildToggles(element, layers, onToggle) {
    const list = document.createElement("div");
    list.className = "test-map-toggle-list";

    for (const layer of layers) {
        const toggle = document.createElement("button");
        toggle.type = "button";
        toggle.className = "test-map-toggle test-map-toggle-active";
        toggle.setAttribute("aria-pressed", "true");
        toggle.textContent = layer.title;

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

/**
 * What the comparison is actually about, in numbers.
 *
 * Which map feels quicker is worth knowing but hard to argue with, so the page
 * also says how long the data took to arrive, how much of it there is, and how
 * many frames the map manages while it is being dragged about.
 */
export function stats(element, library) {
    const fields = {};

    element.replaceChildren(
        ...["bibliotek", "objekter", "hentet", "tegnet", "billeder/s"].map(
            (name) => {
                const field = document.createElement("span");
                field.className = "test-map-stat";
                field.innerHTML = `<span class="test-map-stat-name">${name}</span><span class="test-map-stat-value">–</span>`;
                fields[name] = field.lastElementChild;

                return field;
            },
        ),
    );

    fields["bibliotek"].textContent = library;

    let frames = 0;
    let since = performance.now();

    const count = (now) => {
        frames += 1;

        if (now - since >= 500) {
            fields["billeder/s"].textContent = Math.round(
                (frames * 1000) / (now - since),
            );
            frames = 0;
            since = now;
        }

        window.requestAnimationFrame(count);
    };

    window.requestAnimationFrame(count);

    return {
        /*
         * The widget fetches its own data, so there is no fetching of ours to
         * time on that tab; what cannot be told apart is left blank rather
         * than guessed at.
         */
        loaded(features, fetched, drawn) {
            const milliseconds = (value) =>
                value === null || value === undefined
                    ? "–"
                    : `${Math.round(value)} ms`;

            fields["objekter"].textContent = features.toLocaleString("da-DK");
            fields["hentet"].textContent = milliseconds(fetched);
            fields["tegnet"].textContent = milliseconds(drawn);
        },
        failed(message) {
            fields["objekter"].textContent = message;
        },
    };
}
