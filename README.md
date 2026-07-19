# FindYourInfluencer

Internal admin tool to track Instagram profiles, refresh them via queued jobs, and store time-series snapshots.

**Provider:** Apify — `POST https://api.apify.com/v2/acts/apify~instagram-profile-scraper/run-sync-get-dataset-items`  
**Concurrency:** Postgres advisory locks in production (`pg_try_advisory_lock(profile_id)`); Redis NX lock (TTL 120s) on SQLite/local. See below.  
**Stack:** Laravel 12, Inertia v2, React 19 + TypeScript, Tailwind, PostgreSQL (SQLite OK for local), Redis, Pest.

---

## Setup (< 10 minutes)

### Prerequisites

- PHP 8.2+ with extensions: `curl`, `mbstring`, `openssl`, `pdo_pgsql` (or `pdo_sqlite`), `zip`
- Composer, Node 20+, Redis
- PostgreSQL recommended for submission; SQLite works for local/dev
- **Windows SSL:** ensure `curl.cainfo` / `openssl.cafile` in `php.ini` point at `storage/certs/cacert.pem` (see that folder’s README). Without this, Apify calls fail with cURL error 60.

### Steps

```bash
cp .env.example .env
composer install
php artisan key:generate

# Local quickstart (SQLite + Redis)
# Ensure Redis is running on 127.0.0.1:6379
touch database/database.sqlite

# Or Postgres:
# DB_CONNECTION=pgsql
# DB_HOST=127.0.0.1
# DB_PORT=5432
# DB_DATABASE=findyourinfluencer
# DB_USERNAME=postgres
# DB_PASSWORD=secret

php artisan migrate
php artisan db:seed
npm install
npm run build

# Terminal A
php artisan serve

# Terminal B
php artisan queue:work

# Terminal C (optional scheduler)
php artisan schedule:work
```

**Login:** `admin@example.com` / `password`

**Seed notes:**
- `php artisan db:seed` — admin user + 3 sample profiles (`SampleProfileSeeder`)
- `php artisan db:seed --class=BenchmarkSeeder` — ≥1,000 profiles + ≥10,000 snapshots for EXPLAIN proofs

### Environment

| Variable | Purpose |
|---|---|
| `APIFY_TOKEN` | Apify API token (never commit) |
| `APIFY_ACTOR_ID` | Default `apify~instagram-profile-scraper` |
| `PROFILE_PROVIDER` | `apify` or `fake` |
| `WEBHOOK_SECRET` | HMAC shared secret for `/webhooks/{provider}` |
| `QUEUE_CONNECTION` | Prefer `redis` |
| `CACHE_STORE` | Prefer `redis` for scheduler overlap lock |

---

## Concurrency approach (§4.B.2)

**Chosen:** Postgres advisory lock `SELECT pg_try_advisory_lock(profile_id)`.

**Why:** Fast, keyed by profile id, enforced by the database connection workers already share. Released in a `finally` block so crashes still unlock when the connection drops.

**Local/SQLite fallback:** Redis `SET key NX EX 120`. TTL is 120s — longer than connect (3s) + read (15s) timeouts and a typical Apify cold run (~8s). If a job exceeds TTL, Redis may release the key and a second worker could proceed; **use Postgres in production** so advisory locks are the source of truth.

Proof: `tests/Feature/ConcurrencyTest.php` — held lock → zero HTTP calls; after unlock → exactly one HTTP call.

---

## Circuit breaker (§4.B.5)

```text
closed --[10 consecutive failures]--> open
open --[2 minutes]--> half_open
half_open --[probe success]--> closed
half_open --[probe failure]--> open
```

Implemented in Redis (`circuit:apify:failures`, `circuit:apify:open_until`, `circuit:apify:half_open`). No third-party package.

While open, jobs are **deferred** (`release(120)`), not counted as failed attempts.

---

## Rate limiting + daily quota (§4.B.3)

**Token bucket** in Redis: capacity 100, refill 10/min. Empty bucket → re-dispatch with exponential delay (not a failure).

**Daily quota (IST):** Redis key `quota:YYYY-MM-DD` using Asia/Kolkata date. Ceiling 1000 units/day; jobs stop (deferred, logged) when usage reaches **90%** of ceiling. Incremented after a successful provider fetch.

**HTTP timeouts:** **connect 3s**, **read 60s** (Apify sync run can take longer on cold starts).

---

## EXPLAIN ANALYZE (§4.B.7)

Run after `php artisan db:seed --class=BenchmarkSeeder` on **Postgres**.  
Re-generate anytime with: `php scripts/explain_proof.php` (writes [docs/explain-analyze.md](docs/explain-analyze.md)).

Seed used for the plans below: **1,003 profiles**, **10,018 snapshots**.

### Watchlist query BEFORE composite index

