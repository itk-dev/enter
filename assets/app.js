/*
 * Welcome to your app's main JavaScript file!
 *
 * This file will be included onto the page via the importmap() Twig function,
 * which should already be in your base.html.twig.
 */
import './styles/app.css';
import WidgetAPI from 'septima-widget'

// const widget = new WidgetAPI('#map', {
//   map: {
//       debug: true,
//       view: {
//           zoomLevel: 4,
//           "x": 572929.1565135007,
//           "y": 6225655.880819891,
//           // x: 724413,
//           // y: 6175985,
//       },
//       layer: [
//           {
//               namedlayer: "#septima_standard"
//           },

// // {
// //         "features_host": "https://widget.cdn.septima.dk/demodata/dataapi1.json",
// //         "visible": true,
// //         "features": true,
// //         "features_dataType": "json",
// //         "type": "rawjson",
// //         "features_template_rows": "0.produktionsenhed",
// //         "features_template": "{ 'type': 'Feature', 'properties': { 'id': <%= cvrnummer %>, 'navn': '<%= navn %>'}, 'geometry': <%= _formatJSON(geometri) %>}",
// //         "features_style": [
// //           {
// //             "namedstyle": "#004",
// //             "symbol": "circle",
// //             "fillcolor": "rgba(255,255,255,1)",
// //             "strokecolor": "<%= _getRandomColor(1, id) %>",
// //             "strokewidth": 2,
// //             "radius": 14
// //           },
// //           {
// //             "namedstyle": "#004",
// //             "fillcolor": "<%= _getRandomColor(1, id) %>",
// //             "yOffset": 15,
// //             "scale": 0.5,
// //             "svg": "<svg xmlns='http://www.w3.org/2000/svg' xmlns:xlink='http://www.w3.org/1999/xlink' version='1.1' width='32' height='32' viewBox='0 0 24 24'><path d='M4,18V20H8V18H4M4,14V16H14V14H4M10,18V20H14V18H10M16,14V16H20V14H16M16,18V20H20V18H16M2,22V8L7,12V8L12,12V8L17,12L18,2H21L22,12V22H2Z' fill='currentColor' /></svg>"
// //           }
// //         ]
// //       },

//           {
//               "name": "Data",
//               "selectable": true,
//               "features": true,
//               // # https://widget.cdn.septima.dk/latest/components/helpers/layer.html#type
//               "type": "geojson",
//               // # https://datatracker.ietf.org/doc/html/rfc7946#section-4
//               // # https://geoinfospot.com/what-is-wgs84-the-coordinate-system/
//               "srs": "EPSG:4326",
//               "visible": true,

//               // # "features_host": "https://widget.cdn.septima.dk/demodata/skoler.geojson",

//               // # "features_host": "/data/ngsi-ld/v1/entities.geojson?type=Room",

//               // # "features_host": "/data/ngsi-ld/v1/entities.geojson?type=toilet",

//               // # "features_host": "/data/ngsi-ld/v1/entities.geojson?type=handicapparkering",

//               "features_host": "/data/ngsi-ld/v1/entities.geojson?type=hundeskov&limit=1",
//               // # https://widget.cdn.septima.dk/latest/components/helpers/layer.html#features-template
//               // # https://widget.cdn.septima.dk/latest/components/helpers/template.html#formatjson-data

//               // https://widget.cdn.septima.dk/latest/examples/data23.html#data-fra-json
//               "features_host": "/data/ngsi-ld/v1/entities?type=hundeskov&limit=1",
//               "features_dataType": "json",
//               "type": "rawjson",
//               "features_template": "{ 'type': 'Feature', 'properties': {'id': <%= cvrnummer %>, 'navn': '<%= navn %>'}, 'geometry': <%= _formatJSON(location) %>}",
//               "features_template": "{ 'type': 'Feature', 'properties': { 'id': <%= cvrnummer %>, 'navn': '<%= navn %>'}, 'geometry': <%= _formatJSON(geometri) %>}",

//               // # "features_template": "{ 'type': 'Feature', 'properties': {\"navn\": \"Hmm …\"}, 'geometry': <%= _formatJSON('{ \"type\": \"Point\", \"coordinates\": [ 10.179484273715866, 56.18474125523462 ] }') %>}",

//               // "features_dataType": "json",
//               // # "features_type": "Point",

//     "features_style": [
//       {
//       "symbol": "circle",
//       "symbol_selected": "circle",
//       "radius": 15,
//       "radius_selected": 20,
//       "fillcolor": "rgba(1, 119, 134, 1)",
//       "fillcolor_selected": "rgba(1, 119, 134, 1)"
//       },
//       {
//       "icon": "school",
//       "icon_selected": "school",
//       "scale": 0.8,
//       "scale_selected": 1.2,
//       "fillcolor": "rgba(255, 255, 255, 1)",
//       "fillcolor_selected": "rgba(255, 255, 255, 1)",
//       "xOffset": 0,
//       "yOffset": 1,
//       "yOffset_selected": 2
//       }
//     ],

//           },
//       ],
//   }
// })

// console.debug({widget})
