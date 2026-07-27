# IMBY API (`api_v2`)

Laravel 13 JSON API for the IMBY product database (`imby_data_v2`).

Warehouse / product schema (including Sanctum + password-reset tables) is owned by **agents_v2** migrations. This app connects to the same PostGIS instance and exposes HTTP endpoints only — no schema migrations here.

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
Root `/` redirects to `/api/status`.

## API docs (OpenAPI)

Generated automatically with [Scramble](https://scramble.dedoc.co/) from routes, FormRequests, and API Resources (OpenAPI 3.1 — no `@SWG` annotations).

| URL | What |
|-----|------|
| [http://localhost:8001/docs/api](http://localhost:8001/docs/api) | Interactive docs (Scalar UI) |
| [http://localhost:8001/docs/api.json](http://localhost:8001/docs/api.json) | OpenAPI JSON |

Docs are available in `local` / `testing` only (`viewApiDocs` gate). Export a snapshot:

```bash
docker compose exec app php artisan scramble:export
# → docs/openapi.json
```

## Auth (Sanctum)

| Method | Path | Auth |
|--------|------|------|
| POST | `/api/auth/register` | public |
| POST | `/api/auth/login` | public |
| POST | `/api/auth/logout` | Bearer |
| POST | `/api/auth/password/change` | Bearer |
| POST | `/api/auth/password/forgot` | public |
| POST | `/api/auth/password/reset` | public |

## User

| Method | Path | Auth |
|--------|------|------|
| DELETE | `/api/user` | Bearer (body: `password`) — deletes account + related data |
| GET | `/api/user/profile` | Bearer |
| PUT | `/api/user/profile` | Bearer |
| GET | `/api/user/settings` | Bearer |
| PUT | `/api/user/settings` | Bearer |
| GET | `/api/user/log` | Bearer |
| GET | `/api/user/searches` | Bearer |
| POST | `/api/user/searches` | Bearer |
| GET | `/api/user/searches/{id}` | Bearer |
| PUT | `/api/user/searches/{id}` | Bearer |
| DELETE | `/api/user/searches/{id}` | Bearer |

Register body: `name`, `surname`, `email`, `password`, `password_confirmation`, optional `company` / `mobile`.

Settings body (all optional): `map_type`, `date_range`, `new_application_email_frequency`, `locale`, `default_search_id`.

Saved search body: `name`, `lat`, `lng`, `radius`, `notify`, optional `filter` (object).

Activity log query: `?filter=&per_page=15&page=1`.

## Warehouse (map / entities / analytics)

| Method | Path | Auth |
|--------|------|------|
| GET | `/api/map/markers` | public — `?query=<json>` or flat filters → GeoJSON |
| GET | `/api/map/markers/csv` | Bearer — CSV of matching applications |
| GET | `/api/authorities/coverage` | public |
| GET | `/api/authorities` | Bearer |
| GET | `/api/authorities/statistics` | Bearer — search `authorities_statistics` (`statistics_code`, `authority_id`, `state`, `measure`, `year`, `source`, `latest=1`) |
| GET | `/api/authorities/{id}` | Bearer |
| GET | `/api/authorities/{id}/statistics` | Bearer — ABS/census (latest year per measure; `?all=1` or `?year=2021`) |
| GET | `/api/authorities/{id}/locations` | Bearer — locations linked via `authority_locations` |
| GET | `/api/authorities/{id}/boundary` | Bearer — LGA boundary GeoJSON from `authorities.geom` |
| GET | `/api/applications` | Bearer — optional `legislation_ids` / `legislation_id` |
| GET | `/api/applications/{id}` | Bearer — includes `legislation` when loaded |
| GET | `/api/applications/{id}/legislation` | Bearer |
| GET | `/api/legislation` | Bearer — `jurisdiction`, `instrument_type`, `status`, search |
| GET | `/api/legislation/{id}` | Bearer |
| GET | `/api/legislation/{id}/applications` | Bearer — shared application filters |
| GET | `/api/locations` | Bearer — search/filter, `state`, `suburb`, `authority_id` |
| GET | `/api/locations/{id}` | Bearer |
| GET | `/api/locations/{id}/applications` | Bearer |
| GET | `/api/notifications` | Bearer — GeoJSON for notify-enabled searches |
| GET | `/api/stats?metric=` | Bearer |
| GET | `/api/charts?metric=&format=` | Bearer |
| GET | `/api/taxonomies/application-classes` | Bearer |
| GET | `/api/taxonomies/application-types` | Bearer — optional `jurisdiction`, `class_id` |
| GET | `/api/taxonomies/development-classes` | Bearer |
| GET | `/api/taxonomies/development-types` | Bearer — optional `jurisdiction`, `class_id` |
| GET | `/api/taxonomies/decision-classes` | Bearer |
| GET | `/api/taxonomies/decision-types` | Bearer — optional `jurisdiction`, `class_id` |

Map filter JSON (legacy + aliases): `map.bounds` `[latMax,lngMax,latMin,lngMin]`, `app` / `application_class_ids`, `type` / `development_class_ids`, `status` / `decision_class_ids`, `legislation_ids`, `estvalue`, `date`.

Stats metrics: `applications`, `estimated_costs`, `application_types`, `development_types`, `decision_classes`.

Chart formats: `timeseries`, `calendar`, `categorical`, `bands` (or `auto`).

Every chart response includes Chart.js-friendly `labels` + `values`:

| format | `labels` | `values` | `series` (extra) |
|--------|----------|----------|------------------|
| `categorical` / `bands` | category names | counts (1D) | — |
| `calendar` | month names | year×month matrix | years (row keys) |
| `timeseries` | period dates | counts, or cost **sums** for `estimated_costs` | full points (`period`, `count`, optional `sum`/`avg`) |

## Insights (experimental)

Disabled unless `INSIGHTS_ENABLED=true`.

| Method | Path | Auth |
|--------|------|------|
| POST | `/api/insights/ask` | Bearer |
| GET | `/api/insights/threads` | Bearer |
| GET | `/api/insights/threads/{id}` | Bearer |
| DELETE | `/api/insights/threads/{id}` | Bearer |
| GET | `/insights` | browser test UI |

```bash
# .env: INSIGHTS_ENABLED=true
docker compose --profile insights up -d
docker compose --profile insights exec ollama ollama pull llama3.2:3b
```

## Notes

Login / protected routes use `Authorization: Bearer {token}`.

Users live in `imby_data_v2.users` (schema owned by agents_v2).

Auth activity is written to `users_log` for: `login`, `logout`, `password_changed`, `password_reset`, `profile_updated`, `settings_updated`, `search_created`, `search_updated`, `search_deleted`.
