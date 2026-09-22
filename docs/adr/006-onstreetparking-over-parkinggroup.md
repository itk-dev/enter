# 006: Model selection — OnStreetParking over ParkingGroup

| Field              | Value                                                  |
|--------------------|--------------------------------------------------------|
| **Created By**     | Jeppe Krogh                                            |
| **Date**           | 2026-08-31                                             |
| **Decision Maker** | ITK Dev team                                           |
| **Stakeholders**   | ITK Dev developers, data consumers, future maintainers |
| **Status**         | Draft                                                  |

## Context

The parking domain is a hierarchy. A site — `OnStreetParking` or
`OffStreetParking` — requires only `id`, `type` and `location`. Below it, a
`ParkingGroup` subdivision and a `ParkingSpot` unit must each reference a site,
and a spot also requires `status` and `category`.

This decision applies where a data set provides a count of units per location
with a point geometry, no reference to a containing site, and neither per-unit
geometry nor occupancy.

This ADR serves to decide which model in the parking hierarchy is published
under those conditions.

### Drivers

- **Functional:** every mandatory relationship points at an entity that exists;
  the restriction on who may park is expressible unambiguously; finer
  granularity is addable later without restructuring.
- **Non-functional:** nothing invented purely to satisfy a schema; a reversible
  choice in preference to one that is not.

### Options Considered

1. **`ParkingGroup`, creating the missing parent site.** `onlyDisabled` states
   exclusivity by name, and the model's reference example for disabled parking
   sits at this level — but points at a real street-address site.
   `refParkingSite` is mandatory with no value available, so a parent must be
   invented; one spanning the administrative area asserts a false containment
   and its own mandatory geometry carries no meaning.
2. **`ParkingGroup`, omitting `refParkingSite`.** Nothing invented, smallest
   change — but knowingly non-conformant, and a schema validator flags every
   entity.
3. **`OnStreetParking`.** Everything it requires is available, and it is the
   entity both lower levels must reference, so granularity can be added beneath
   it and migration downward stays possible if real sites appear. `category`
   offers only `forDisabled`, which states exclusivity less plainly.
4. **`ParkingSpot`.** Models the individual unit, but only a count per location
   is available, `status` is mandatory with no occupancy data, and a parent site
   is required. Rejected outright.

## Decision

Publish each record as an **`OnStreetParking`** entity, with
`category: ["forDisabled"]` and no `refParkingSite`.

- The only option that invents nothing: everything it requires is available.
- Mandatory references propagate downward, so `ParkingGroup` would schedule the
  invented parent rather than avoid it — `ParkingSpot` requires a site too.
- The costs are asymmetric. Site level, then finding real sites exist, is a
  one-off migration; subdivision level, then never acquiring real sites, means
  maintaining an invented entity indefinitely.

## Consequences

### Positive

- No dangling relationship: every entity is self-contained, and nothing invented
  has to be created, documented or kept in sync.
- Conformant to the model's schema without exceptions.
- `ParkingGroup` or `ParkingSpot` entities can be attached beneath these later
  without changing them.

### Negative / Trade-offs

- `forDisabled` does not state exclusivity. The schema documents `category` only
  as "Street parking category" with an enum list and defines no individual
  value, and the two models prefix apparently identical concepts inconsistently
  — `forDisabled` / `forResidents` against `onlyDisabled` / `onlyResidents` —
  while both carry `onlyWithPermit`, so values must not be copied between the
  enums.
- Entity type is embedded in identifiers, so any later change means deleting and
  re-publishing.
- Domain experts may find a single address described as a "site"
  counter-intuitive.
