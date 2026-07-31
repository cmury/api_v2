# AGENTS.md

## Cursor Cloud specific instructions

This repo (`api_v2`) is the IMBY **JSON API** (Laravel 13, Sanctum bearer tokens), served on
**port 8001**. It owns **no schema**: every model uses the `data` connection →
`imby_data_v2`, whose tables (users, users_searches, users_preferences, users_log,
personal_access_tokens, password_reset_tokens, warehouse tables, users_chat_*) are created and
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
- Entities: `/api/authorities`, `/api/applications`, `/api/locations`, `/api/legislation` (+ nested applications).
- Facilities: `/api/facilities` (+ near/applications).
- Analytics: `/api/stats`, `/api/charts`, `/api/forecasts` (volume projections).
- Taxonomies: `/api/taxonomies/*`. Shared filters live in `App\Support\Warehouse\*`.

### OpenAPI docs
- Interactive: `GET /docs/api` (Scramble + Scalar; `local`/`testing` only).
- Spec: `GET /docs/api.json` or `php artisan scramble:export` → `docs/openapi.json`.
- Config: `config/scramble.php`. Do **not** use old `@SWG` annotations.

### Tests & lint
- `php artisan test` passes (uses sqlite `:memory:` per `phpunit.xml`). Warehouse and Insights
  feature tests cover auth + validation only (live PostGIS / LLM paths are verified manually).
- Lint: `./vendor/bin/pint` (check-only: `--test`).

### AI insights feature (`laravel/ai` + cloud provider) — optional
- **Off by default.** Set `INSIGHTS_ENABLED=true` to register `/api/insights/*` and `GET /insights`.
- Default provider is **OpenAI** (same as `config/ai.php`). Set `OPENAI_API_KEY` in `.env`.
  Override with `INSIGHTS_PROVIDER` / `INSIGHTS_MODEL` (e.g. Anthropic).
- Endpoint: `POST /api/insights/ask` (Bearer) — body `{ "question": "..." }`.
  `InsightsAgent` uses **tool calling** over warehouse APIs (search/get authorities &
  applications, facilities, stats, forecasts, taxonomies, OpenAPI lookup) then returns
  structured plain-English `answer`. Guarded `run_warehouse_sql` is last-resort only
  (`SqlGuard` + `data_readonly`).
- Browser test page: `GET /insights` when enabled.
- Provider config: `config/ai.php` + `config/imby.php` (see `.env.example`).
- Prefer a tool-capable cloud model; local Ollama remains optional via
  `docker compose --profile insights` if you set `INSIGHTS_PROVIDER=ollama`.
- Safety: `data_readonly` is SELECT-only (`imby_readonly`); `SqlGuard` enforces read-only SQL,
  row `LIMIT`, and blocks system catalogs. AI SDK conversation migrations are NOT installed.
