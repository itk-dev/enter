# 004: Coordinate reference system — publish WGS84 (EPSG:4326)

| Field              | Value                                                    |
|--------------------|----------------------------------------------------------|
| **Created By**     | Jeppe Krogh                                              |
| **Date**           | 2026-08-27                                               |
| **Decision Maker** | ITK Dev team                                             |
| **Stakeholders**   | ITK Dev developers, broker consumers, future maintainers |
| **Status**         | Draft                                                    |

## Context

Every entity the adapter publishes carries a `location` GeoProperty, so the
coordinate reference system is a cross-cutting concern rather than a per-source
detail.

Input data arrives in whatever CRS its publisher uses. Danish municipal data is
commonly projected — typically EPSG:25832 (ETRS89 / UTM zone 32N), as eastings
and northings in metres. Other inputs may already be geographic, or use a
different projection. The adapter cannot assume one input CRS.

Projected coordinates are sometimes delivered inside a GeoJSON envelope, which
states a geometry type but not units. The data models specify only that
`location` is GeoJSON and make no reference to a coordinate system; the
constraint comes from GeoJSON itself.

This ADR serves to decide which coordinate reference system is published,
and at what precision.

### Drivers

- **Functional:** consumers must be able to interpret `location` unbriefed;
  geo-queries must return correct results; clients must render without
  preprocessing; one rule must hold for every input.
- **Non-functional:** self-description; conformance; uniformity across inputs;
  precision no worse than the input.

### Options Considered

1. **Normalise everything to WGS84, reprojecting in the adapter.** Conforms to
   RFC 7946; self-describing; geo-queries work; one rule however many input
   CRSs accumulate. Requires a reprojection dependency, and each input must
   declare its CRS.
2. **Pass each input's native CRS through unchanged.** No transformation, no
   dependency — but produces invalid GeoJSON with nowhere to declare the CRS,
   makes entities from different inputs mutually incomparable, and breaks
   geo-queries because distances are read as degrees. Every failure is silent.
3. **Publish WGS84 and also retain original coordinates in an extra
   attribute.** Avoids a round trip for consumers wanting native coordinates,
   but the attribute cannot have a stable shape: each input brings its own CRS
   and geometry type, and it would be absent for inputs already in WGS84. A
   consumer cannot code against that, so it would go unused.
4. **Pass native CRSs through under RFC 7946's "prior arrangement" clause,
   documenting each out of band.** Permitted by the RFC, but the clause
   requires all parties to have agreed — incompatible with a broker whose
   consumers are unknown by design.

## Decision

Every `location` the adapter emits is **EPSG:4326 (WGS84)
longitude/latitude**, at **full precision — coordinates are not rounded**. Each
input declares its own CRS; reprojection happens at the boundary between reading
an input and building an entity, and nowhere else.

- **The specification is unambiguous.** RFC 7946 §4: "The coordinate reference
  system for all GeoJSON coordinates is a geographic coordinate reference
  system, using the World Geodetic System 1984 (WGS 84) datum, with longitude
  and latitude units of decimal degrees." NGSI-LD GeoProperty values are
  GeoJSON, so the requirement is inherited.
- **There is no way to declare otherwise.** RFC 7946 Appendix B.1:
  "Specification of coordinate reference systems has been removed, i.e., the
  'crs' member of [GJ2008] is no longer used." Publishing projected coordinates
  means publishing an undeclarable assumption. Inputs may still carry that
  deprecated member; it can be read, but not passed on.
- Entities from different inputs are queried together, so a query spanning two
  inputs published in different CRSs returns meaningless results.
- A broker given projected coordinates accepts them, answers geo-queries
  incorrectly, and renders points in the wrong location. No error is raised at
  any stage.
- **The conversion is lossless at full precision.** A projected-to-geographic
  round trip returns the input exactly when no rounding is applied. Rounding
  trades accuracy for a marginal reduction in payload size.
- `source` and `seeAlso` can reference the originating export, which states its
  own CRS. A coordinate copied into an extra attribute states nothing.

## Consequences

### Positive

- Payloads are valid GeoJSON and NGSI-LD; geo-queries work and are comparable
  across inputs; any client renders them unmodified.
- One rule for every present and future input.
- Reprojection is isolated in one component with its own tests, verified
  against independently known reference coordinates, so a regression fails
  loudly instead of silently relocating data.

### Negative / Trade-offs

- Adds a reprojection dependency. National grid definitions are not always
  shipped and may need registering explicitly, making them load-bearing
  project code.
- **Datum shifts are approximated.** ETRS89-based grids are treated as
  equivalent to WGS84 via a null datum transformation. The two were coincident
  in 1989 and have diverged by roughly 0.5–1 m since, at about 2.5 cm per year.
  What is published is therefore ETRS89 labelled WGS84. This is standard
  practice in web GIS, but it is the largest error in the pipeline — greater
  than the source's own positional accuracy — so a consumer using a rigorous
  transformation with an explicit epoch will land about a metre away.
- Consumers with natively projected stacks must convert.
- Every new input must declare its CRS, and unsupported ones need adding.
- Only point geometries were implemented initially; line and area geometries
  were added later.
