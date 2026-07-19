# Migrations

Product / warehouse schema for `imby_data_v2` is owned by **agents_v2**.

Do not add create/alter migrations here. Run schema changes from:

```bash
cd ../agents_v2
docker compose exec app php artisan migrate --force
```

Auth-related tables on the data DB:

- `2000_06_01_000001` — `personal_access_tokens` (Sanctum)
- `2000_07_01_000001` — `password_reset_tokens` (password broker)
