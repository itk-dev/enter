/*
 * The test map: what the test sources published, drawn by MapLibre in one
 * colour per data set and nothing else.
 */

/* Dokk1, from far enough out to take in the town. */
const CENTER = [10.2144, 56.1535];
const ZOOM = 11;

/* One colour per data set, in the order the sources come. */
const COLOURS = ["#e6194b", "#3e7bfa", "#2ca02c", "#ff7f0e", "#9467bd"];

function drawMap(container, urls) {
    const map = new window.maplibregl.Map({
        container,
        style: {
            version: 8,
            sources: {
                background: {
                    type: "raster",
                    tiles: ["https://tile.openstreetmap.org/{z}/{x}/{y}.png"],
                    tileSize: 256,
                    maxzoom: 19,
                    attribution:
                        '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>-bidragydere',
                },
            },
            layers: [
                { id: "background", type: "raster", source: "background" },
            ],
        },
        center: CENTER,
        zoom: ZOOM,
    });

    map.on("load", () => {
        urls.forEach((url, position) => {
            const id = `dataset-${position}`;
            const colour = COLOURS[position % COLOURS.length];

            map.addSource(id, { type: "geojson", data: url });

            map.addLayer({
                id: `${id}-areas`,
                type: "fill",
                source: id,
                paint: { "fill-color": colour, "fill-opacity": 0.35 },
            });

            /* Left to itself a circle layer dots every corner of an area. */
            map.addLayer({
                id: `${id}-points`,
                type: "circle",
                source: id,
                filter: ["==", ["geometry-type"], "Point"],
                paint: { "circle-color": colour, "circle-radius": 4 },
            });
        });
    });
}

const element = document.querySelector(".test-map[data-urls]");

if (element !== null) {
    drawMap(element, JSON.parse(element.dataset.urls));
}
