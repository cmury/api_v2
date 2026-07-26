# AGENTS.md

## Cursor Cloud specific instructions

This repo (`api_v2`) is the IMBY **JSON API** (Laravel 13, Sanctum bearer tokens), served on
**port 8001**. It owns **no schema**: every model uses the `data` connection →
`imby_data_v2`, whose tables (users, users_searches, users_preferences, users_log,
personal_access_tokens, password_reset_tokens, warehouse tables, chat_*) are created and
owned by the sibling **`agents_v2`** repo. Run `agents_v2` migrations before expecting the
API to work.

### Environment already provided by the update script / VM snapshot
- PHP **8.4** is the default `php` (the committed `composer.lock` pins Symfony 8.1 → PHP >= 8.4.1,
  even though `composer.json` says `^8.3`; PHP 8.3 fails `composer install`).
- Composer deps are installed by the update script (`composer install`). This API has no Vite
  frontend build.
- A local `.env` (untracked) already exists with `DATA_DB_HOST=127.0.0.1` (the committed
  `.env.example` uses `postgis`, the docker hostname; the docker-compose here also expects the
  external `agents_v2_default` docker network. On this VM we run natively against local Postgres
  instead of Docker).

### Prerequisites to run
- Postgres must be running (not auto-started on boot): `sudo pg_ctlcluster 16 main start`.
- `imby_data_v2` must be migrated (do this from `../agents_v2`: `php artisan migrate`).

### Running the app (dev)
- `php artisan serve --host=0.0.0.0 --port=8001` (or `composer dev` for serve+queue+pail).
- Smoke check: `GET http://localhost:8001/api/status` → `{"database":{"ok":true,...}}`.
- Auth flow: `POST /api/auth/register` or `/api/auth/login` return a bearer token under
  `data.token`; pass it as `Authorization: Bearer <token>` to `/api/user/*` and warehouse routes.

### Warehouse read APIs
- Map: `GET /api/map/markers` (public GeoJSON), `GET /api/map/markers/csv` (Bearer).
- Entities: `/api/authorities`, `/api/applications`, `/api/locations/{id}` (+ nested applications).
- Analytics: `/api/stats`, `/api/charts` (collapsed replacements for the old per-scope count/chart tree).
- Taxonomies: `/api/taxonomies/*-classes`. Shared filters live in `App\Support\Warehouse\*`.

### Tests & lint
- `php artisan test` passes (uses sqlite `:memory:` per `phpunit.xml`). Warehouse and Insights
  feature tests cover auth + validation only (live PostGIS / LLM paths are verified manually).
- Lint: `./vendor/bin/pint` (check-only: `--test`).

### AI insights feature (`laravel/ai` + local Llama via Ollama) — optional
- **Off by default.** Set `INSIGHTS_ENABLED=true` to register `/api/insights/*` and `GET /insights`.
- Docker: `docker compose --profile insights up -d` starts Ollama; pull a model with
  `docker compose --profile insights exec ollama ollama pull llama3.2:3b`.
- Endpoint: `POST /api/insights/ask` (Bearer) — body `{ "question": "..." }`. Uses `InsightsAgent`
  (structured output, temperature 0) → `App\Support\SqlGuard` → `data_readonly` connection.
- Browser test page: `GET /insights` when enabled.
- Provider config: `config/ai.php`; defaults to `ollama` + `OLLAMA_MODEL` (see `.env.example`).
- Safety: `data_readonly` is SELECT-only (`imby_readonly`); `SqlGuard` enforces read-only SQL,
  row `LIMIT`, and blocks system catalogs. AI SDK conversation migrations are NOT installed.
- Ollama gotcha on this VM class: the bundled runner may pick an AVX-512 ggml backend that
  segfaults. Move AVX-512 backends out of `/usr/local/lib/ollama/` so it falls back to AVX2
  `haswell`, then restart `ollama serve`.
