# 006: Model selection — OnStreetParking over ParkingGroup

| Field              | Value                                                  |
|--------------------|--------------------------------------------------------|
| **Created By**     | Jeppe Krogh                                            |
| **Date**           | 2026-08-31                                             |
| **Decision Maker** | ITK Dev team                                           |
| **Stakeholders**   | ITK Dev developers, data consumers, future maintainers |
| **Status**         | Draft                                                  |

## Context

The parking domain is organised as a hierarchy:

```text
OnStreetParking / OffStreetParking   site — no parent, requires id, type, location
    └── ParkingGroup                 subdivision — requires refParkingSite
            └── ParkingSpot          individual unit — requires refParkingSite, status, category
```

This decision applies where a data set provides a count of units per location
with a point geometry, no reference to a containing site, and neither per-unit
geometry nor occupancy.

Entity identifiers embed the type by convention, so the choice must be made
before first publication: changing it afterwards means deleting and
re-publishing.

This ADR serves to decide which model in the parking hierarchy is published
under those conditions.

### Drivers

- **Functional:** every mandatory relationship must point at an entity that
  exists; the restriction on who may park must be expressible unambiguously;
  finer granularity addable later without restructuring what is published.
- **Non-functional:** nothing invented purely to satisfy a schema; a choice
  that is cheap to reverse in preference to one that is not.

### Options Considered

1. **`ParkingGroup`, creating the missing parent site.** `category` offers
   `onlyDisabled`, which by name states exclusivity, and the model's reference
   example for disabled parking sits at this level. But `refParkingSite` is
   mandatory and no value is available for it, so a parent must be invented;
   one spanning the whole administrative area asserts a false containment, and
   its own mandatory geometry would carry no meaning.
   `ParkingSpot` also requires a site, so adding per-unit data later would force
   the invented entity into existence after entities had been published against
   it.
2. **`ParkingGroup`, omitting `refParkingSite`.** Nothing invented, smallest
   change — but knowingly non-conformant, and a schema validator flags every
   entity.
3. **`OnStreetParking`.** Requires only `id`, `type` and `location`, all of
   which are available. It is the entity both `ParkingGroup` and
   `ParkingSpot` are required to reference, so finer granularity can be
   attached beneath it, and migration down to `ParkingGroup` stays possible if
   real site data appears. `category` offers only `forDisabled`, which does not
   state exclusivity as plainly.
4. **`ParkingSpot`.** Models an individual unit, but only a count per location
   is available, `status` is mandatory with no occupancy data, and a parent site
   is required. Rejected outright.

## Decision

Publish each record as an **`OnStreetParking`** entity, with
`category: ["forDisabled"]` and no `refParkingSite`.

- It is the only option that invents nothing; everything the model requires is
  available.
- Mandatory relationships propagate downward, so choosing `ParkingGroup` would
  schedule the invented parent rather than avoid it — `ParkingSpot` requires a
  site too.
- The costs are asymmetric. Site level, then finding real sites exist, is a
  one-off migration. Subdivision level, then never acquiring real sites, means
  maintaining an invented entity indefinitely.

The model's reference example does use `ParkingGroup` for disabled parking, but
points at a real street-address site. It shows what to do when a site exists,
not when none does.

## Consequences

### Positive

- No dangling relationship; every entity is self-contained.
- Nothing invented to create, document or keep in sync.
- Conformant to the model's schema without exceptions.
- `ParkingGroup` or `ParkingSpot` entities can be attached beneath these later
  without changing them.

### Negative / Trade-offs

- **`forDisabled` does not state exclusivity.** The schema documents `category`
  only as "Street parking category" with an enum list and defines no individual
  value. The two models' enums use inconsistent prefixes for what appear to be
  the same concepts — `forDisabled` / `forResidents` against `onlyDisabled` /
  `onlyResidents` — while both carry `onlyWithPermit`, so the spelling cannot
  be relied on to carry exclusivity.
- Values must not be copied between the two models' `category` enums.
- Entity type is embedded in identifiers, so any later change means deleting
  and re-publishing.
- Domain experts may find a single address described as a "site"
  counter-intuitive.
