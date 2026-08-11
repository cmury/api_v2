# AGENTS.md

## Cursor Cloud specific instructions

This repo (`api_v2`) is the IMBY **JSON API** (Laravel 13, Sanctum bearer tokens), served on
**port 8001**. It owns **no schema**: every model uses the `data` connection →
`imby_data_v2`, whose tables (users, users_searches, users_preferences, users_log,
personal_access_tokens, password_reset_tokens, users_passkeys, warehouse tables, users_chat_*) are created and
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
- Passkeys (WebAuthn): `GET /api/auth/passkeys/login/options` + `POST /api/auth/passkeys/login`
  return the same Bearer token shape. Authenticated manage: `GET|POST /api/auth/passkeys*` /
  `DELETE /api/auth/passkeys/{id}`. Table `users_passkeys` (agents_v2). Configure
  `PASSKEYS_RELYING_PARTY_ID` + `PASSKEYS_ALLOWED_ORIGINS` (include the SPA origin). Password
  login remains available.

### Warehouse read APIs
- Map: `GET /api/map/markers` (public GeoJSON), `GET /api/map/markers/csv` (Bearer).
- Entities: `/api/authorities`, `/api/applications`, `/api/locations`, `/api/legislation` (+ nested applications).
- Facilities: `/api/facilities` (+ near/applications).
- Planning controls: `/api/planning-controls`, `/api/planning-controls/at-point`, taxonomies for layers/codes.
- Contacts: `/api/contacts`, `/api/applications/{id}/contacts` (contribute via POST).
- Certifiers: `/api/certifiers` (filter `enrichment_status` / `enriched`), `/api/certifiers/{id}/applications`,
  `/api/applications/{id}/certifiers` (read-only Fair Trading register links).
- Claims: `/api/user/contact-profile`, `/api/user/claims`, `POST|DELETE /api/applications/{id}/claim`.
- Portfolio: `/api/contacts/{id}/portfolio` (owner add/remove; published list for others).
- Favourites: CRUD `/api/user/favourites`.
- Analytics: `/api/stats`, `/api/charts`, `/api/forecasts` (volume projections).
- Taxonomies: `/api/taxonomies/*`. Shared filters live in `App\Support\Warehouse\*`.

### Billing (Stripe via Laravel Cashier)
- Off until Stripe env vars + Price IDs are set. Uses **Checkout Sessions**, **Customer
  Portal**, and Cashier **webhooks** (not legacy Tokens / Plans).
- Plans catalogue: `GET /api/billing/plans` (public).
- Auth (Bearer): `GET /api/billing/status`, `POST /api/billing/checkout` → `{ url }`,
  `POST /api/billing/portal` → `{ url }`, `POST /api/billing/swap`, `POST /api/billing/cancel`,
  `POST /api/billing/resume`.
- Webhook: `POST /stripe/webhook` (CSRF-exempt). Configure signing secret as
  `STRIPE_WEBHOOK_SECRET`. Run `php artisan cashier:webhook` or create the endpoint in
  Stripe Dashboard pointing at `{APP_URL}/stripe/webhook`.
- Config: `config/cashier.php`, `config/imby.php` → `billing.plans` (`STRIPE_PRICE_*`).
- Schema: `users` pm columns + `users_subscriptions` + `users_subscription_items` in
  **agents_v2** (migrate there after pull).

### Property reports (public one-time Stripe)
- Guest Payment Element flow (no login): pricing → pay → status → PDF download.
- `GET /api/reports/property/pricing`, `GET /api/reports/property/example` (sample PDF),
  `POST /api/reports/property/pay` `{ location_id | lat+lng | address, email? }` →
  `{ client_secret, download_token }`, `GET /api/reports/property/{token}/status`,
  `GET /api/reports/property/{token}/download` (402 until paid).
- Webhook: `POST /api/reports/stripe/webhook` (`payment_intent.succeeded`).
- PDF via Dompdf from warehouse location + planning controls + DAs (falls back to
  example dataset when data is missing). Schema: `report_purchases` in agents_v2.
- Config: `imby.reports.property` (`STRIPE_PROPERTY_REPORT_AMOUNT_CENTS`, etc.).

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
