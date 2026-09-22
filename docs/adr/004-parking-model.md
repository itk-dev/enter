# 004: Parking model

| Field              | Value                                                  |
|--------------------|--------------------------------------------------------|
| **Created By**     | Jeppe Krogh                                            |
| **Date**           | 2026-09-22                                             |
| **Decision Maker** | ITK Dev team                                           |
| **Stakeholders**   | ITK Dev developers, data consumers, future maintainers |
| **Status**         | Draft                                                  |

## Context

Some of the data we publish describes parking spots reserved for disabled
drivers. This ADR serves to decide which approach we choose to take,
in terms of modeling these parking spots.

Our parking data takes two shapes. A point per location with the number of reserved
spots at it, and a polygon per individual spot with no count.

## Decision

Smart Data Models has two models that initially both seem to fit a parking spot:

`ParkingSpot` - A spot is one delimited space for one vehicle, and must also carry
an occupancy status and a reference to the site it belongs to.

`OnStreetParking` - holds a number of spots and requires only an identifier, a type and a
location.

Following 003, we type each record by what it describes, and use the parking
hierarchy as it is designed. A record that is one spot becomes a `ParkingSpot`,
with a reference to the site it belongs to.

A record that counts spots at a location becomes a site, `OnStreetParking` on
the street and `OffStreetParking` in a car-park, carrying the count and the reservation.

## Consequences

### Easier

- A spot and the location that counts it can be linked, and the count can be
  checked against the spots.
- Building context with available data and models makes later conflation easier

### Harder

- Every ParkingSpot carries an occupancy status of unknown, only because the schema
  requires one.
- A spot with no counted location nearby has no site to reference, and the
  reference is mandatory, so it cannot be published conformant until a site is
  found for it.
- Which site a spot belongs to is not in the data, so the reference comes from
  matching on location. A wrong match puts a spot under the wrong site, and a
  count can disagree with the spots linked to it.
