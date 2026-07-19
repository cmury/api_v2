# IMBY API (`api_v2`)

Laravel 13 JSON API for the IMBY product database (`imby_data_v2`).

Warehouse / product schema is owned by **agents_v2** migrations. This app connects to the same PostGIS instance and exposes HTTP endpoints (Sanctum-ready).

## Prerequisites

- Docker Desktop
- `agents_v2` stack running (provides `laravel-postgis` on network `agents_v2_default`)

```bash
cd ../agents_v2
docker compose up -d
```

## Setup

```bash
cp .env.example .env
docker compose up -d --build
docker compose exec app php artisan key:generate
```

API base: [http://localhost:8001](http://localhost:8001)

Smoke check: [http://localhost:8001/api/status](http://localhost:8001/api/status)

## Notes

- App container joins the external Docker network `agents_v2_default` and reaches Postgres as host `postgis`.
- Default DB connection is `data` → `imby_data_v2`.
- Session/cache/queue use file/sync drivers so Laravel ops tables stay out of the warehouse unless you add them deliberately.
- Sanctum is installed; auth routes/tokens come next.
