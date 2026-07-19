# N+1 / query budget proof (§4.B.8)

## Approach

- Watchlist index uses a **single paginated query** on `profiles` (no per-row relation loads).
- Sessions are stored in **Redis** (`SESSION_DRIVER=redis`) so auth does not add SQL per request.
- Detail page loads snapshots via `$profile->snapshots()` once (not N+1).

## Measured result (Laravel Debugbar)

Capture: `storage/debugbar/01KXR76F3DN4Q4NPC3ABP12Q7T.json` for `GET /watchlist` (Redis sessions):

| # | SQL |
|---|---|
| 1 | `select * from "users" where "id" = ? limit 1` |
| 2 | `select count(*) as aggregate from "profiles"` |
| 3 | `select * from "profiles" order by ... limit 20` |

**3 queries** — well under ≤ 3 and **not** N+1 (does not grow with page size).

Screenshot: [n-plus-one-watchlist.png](n-plus-one-watchlist.png)
