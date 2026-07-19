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

## Auth (Sanctum)

| Method | Path | Auth |
|--------|------|------|
| POST | `/api/auth/register` | public |
| POST | `/api/auth/login` | public |
| POST | `/api/auth/logout` | Bearer |
| GET | `/api/auth/user` | Bearer |
| PUT | `/api/auth/user` | Bearer |
| POST | `/api/auth/password/change` | Bearer |
| POST | `/api/auth/password/forgot` | public |
| POST | `/api/auth/password/reset` | public |

Register body: `name`, `surname`, `email`, `password`, `password_confirmation`, optional `company` / `mobile`.

Login / protected routes use `Authorization: Bearer {token}`.

Users live in `imby_data_v2.users` (schema owned by agents_v2).
