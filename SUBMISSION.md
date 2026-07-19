# Submission checklist

**Candidate:** Kaustubh Nitin Borkar  
**Contact:** borkarkaustubhnitin@gmail.com  
**Repo:** https://github.com/kai2o/find-your-influencer  

Email **careers@exhibit.co.in**  
Subject: `FindYourInfluencer Assignment — Kaustubh Nitin Borkar`

## Repo

| Field | Value |
|---|---|
| **SSH** | `git@github.com:kai2o/find-your-influencer.git` |
| **HTTPS** | https://github.com/kai2o/find-your-influencer |
| **Visibility** | Public |

**Never commit** `.env`, `APIFY_TOKEN`, or Google OAuth secrets. Rotate any token that was pasted in chat.

## Done in this repo

- [x] §4.A watchlist CRUD + detail deltas + re-fetch + scheduler (10 min / 1h stale)
- [x] `FetchProfileJob` + Apify / Fake providers
- [x] Postgres advisory lock (+ Redis NX fallback) + concurrency test
- [x] Token bucket + IST daily quota (`quota:YYYY-MM-DD`)
- [x] Circuit breaker + retry classification
- [x] HMAC webhook + replay
- [x] Structured JSON job logs + `/healthz`
- [x] EXPLAIN ANALYZE in README + `docs/explain-analyze.md`
- [x] N+1 proof screenshot `docs/n-plus-one-watchlist.png` (3 SQL on `/watchlist`)
- [x] Pest tests
- [x] `.env.example` (no secrets)
- [x] Seed: `php artisan db:seed` (≥ 3 samples)
- [x] Code pushed to GitHub

## Your remaining manual steps

1. **Loom ≤ 5 min** — UI working → open a controller → open `FetchProfileJob` → run `php artisan test` on camera  
2. **Email** careers@exhibit.co.in with the body below  
3. Optional: rotate Apify token if it was shared  

## Email body (copy/paste)

```
Hi Exhibit team,

Please find my FindYourInfluencer assignment submission.

Repo: https://github.com/kai2o/find-your-influencer
Seed: php artisan db:seed
Hours: <N>
Provider: Apify (apify~instagram-profile-scraper)
Loom: <URL>
Deployed: none (local run — README has <10 min setup)
Contact: borkarkaustubhnitin@gmail.com

Thanks,
Kaustubh Nitin Borkar
```

## Local smoke before send

```powershell
php artisan migrate
php artisan db:seed
npm run build
php artisan serve          # terminal 1
php artisan queue:work     # terminal 2
php artisan schedule:work  # terminal 3
php artisan test
```

Login: `admin@example.com` / `password`
