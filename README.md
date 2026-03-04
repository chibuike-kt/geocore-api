# GeoCore API

A production-grade geospatial backend platform built with Laravel 12, PostgreSQL 18, and PostGIS. Designed for drone platforms, logistics intelligence systems, and location intelligence infrastructure.

---

## Overview

GeoCore is an API-first geospatial platform that exposes spatial data management, drone mission tracking, geofence event detection, and spatial analytics through a clean REST API. There is no frontend — the backend is the product.

---

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Backend | PHP 8.2 / Laravel 12 |
| Database | PostgreSQL 18 |
| Spatial Engine | PostGIS 3.x |
| Queue | Database (Redis-ready) |
| Storage | Local (S3-ready) |

---

## Architecture

GeoCore follows a strict layered architecture with full separation of concerns:

```
HTTP Request
     ↓
Controller       — HTTP in/out only, delegates immediately
     ↓
Service          — Business logic, orchestration, validation
     ↓
Repository       — All database interaction, spatial SQL
     ↓
PostGIS / PostgreSQL
```

Controllers never touch the database. Repositories never contain business logic. Services never know about HTTP.

### Folder Structure

```
app/
├── Geo/                     # Geometry helpers and WKT conversion
├── Http/
│   ├── Controllers/         # HTTP layer
│   ├── Requests/            # Form request validation
│   └── Resources/           # API response transformers
├── Services/                # Business logic layer
├── Repositories/
│   ├── Contracts/           # Repository interfaces
│   └── *.php                # Concrete implementations
├── Models/                  # Eloquent models
├── Jobs/                    # Queued async jobs
├── Events/                  # Domain events
└── Listeners/               # Event listeners
```

---

## Features

### Dataset Management
Store and manage named collections of spatial features. Supports points, linestrings, polygons, and multi-geometry types in SRID 4326.

### Feature Ingestion
Single feature insertion and bulk GeoJSON FeatureCollection upload. Idempotent ingestion via SHA-256 geometry hashing and `source_id` deduplication — duplicate features are never inserted.

### Spatial Queries
Direct PostGIS spatial operations exposed as REST endpoints:

| Query | PostGIS Function | Description |
|-------|-----------------|-------------|
| Within | `ST_Within` | Features completely inside a polygon |
| Radius | `ST_DWithin` | Features within N metres of a point |
| Nearest | `<->` KNN operator | N closest features to a point |
| Intersects | `ST_Intersects` | Features intersecting any geometry |

### Drone Mission Tracking
Full mission lifecycle management with enforced status transitions (`planned → active → completed/aborted`). Telemetry ingestion supports single pings and batches of up to 500 points. The track endpoint reconstructs the full flight path as a GeoJSON LineString using PostGIS `ST_MakeLine` with aggregate flight statistics.

### Geofence Engine
Create polygon geofences with optional altitude bounds. The evaluation engine uses stateful enter/exit detection — it tracks the last known event per mission/geofence pair and fires events only on actual state transitions. The same position evaluated twice fires no duplicate events.

| State | Last Event | Result |
|-------|-----------|--------|
| Inside | null | ENTER |
| Inside | enter | (no event) |
| Inside | exit | ENTER |
| Outside | null | (no event) |
| Outside | enter | EXIT |
| Outside | exit | (no event) |

### Spatial Analytics
PostGIS-powered analytics operations:

| Endpoint | Operation | Description |
|----------|-----------|-------------|
| `/analytics/buffer` | `ST_Buffer` | Expand geometry by radius, find features inside |
| `/analytics/coverage` | `ST_Union` + `ST_Area` | Total area covered by polygon features |
| `/analytics/density` | `ST_SquareGrid` | Feature count per grid cell |
| `/analytics/cluster` | `ST_ClusterDBSCAN` | Group point features into spatial clusters |
| `/analytics/extent` | `ST_Extent` | Bounding box of all features in a dataset |

### Export System
Export datasets to GeoJSON FeatureCollection or CSV. Synchronous for small datasets (under 500 features). Automatically queued for large datasets with status polling and direct file download.

---

## API Reference

### Datasets
```
GET    /api/v1/datasets
POST   /api/v1/datasets
GET    /api/v1/datasets/{id}
PUT    /api/v1/datasets/{id}
DELETE /api/v1/datasets/{id}
```

### Features
```
GET    /api/v1/datasets/{id}/features
POST   /api/v1/datasets/{id}/features
POST   /api/v1/datasets/{id}/features/bulk
```

### Spatial Queries
```
POST   /api/v1/query/within
GET    /api/v1/query/radius
GET    /api/v1/query/nearest
POST   /api/v1/query/intersects
```

