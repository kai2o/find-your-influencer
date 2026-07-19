# EXPLAIN ANALYZE proofs

Generated: 2026-07-16T15:38:18+00:00

## Watchlist query BEFORE composite index

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

## Watchlist query AFTER composite index

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

## 30-day snapshots query

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

