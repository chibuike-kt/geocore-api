# About GeoCore API

## What It Is

GeoCore is a backend geospatial platform built to demonstrate production-grade API architecture using spatial databases. It is the kind of system that companies building drone platforms, logistics intelligence tools, or location-aware infrastructure would build as their core data layer.

There is no frontend. The backend is the product.

---

## Why It Was Built

Most portfolio projects demonstrate CRUD. GeoCore demonstrates something harder — spatial data engineering, real-time event detection, and PostGIS query design — the kind of backend work that separates engineers who understand systems from those who only understand frameworks.

The goal was to build a platform that requires genuine knowledge to build correctly:

- Geometry types, coordinate systems, and SRID handling
- Spatial indexing and query optimisation with GiST indexes
- Stateful event detection (geofence enter/exit transitions)
- Idempotent bulk ingestion with geometry hashing
- High-volume telemetry design decisions (BIGSERIAL vs UUID)
- Background job queuing for large dataset exports

---

## What It Demonstrates

### Backend Architecture
Strict layered design with Controller → Service → Repository separation. Services contain all business logic. Repositories contain all database access. Controllers contain neither. Repository interfaces are bound through a service provider so the storage layer is fully decoupled from business logic.

### PostGIS & Spatial Databases
Every spatial operation uses raw PostGIS SQL:

- `ST_Within`, `ST_DWithin`, `ST_Intersects`, `ST_Distance`
- `ST_Buffer` with geography cast for metre-accurate buffering
- `ST_MakeLine` for flight path reconstruction from telemetry
- `ST_ClusterDBSCAN` for point feature clustering
- `ST_SquareGrid` for density grid analysis
- `ST_Union`, `ST_Area`, `ST_Extent` for coverage analytics
- KNN `<->` operator for nearest-neighbour queries

### Domain Modelling
The geofence module models a real detection problem. It tracks the last known state per mission/geofence pair and fires events only on actual transitions (enter/exit/re-entry), not on every position evaluation. This mirrors how production geofencing systems work.

### Scalability Decisions
- Telemetry uses `BIGSERIAL` not UUID — high-volume insert tables benefit from sequential integer keys
- Bulk ingestion uses SHA-256 geometry hashing for idempotency — the same geometry can never be inserted twice
- Large exports are queued as background jobs — synchronous only for datasets under 500 features
- All geometry columns have GiST spatial indexes

---

## Technical Stack

```
PHP 8.2          Laravel 12        PostgreSQL 18
PostGIS 3.x      Redis (queue)     PHPUnit (tests)
```

---

## Scope

| Module | Endpoints |
|--------|-----------|
| Datasets | 5 |
| Features | 3 |
| Spatial Queries | 4 |
| Drone Missions | 7 |
| Geofences | 6 |
| Analytics | 5 |
| Export | 5 |
| **Total** | **35** |

40 feature tests covering all modules with real PostGIS database assertions.

---

## Author

Built as a portfolio project to demonstrate production-grade geospatial backend engineering with PHP, Laravel, and PostGIS.