### Missions
```
GET    /api/v1/missions
POST   /api/v1/missions
GET    /api/v1/missions/{id}
PUT    /api/v1/missions/{id}
PATCH  /api/v1/missions/{id}/status
POST   /api/v1/missions/{id}/telemetry
GET    /api/v1/missions/{id}/track
```

### Geofences
```
GET    /api/v1/geofences
POST   /api/v1/geofences
GET    /api/v1/geofences/{id}
PUT    /api/v1/geofences/{id}
POST   /api/v1/geofences/evaluate
GET    /api/v1/geofences/{id}/events
```

### Analytics
```
POST   /api/v1/analytics/buffer
POST   /api/v1/analytics/coverage
POST   /api/v1/analytics/density
POST   /api/v1/analytics/cluster
GET    /api/v1/analytics/extent
```

### Export
```
POST   /api/v1/export
POST   /api/v1/export/geojson
POST   /api/v1/export/csv
GET    /api/v1/export/status/{exportId}
GET    /api/v1/export/download/{exportId}
```

---

## Installation

### Requirements
- PHP 8.2+
- PostgreSQL 18 with PostGIS 3.x
- Composer

### Setup

```bash
# Clone and install dependencies
git clone https://github.com/chibuike-kt/geocore-api.git
cd geocore-api
composer install

# Configure environment
cp .env.example .env
php artisan key:generate
```

### Database Setup

```sql
-- Run in psql as postgres superuser
CREATE USER geocore_user WITH PASSWORD 'your_password';
CREATE DATABASE geocore OWNER geocore_user;
\c geocore
CREATE EXTENSION IF NOT EXISTS postgis;
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS pgcrypto;
```

Update `.env`:
```ini
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=geocore
DB_USERNAME=geocore_user
DB_PASSWORD=your_password
QUEUE_CONNECTION=database
```

Run migrations:
```bash
php artisan migrate
php artisan queue:table
php artisan migrate
```

Start the server:
```bash
php artisan serve
php artisan queue:work   # in a separate terminal
```

---

## Testing

GeoCore has a full feature test suite covering all modules with real PostGIS assertions.

### Test Database Setup

```sql
CREATE DATABASE geocore_test OWNER geocore_user;
\c geocore_test
CREATE EXTENSION IF NOT EXISTS postgis;
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS pgcrypto;
```

### Run Tests

```bash
# Full suite
php artisan test

# Single module
php artisan test tests/Feature/GeofenceTest.php

# Single test
php artisan test --filter test_evaluator_fires_exit_event_when_leaving

# Verbose
php artisan test --verbose
```

---

## Database Design

All geometry columns use SRID 4326 (WGS84). GiST spatial indexes are applied to every geometry column for query performance.

| Table | Primary Key | Geometry Column | Type |
|-------|------------|----------------|------|
| `datasets` | UUID | — | — |
| `features` | UUID | `geometry` | `GEOMETRY(Geometry, 4326)` |
| `missions` | UUID | `planned_area` | `GEOMETRY(Polygon, 4326)` |
| `telemetry` | BIGSERIAL | `position` | `GEOMETRY(PointZ, 4326)` |
| `geofences` | UUID | `geometry` | `GEOMETRY(Polygon, 4326)` |
| `geofence_events` | BIGSERIAL | `position` | `GEOMETRY(Point, 4326)` |
| `audit_logs` | BIGSERIAL | — | — |

> Telemetry and geofence_events use BIGSERIAL instead of UUID because they are high-volume insert tables. UUID primary keys cause index fragmentation at scale.

---

## Engineering Decisions

**Raw SQL for geometry columns** — Laravel's Eloquent has no native PostGIS support. All geometry reads and writes use raw SQL with PostGIS functions (`ST_GeomFromText`, `ST_AsGeoJSON`, `ST_MakePoint`). This avoids casting hacks and keeps spatial logic explicit.

**Repository interfaces** — Every repository is bound through `RepositoryServiceProvider`. Services depend on interfaces, never concrete classes. This makes the system testable and the storage layer swappable.

**Idempotent ingestion** — Features are fingerprinted with SHA-256 hashes of their geometry. Bulk uploads silently skip duplicates and return a summary of inserted vs skipped counts.

**Stateful geofence evaluation** — The evaluator tracks the last known event per mission/geofence pair. This means the API can be called at any frequency without producing duplicate events, and correctly detects re-entry after exit.

**Audit logging** — Every write operation and spatial query writes to `audit_logs`. The log is append-only (no `updated_at` column) and indexed by entity and time.

---

## License

MIT
