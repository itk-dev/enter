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
coordinate reference system is cross-cutting rather than a per-input detail.
Inputs arrive in whatever CRS their publisher uses — Danish municipal data is
commonly projected, typically EPSG:25832 (ETRS89 / UTM zone 32N) as eastings and
northings in metres — and no single input CRS can be assumed. A GeoJSON envelope
states a geometry type but not units, so projected coordinates arrive inside one
undetected.

This ADR serves to decide which coordinate reference system is published, and at
what precision.

### Drivers

- **Functional:** `location` is interpretable unbriefed, geo-queries return
  correct results, and clients render without preprocessing.
- **Non-functional:** conformance and self-description; one rule for every
  input; precision no worse than the input.

### Options Considered

1. **Normalise everything to WGS84, reprojecting in the adapter.** One rule
   however many input CRSs accumulate. Needs a reprojection dependency, and each
   input must declare its CRS.
2. **Pass each input's native CRS through unchanged.** No transformation and no
   dependency, but the GeoJSON is invalid, entities from different inputs are
   incomparable, and distances read as degrees.
3. **Publish WGS84 and also keep the original coordinates in an extra
   attribute.** Saves consumers a round trip, but the attribute has no stable
   shape — CRS and geometry type differ per input, and it is absent for inputs
   already in WGS84 — so nothing can be coded against it.
4. **Pass native CRSs through under RFC 7946's "prior arrangement" clause.**
   Permitted, but the clause requires all parties to have agreed, which a broker
   whose consumers are unknown by design cannot satisfy.

## Decision

Every `location` the adapter emits is **EPSG:4326 (WGS84) longitude/latitude**,
at **full precision — coordinates are not rounded**. Each input declares its own
CRS; reprojection happens at the boundary between reading an input and building
an entity, and nowhere else.

1. **The specification leaves no choice.** RFC 7946 §4 fixes the CRS for all
   GeoJSON coordinates as geographic, WGS 84 datum, longitude and latitude in
   decimal degrees; NGSI-LD GeoProperty values are GeoJSON and inherit it.
2. **There is no way to declare otherwise.** RFC 7946 Appendix B.1 removed the
   `crs` member of the 2008 specification, so projected coordinates publish an
   undeclarable assumption. An input may still carry that deprecated member; it
   can be read, not passed on.
3. Nothing catches the mistake: a broker accepts projected coordinates, answers
   geo-queries incorrectly and places points wrongly, raising no error; and
   entities from different inputs are queried together, so a query spanning two
   CRSs returns meaningless results.
4. **A round trip is lossless at full precision.** Projected to geographic and
   back returns the input exactly when nothing is rounded; rounding trades
   accuracy for a marginal payload reduction.
5. `source` and `seeAlso` can reference the originating export, which states its
   own CRS.

## Consequences

### Positive

- Payloads are valid GeoJSON and NGSI-LD, comparable across inputs, and render
  in any client unmodified, under one rule for every present and future input.
- Reprojection is isolated in one tested component, verified against
  independently known reference coordinates, so a regression fails loudly rather
  than silently relocating data.

### Negative / Trade-offs

- Adds a reprojection dependency, and national grid definitions are not always
  shipped, so registering them becomes load-bearing project code.
- Datum shifts are approximated: ETRS89-based grids are treated as equivalent
  to WGS84 via a null datum transformation, so what is published is ETRS89
  labelled WGS84. Coincident in 1989, the two have diverged by roughly 0.5–1 m
  at about 2.5 cm per year — standard practice in web GIS, and the largest
  error introduced, so a consumer transforming rigorously with an explicit
  epoch lands about a metre away.
- Consumers with natively projected stacks must convert.
- Every new input must declare its CRS, and unsupported ones need adding.
- Each geometry type needs its own reprojection, not points alone.
