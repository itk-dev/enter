# 003: Parking model

| Field              | Value                                                  |
|--------------------|--------------------------------------------------------|
| **Created By**     | Jeppe Krogh                                            |
| **Date**           | 2026-08-31                                             |
| **Decision Maker** | ITK Dev team                                           |
| **Stakeholders**   | ITK Dev developers, data consumers, future maintainers |
| **Status**         | Draft                                                  |

## Context

Some of the data we publish describes parking spots reserved for disabled
drivers. This ADR serves to decide which approach we choose to take,
in terms of modeling these parking spots.

Smart Data Models has two models that initially both seem to fit a parking spot:
`ParkingSpot` and `OnStreetParking`. Geometry does not separate them, but what they
stand for and what they have to carry does.

A site holds a number of spots and requires only an identifier, a type and a
location. A spot is one delimited space for one vehicle, and must also carry
an occupancy status and a reference to the site it belongs to.

Our data takes both shapes: a point per location with the number of reserved
spots at it, and a polygon per individual spot with no count.

Neither carries occupancy nor a containing site. The two often describe the same
places, and the aim is to merge them into one picture, so the model has to keep
the granularity of each record and let the two be related.

## Decision

The initial instinct were to select one model to fit all parking-spot-related data,
but this turns out to be the wrong approach.

We type each record by what it describes, and use the parking hierarchy as it
is designed. A record that is one spot becomes a `ParkingSpot`, and a reference
to the site it belongs to.

A record that counts spots at a location becomes a site, `OnStreetParking` on
the street and `OffStreetParking` in a car-park, carrying the count and the reservation.

## Consequences

### Easier

- A spot and the location that counts it can be linked, and the count can be
  checked against the spots.
- Data may vary, so choosing models based on each source gets us away from
  trying to force data into one model.
- Merged entities reuse the same types, so conflation adds no modeling work.

### Harder

- Every spot carries an occupancy status of unknown, only because the schema
  requires one.
- A spot with no counted location nearby has no site to reference, and the
  reference is mandatory, so it cannot be published conformant until a site is
  found for it.
- Takes more work to write adapter, as it has to potentially handle different
  types of data in the same dataset.
- Which site a spot belongs to is not in the data, so the reference comes from
  matching on location. A wrong match puts a spot under the wrong site, and a
  count can disagree with the spots linked to it.