```
Limit  (cost=20.76..20.77 rows=1 width=1048) (actual time=0.423..0.425 rows=20 loops=1)
  ->  Sort  (cost=20.76..20.77 rows=1 width=1048) (actual time=0.421..0.421 rows=20 loops=1)
        Sort Key: last_refreshed_at DESC NULLS LAST
        Sort Method: top-N heapsort  Memory: 26kB
        ->  Seq Scan on profiles  (cost=0.00..20.75 rows=1 width=1048) (actual time=0.022..0.293 rows=803 loops=1)
              Filter: ((status)::text = 'fetched'::text)
              Rows Removed by Filter: 200
Planning Time: 1.719 ms
Execution Time: 0.483 ms
```

**Diff signal:** `Seq Scan on profiles`.

### Watchlist query AFTER composite index

Index: `(status, last_refreshed_at DESC) INCLUDE (username)`

```
Limit  (cost=16.93..16.95 rows=5 width=1048) (actual time=0.507..0.508 rows=20 loops=1)
  ->  Sort  (cost=16.93..16.95 rows=5 width=1048) (actual time=0.506..0.507 rows=20 loops=1)
        Sort Key: last_refreshed_at DESC NULLS LAST
        Sort Method: top-N heapsort  Memory: 26kB
        ->  Bitmap Heap Scan on profiles  (cost=4.31..16.88 rows=5 width=1048) (actual time=0.279..0.404 rows=803 loops=1)
              Recheck Cond: ((status)::text = 'fetched'::text)
              Heap Blocks: exact=20
              ->  Bitmap Index Scan on profiles_status_last_refreshed_idx  (cost=0.00..4.31 rows=5 width=0) (actual time=0.116..0.116 rows=803 loops=1)
                    Index Cond: ((status)::text = 'fetched'::text)
Planning Time: 1.440 ms
Execution Time: 0.713 ms
```

**Diff signal:** `Seq Scan` → `Bitmap Index Scan` / `Bitmap Heap Scan` on `profiles_status_last_refreshed_idx`.

### 30-day snapshots query

```
Sort  (cost=15.49..15.50 rows=3 width=620) (actual time=0.103..0.104 rows=6 loops=1)
  Sort Key: profile_snapshots.captured_at DESC
  Sort Method: quicksort  Memory: 25kB
  InitPlan 1
    ->  Limit  (cost=0.28..0.39 rows=1 width=8) (actual time=0.060..0.060 rows=1 loops=1)
          ->  Index Only Scan using profiles_pkey on profiles  (cost=0.28..115.32 rows=1003 width=8) (actual time=0.059..0.059 rows=1 loops=1)
                Heap Fetches: 1
  ->  Bitmap Heap Scan on profile_snapshots  (cost=4.31..15.07 rows=3 width=620) (actual time=0.097..0.098 rows=6 loops=1)
        Recheck Cond: ((profile_id = (InitPlan 1).col1) AND (captured_at >= (now() - '30 days'::interval)))
        Heap Blocks: exact=1
        ->  Bitmap Index Scan on profile_snapshots_profile_captured_idx  (cost=0.00..4.31 rows=3 width=0) (actual time=0.084..0.084 rows=6 loops=1)
              Index Cond: ((profile_id = (InitPlan 1).col1) AND (captured_at >= (now() - '30 days'::interval)))
Planning Time: 2.526 ms
Execution Time: 0.130 ms
```

Uses `profile_snapshots_profile_captured_idx` (`profile_id`, `captured_at`).

---

## N+1 (§4.B.8)

Watchlist index loads a single paginated query (no per-row relations). Sessions use Redis so auth is not SQL. Detail page loads snapshots once via `$profile->snapshots()`.

Evidence and capture steps: [docs/n-plus-one-watchlist.md](docs/n-plus-one-watchlist.md).  
Screenshot: ![Watchlist ≤3 queries](docs/n-plus-one-watchlist.png)

---

## Trade-offs

1. **SQLite for local + Postgres for production** — speeds onboarding on Windows; advisory locks and partial unique indexes are Postgres-specific and activated when `DB_CONNECTION=pgsql`.
2. **FakeProfileProvider when `APIFY_TOKEN` is empty** — keeps demos/tests running without burning Apify credit; set `PROFILE_PROVIDER=apify` + token for real fetches.

---

## Skipped / deferred

- Bonus §5 (self-chaining / horizontal scaling / Prometheus) — deferred unless buffer time remains after solid §4.
- Deployed URL — optional; local + GitHub is the submission path.

---

## Assumptions

- Instagram via Apify Instagram Profile Scraper actor.
- Status machine: `pending → fetching → fetched|failed`.
- Scheduler every 10 minutes; stale = `last_refreshed_at` older than 1 hour (or null).
- Webhook is simulated (`POST /webhooks/{provider}`) even though Apify path here is pull-based.

---

## Tests

```bash
php artisan test
```

Minimum coverage: Inertia watchlist feature, retry classifier unit, job dispatch, concurrency (lock → one HTTP), webhook valid/invalid/replay.

---

## Health

`GET /healthz` — 200 when DB + Redis reachable and a queue job processed within 5 minutes; otherwise 503 `{"status":"degraded","failing":[...]}`.
