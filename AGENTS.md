# AGENTS.md

## Cursor Cloud specific instructions

This repo (`api_v2`) is the IMBY **JSON API** (Laravel 13, Sanctum bearer tokens), served on
**port 8001**. It owns **no schema**: every model uses the `data` connection →
`imby_data_v2`, whose tables (users, users_searches, users_preferences, users_log,
personal_access_tokens, password_reset_tokens) are created and owned by the sibling **`agents_v2`**
repo. Run `agents_v2` migrations before expecting the API to work.

### Environment already provided by the update script / VM snapshot
- PHP **8.4** is the default `php` (the committed `composer.lock` pins Symfony 8.1 → PHP >= 8.4.1,
  even though `composer.json` says `^8.3`; PHP 8.3 fails `composer install`).
- Composer + Node deps are installed by the update script (`composer install` + `npm install`).
- A local `.env` (untracked) already exists with `DATA_DB_HOST=127.0.0.1` (the committed
  `.env.example` uses `postgis`, the docker hostname; the docker-compose here also expects the
  external `agents_v2_default` docker network. On this VM we run natively against local Postgres
  instead of Docker).

### Prerequisites to run
- Postgres must be running (not auto-started on boot): `sudo pg_ctlcluster 16 main start`.
- `imby_data_v2` must be migrated (do this from `../agents_v2`: `php artisan migrate`).

### Running the app (dev)
- `php artisan serve --host=0.0.0.0 --port=8001` (or `composer dev` for serve+queue+pail+vite).
- Smoke check: `GET http://localhost:8001/api/status` → `{"database":{"ok":true,...}}`.
- Auth flow: `POST /api/auth/register` or `/api/auth/login` return a bearer token under
  `data.token`; pass it as `Authorization: Bearer <token>` to `/api/user/*` routes.

### Tests & lint
- `php artisan test` passes (uses sqlite `:memory:` per `phpunit.xml`). Tests are provider- and
  Postgres-free: the `SqlGuard` logic is unit-tested and the insights endpoint tests only cover
  auth + validation (the live LLM→SQL path is verified manually).
- Lint: `./vendor/bin/pint` (check-only: `--test`; a few pre-existing files report style diffs).

### AI insights feature (`laravel/ai` + local Llama via Ollama)
- Endpoint: `POST /api/insights/ask` (Bearer auth) — body `{ "question": "..." }`. It uses the
  `InsightsAgent` (structured output) to turn the question into a single read-only SELECT, runs it
  through `App\Support\SqlGuard`, and executes it on the **`data_readonly`** connection.
- Provider config lives in `config/ai.php`; defaults to the `ollama` provider + `OLLAMA_MODEL`
  (see `.env.example`). Switching to a hosted provider only needs `AI_DEFAULT_PROVIDER` + that
  provider's key — no code change.
- Safety: `data_readonly` is a SELECT-only Postgres role (`imby_readonly`); `SqlGuard` additionally
  enforces single read-only statement, row `LIMIT`, and blocks system catalogs. AI SDK conversation
  migrations are intentionally NOT installed (keeps `api_v2` schema-less).
- To run the live feature you need: Ollama running (`ollama serve`), a model pulled
  (`ollama pull llama3.2:3b`), and the `imby_readonly` role granted SELECT on `imby_data_v2`.
- Ollama gotcha on this VM class: the bundled runner auto-selects the AVX-512 `sapphirerapids`
  ggml backend, which segfaults here (the Firecracker CPU advertises AVX-512 but faults on it).
  Fix: move the AVX-512 backends out of `/usr/local/lib/ollama/` (`libggml-cpu-sapphirerapids.so`,
  `skylakex`, `icelake`, `cascadelake`, `cooperlake`, `cannonlake`, `zen4`, `alderlake`) so it falls
  back to the AVX2 `haswell` build, then restart `ollama serve`.
