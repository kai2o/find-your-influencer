# FindYourInfluencer Take-Home Assignment

**Take-Home Assignment — Full-Stack Developer (4–7 yrs)**

Welcome, and thanks for applying to Exhibit Social. This document is everything you need to complete the assignment. Read it end-to-end before you start coding.

---

## TL;DR (read this first)

| | |
|---|---|
| **Role** | Full-Stack Developer — influencer discovery + brand onboarding platform |
| **What you build** | A small Laravel + React (Inertia) app that tracks Instagram/YouTube profiles and refreshes them via a background job |
| **Deadline** | 6 calendar days from the moment this email lands in your inbox. Your clock starts the instant you receive it. |
| **Effort target** | 15–20 hours total, spread across the week. Quality > quantity — there’s no bonus for rushing. |
| **Submit to** | careers@exhibit.co.in — subject: `FindYourInfluencer Assignment — <Your Full Name>` |
| **Must include** | Public GitHub repo link + Loom video (≤ 5 min). Deployed URL is optional — we will clone and run locally. |
| **Stack** | Laravel 11/12, PHP 8.2+, Inertia v2, React 19 + TypeScript, Tailwind, PostgreSQL, Redis |

**Honest heads-up:** This task is deliberately harder than a typical tutorial. We want to see how you handle concurrency, database design, and third-party API limits — the real problems we deal with daily. An AI can scaffold the CRUD; it cannot explain your design choices in the interview. Build what you understand.

---

## About the role

This position is to take over the primary engineering seat on the Exhibit Social platform — you would become the person who owns the Laravel + React codebase, the background job pipeline, and the production VM day-to-day. The outgoing engineer will hand over context and be available for a short transition period.

That is why the bar below is high: we are looking for someone who can run the system, not just contribute to it. If you are shortlisted, you will inherit a real, working production app serving live traffic — so we need to see that you can reason about locks, quotas, databases, and failure modes on your own.

---

## 1. Why we designed this assignment

Our real production stack handles thousands of Instagram and YouTube profiles being scraped every night through background jobs, talking to rate-limited APIs, with strict quotas. When code breaks here, it either costs us money (API credits) or silently duplicates data.

So this assignment is built around the same three problems we solve every week:

1. Fetching data from a rate-limited third-party API without burning quota.
2. Storing time-series data (profile snapshots over time) in PostgreSQL efficiently.
3. Running background jobs that are safe to run in parallel and safe to re-run.

We are not grading you on: fancy UI design, custom auth systems, microservices, or AI features. Keep it simple; make the fundamentals rock-solid.

---

## 2. What you are building — “FindYourInfluencer”

A small internal admin tool with these user stories:

- **As an admin**, I want to add an Instagram handle like `@cristiano` to a watchlist, so the system fetches that profile’s public data (followers, following, post count, bio, profile picture) in the background.
- **As an admin**, I want to see all tracked handles in one searchable, filterable, paginated table with status badges (pending, fetched, failed), so I can quickly find and monitor profiles.
- **As an admin**, I want to open a handle’s detail page to see current metrics and a full history of every refresh, with the follower delta (↑/↓) per snapshot, so I can spot trends.
- **As an admin**, I want a “Re-fetch now” button on the detail page. I also want a scheduled job that runs every 10 minutes and refreshes any handle older than 1 hour — without duplicate API calls or data races.

That’s it. One app, four pages (login, list, add, detail).

---

## 3. Tech stack — non-negotiable

| Layer | What to use |
|---|---|
| Backend | Laravel 11 or 12, PHP 8.2+ |
| Frontend | Inertia.js v2 + React 18/19 with TypeScript (no Blade for main UI) |
| Styling | Tailwind CSS + shadcn/ui or Radix primitives |
| Database | PostgreSQL (SQLite is OK for local dev only — final deploy must be Postgres) |
| Queue | Redis preferred; database driver acceptable if you explain why |
| Scheduler | Laravel’s built-in scheduler |
| Tests | Pest or PHPUnit |

**Recommended starter:** `laravel new FindYourInfluencer --react --typescript` (the official Laravel React Starter Kit).

**Do not use:** Breeze, Jetstream, or plain Blade for the application UI.

---

## 4. Requirements

Requirements are split into two parts:

- **§4.A Core features** — the app has to do these things.
- **§4.B System-level requirements** — the quality bar. This is where we pick candidates.

Every item marked **MUST** is gated. Missing a MUST item means the section scores 0, regardless of how nice the UI looks.

---

### §4.A — Core features (40% of score)

#### 4.A.1 — Third-party API integration

Profile data must come from a real public API. Every provider we use has a generous free tier — pick one:

| Provider | Free tier | Where to sign up |
|---|---|---|
| Apify | $5/month free credit (plenty for this task) | apify.com → use the public “Instagram Profile Scraper” actor |
| RapidAPI | Free tier on several IG scrapers (e.g. `instagram-scraper-api2`, `instagram-looter2`) | rapidapi.com |
| YouTube Data API v3 | 10,000 free quota units/day | Google Cloud Console — if you pick this, model YouTube channels instead of IG handles (still valid) |

**Rules:**

- API key lives in `.env` — never commit it.
- In the README, tell us which provider and which exact endpoint(s) you used.
- The HTTP call must happen inside a queued job (see §4.B.1), never inside a controller.
- Use a throwaway account/API key for this task, not one tied to anything you rely on. Third-party scraper terms are a gray area we don’t want you carrying personal risk for.

#### 4.A.2 — Watchlist CRUD

- **Add handle form:** one input field, validates format, normalizes (lowercase, trim, strip `@`).
- **List page:** paginated table, searchable by username, filterable by status. URL must reflect current filters so a link can be shared (`/watchlist?status=failed&q=crist`).
- **Detail page:** current metrics card + snapshot history table with per-snapshot follower delta (show `+1,234` in green or `-567` in red).
- **Manual re-fetch button:** dispatches the job, shows a toast, re-renders with updated status.

#### 4.A.3 — Scheduled refresh

- A Laravel scheduled task runs every 10 minutes and enqueues `FetchProfileJob` for every profile whose `last_refreshed_at` is older than 1 hour.
- It must be safe to run: if the previous run is still going, the new run does nothing.

#### 4.A.4 — UI standards

- Fully typed React — zero `any` in any file you wrote.
- Forms use `useForm` from `@inertiajs/react` with inline field-level validation errors.
- Server-side pagination, server-side search, server-side filter (don’t ship 1,000 rows to the browser).

---

### §4.B — System-level requirements (60% of score — the differentiator)

This section is where most candidates fall short. An AI can scaffold §4.A in 20 minutes. This section is what we actually hire on. Read each item carefully, and document your approach in the README — if we can’t tell what you did and why, it doesn’t count.

If you’ve never done one of these before, say so in the README and explain your best attempt. An honest “here’s what I tried, here’s what I’d do with more time” scores better than a silent gap.

#### 4.B.1 — Background jobs (MUST)

All third-party HTTP calls happen inside `FetchProfileJob`. No synchronous `Http::get()` in controllers.

**What this means:** the controller dispatches the job and returns immediately. The worker (running `php artisan queue:work`) picks it up and does the real work. The UI shows a “pending” status until the job completes.

**Status machine:** `pending → fetching → fetched` or `pending → fetching → failed` (store the error message).

#### 4.B.2 — Concurrency safety (MUST) — ⭐ critical

**The scenario:** Two workers pick up the same `FetchProfileJob` for the same handle at the same millisecond (this happens in production — the scheduler is not magic).

**What we want:** exactly one HTTP call is made. The other worker sees the lock and does nothing, or sleeps and retries.

Pick one approach, and justify it in the README:

| Approach | Pros | Watch out for |
|---|---|---|
| Postgres advisory lock — `SELECT pg_try_advisory_lock(profile_id)` | Fast, tied to DB connection | Must release on job exit (including crashes) |
| `SELECT ... FOR UPDATE SKIP LOCKED` | Clean, idiomatic Postgres | Needs wrapping transaction |
| Partial unique index — unique on `(profile_id)` where `status = 'fetching'` | Lets the DB enforce it | Handle the insert conflict gracefully |

**Not acceptable alone:** `Cache::lock('key', 10)` with no reasoning about TTL vs job runtime. If you use a Redis lock, explain what happens when the job exceeds the TTL.

You must prove it works with an automated test — see §4.B.9.

#### 4.B.3 — Rate limiting + quota tracking (MUST)

**The scenario:** YouTube gives you 10,000 quota units per day. Apify gives you $5/month. If you burn through it, the product breaks.

**What you build:** a token-bucket (or leaky-bucket) limiter that the job checks before making the HTTP call. If the bucket is empty:

- The job does not count this as a failed attempt.
- The job re-dispatches itself with an exponential delay (1m, 2m, 4m…).

**For YouTube specifically:** keep a Redis counter of “quota units consumed today,” keyed on the IST date (`quota:2026-04-21`). Refuse to dispatch when within 10% of the ceiling. Log the stop — don’t fail silently.

**Quick primer on token bucket:** imagine a bucket that holds 100 tokens. Each API call removes 1. The bucket refills at 10/min. If empty, the job waits. 5-min video — search “token bucket algorithm” if you’re new to this.

#### 4.B.4 — Retry classification (MUST)

Not all errors deserve a retry. Be deliberate:

| Error | Classify as | What to do |
|---|---|---|
| 5xx, connection timeout, 429 Too Many Requests | Retriable | Exponential backoff with jitter, max 5 attempts |
| 404 Not Found | Fatal | Mark profile failed, no retries — the handle doesn’t exist |
| 401 Unauthorized | Fatal | Mark failed, no retries — your API key is broken, stop wasting quota |
| Validation error / bad payload | Fatal | failed, no retries |

**HTTP client:** set an explicit connect timeout AND read timeout. Explain your numbers in the README (e.g. “connect 3s, read 15s because the IG scraper takes ~8s on a cold run”).

#### 4.B.5 — Circuit breaker (MUST)

**The scenario:** the third-party API is down for 5 minutes. Without a circuit breaker, you pile up hundreds of failing jobs that retry in a hot loop.

**What you build:** after 10 consecutive failures, a “circuit” opens for 2 minutes. During that window, new jobs are deferred (re-dispatched for later), not retried. After 2 minutes, one test job is allowed through. If it works, the circuit closes.

**Rules:**

- Implement this yourself with Redis (counter + timestamp). No `composer require`-ing a package.
- Document the state machine in the README (a tiny diagram is perfect).

#### 4.B.6 — Webhook endpoint with HMAC + replay protection (MUST)

Even if your chosen provider doesn’t have webhooks, simulate one: build a `POST /webhooks/{provider}` endpoint that:

1. Reads an HMAC signature from a header (e.g. `X-Webhook-Signature`).
2. Verifies the signature using a shared secret stored in `.env`.
3. Rejects duplicate requests within 24h (store each request’s ID/nonce in Redis; reject repeats).
4. Returns 200 in under 2 seconds — the actual work is pushed to a queue.

Include a `tests/` test that hits this endpoint with (a) a valid signature, (b) an invalid signature, and (c) a replayed request.

#### 4.B.7 — Database engineering (MUST)

**Schema sanity:**

- At least two tables: `profiles` and `profile_snapshots` (1:N).
- Foreign keys with `onDelete` behavior specified.
- All timestamps use `timestamptz` (timestamp WITH time zone), not plain `timestamp`. This is scored under DB engineering below, not an instant-reject — but it’s an easy point to get right.
- Store times in UTC. Convert to IST (Asia/Kolkata) only when rendering for the user.
- Username uniqueness enforced at the DB level with a partial unique index on the lowercase value (don’t rely on app-layer lowercasing).

**Index proof — required in README:**

1. Seed the DB with ≥ 1,000 profiles and ≥ 10,000 snapshots.
2. Run `EXPLAIN ANALYZE` on your watchlist list query before adding your index — paste the plan in the README.
3. Add a composite index such as `(status, last_refreshed_at DESC) INCLUDE (username)`.
4. Run `EXPLAIN ANALYZE` after — paste the plan.
5. We want to see Seq Scan → Index Scan / Bitmap Index Scan in the diff.

**Transactional integrity:** Writing a snapshot row AND updating the parent profile (`last_refreshed_at`, `followers_count`) must happen in one DB transaction. If the worker crashes mid-write, the data must not split.

**Time-series query:** “Show me the last 30 days of snapshots for this profile” must be efficient (uses an index). Show the query and plan in the README.

#### 4.B.8 — No N+1 queries (MUST)

Install `barryvdh/laravel-debugbar` in dev mode. Open the watchlist page. Take a screenshot showing ≤ 3 SQL queries regardless of how many rows are on the page. Commit the screenshot to `docs/` and link it in the README.

If you see 50 queries for a 50-row page, that’s an N+1 bug. Use eager loading (`->with()`) or a join to fix it.

#### 4.B.9 — Tests (MUST)

**Minimum test suite:**

1. **Feature test** — hits the Inertia watchlist endpoint, asserts the props contain the expected profile.
2. **Unit test** — tests your service / action class in isolation (e.g. retry classification logic).
3. **Job test** — uses `Queue::fake()` to assert the job was dispatched on the right trigger.
4. **Concurrency test** — dispatches two jobs for the same profile simultaneously and asserts only one HTTP call was made. (Use `Http::fake()` and count requests.)
5. **Webhook test** — valid sig, invalid sig, replay — all three cases.

“At least 3 tests” is a floor, not a target. Five solid tests > twenty auto-generated ones.

#### 4.B.10 — Observability (MUST)

**Structured logging:** every job logs a single JSON line per run with these fields:

```json
{"job_id":"...","profile_id":42,"attempt":1,"duration_ms":1234,"outcome":"success"}
```

**Log level:** `info` on success, `warning` on retriable failure, `error` on fatal.

**Health endpoint — `GET /healthz`:**

- Returns 200 only if: DB reachable + Redis reachable + queue worker processed a job in the last 5 minutes.
- Returns 503 with a JSON body like `{"status":"degraded","failing":["queue"]}` otherwise.

---

## 5. Bonus (optional — max +10%)

Only if everything in §4 is solid. A polished base beats a broken bonus.

Pick one:

1. **Self-chaining batch job:** the refresh job processes 50 profiles, then re-dispatches itself (delay 1 min) if stale profiles remain AND quota remains AND the clock is within a configured window. Must survive worker restart.
2. **Horizontal scaling proof:** run 3 queue workers in parallel against the same DB and prove via a test that no profile is fetched twice. Provide the test command.
3. **Prometheus metrics:** `/metrics` endpoint in Prometheus text format with `job_runs_total`, `job_duration_p95_ms`, `job_failures_total` by outcome. Write the formatter yourself; no packages.

---

## 6. What to submit

Email **careers@exhibit.co.in** with subject `FindYourInfluencer Assignment — <Your Full Name>` containing:

- [ ] Public GitHub repo URL — meaningful commit history, not one giant “initial commit”
- [ ] Seed command — a single `php artisan db:seed` (or documented script) that populates at least 3 sample profiles, so we can run locally in minutes
- [ ] Loom video (≤ 5 minutes): show the UI working, open one controller, open the job file, run `php artisan test` on camera
- [ ] Total hours spent (be honest — we use this to calibrate, not to judge)
- [ ] Which API provider you chose
- [ ] Deployed URL — optional, not required. If you deploy, great; if not, GitHub + a clean local setup is enough. We will clone and run it ourselves.

**Your README must include:**

- Setup instructions (a new dev should clone and run in under 10 min)
- `.env.example` committed, `.env` not committed
- Which concurrency approach you picked and why (§4.B.2)
- `EXPLAIN ANALYZE` before/after (§4.B.7)
- The N+1 screenshot (§4.B.8)
- Circuit breaker state diagram (§4.B.5)
- 2 conscious trade-offs you made, with reasoning
- Anything you skipped and why

---

## 7. How we score

| Area | Weight | What we look for |
|---|---|---|
| Core features work end-to-end | 40% | All of §4.A works without prodding |
| Concurrency + locking | 15% | Approach works + test proves it + README explains why |
| Rate limit + quota + circuit breaker | 15% | Token bucket checked before HTTP; quota keyed by IST date; circuit opens cleanly |
| DB engineering | 10% | `timestamptz`, EXPLAIN diff, composite index, transactional snapshot write |
| Retry classification + webhook + observability | 10% | Fatal vs retriable; HMAC + replay; structured JSON logs; `/healthz` |
| Code quality + tests + git + README | 10% | Small commits, readable code, no `any`, honest trade-off notes |

---

## 8. Hard rules — instant reject

These are the items we’ve seen candidates skip to save time. Skipping them = we stop reading.

- Blade templates for the main UI.
- Synchronous `Http::get()` inside a controller.
- Committing `.env` or API keys.
- Missing the concurrency guard (§4.B.2) OR using `Cache::lock()` with no TTL/timeout reasoning.
- Missing `EXPLAIN ANALYZE` output from the README.
- Retry logic that retries 401 or 404.
- No tests, or only auto-generated stubs.
- Final submission DB is SQLite.
- AI-generated code the candidate cannot walk through in the interview.

---

## 9. Interview round (for shortlisted candidates)

A 1-hour pairing call. We will:

1. Ask you to add a small feature live to your own code (e.g. a “category” tag on profiles).
2. Introduce a failing test and ask you to debug it.
3. Ask you to walk through your EXPLAIN plan — we’ll point at a line and ask what it means.
4. Ask the whiteboard question: “Two workers pick up the same profile in the same millisecond. Walk me through what happens in your code, at the DB, and in Redis.” If you cannot narrate this clearly, we know you didn’t write the code yourself.

There are no trick questions. We just want to see you think.

---

## 10. FAQ

**Q: Can I use AI / Copilot / Cursor / Claude / ChatGPT?**  
Yes — fully encouraged. Modern engineering uses AI, and we use it every day ourselves. Claude in particular writes excellent Laravel + React code. We do not penalize AI-assisted work. What we grade is: (a) does it actually run, (b) did you verify and test it, and (c) can you explain and modify it in the live interview. If you used AI to scaffold §4.A and then hand-wrote the §4.B concurrency/quota/locking logic yourself, say so in the README — we’ll respect that.

**Q: I’ve never implemented a circuit breaker. Should I skip?**  
No — attempt it, commit your best shot, and write in the README what you’d improve with more time. We’d rather see a rough-but-working attempt than a silent gap.

**Q: My free API tier ran out during testing. What do I do?**  
Swap to a different provider, or implement your `ProfileProvider` behind an interface with a `FakeProfileProvider` for tests. Document this clearly.

**Q: Do I need a fancy UI?**  
No. Clean, aligned, legible shadcn components are plenty. We don’t grade on visual design.

**Q: What if I can’t deploy in time?**  
No problem — deployment is optional. GitHub repo with a clean README + seed command is enough. We will clone and run it locally. If you want to deploy anyway (Railway, Render, etc.), go ahead.

**Q: Can I extend the deadline?**  
Email careers@exhibit.co.in before the deadline with a reason. Life happens — we’re reasonable. Silent missed deadlines = not reviewed.

**Q: How much am I expected to finish?**  
Everything in §4.A + at least 70% of §4.B to be competitive. §4.A alone is not enough to move to the interview round.

**Q: Something in the spec is ambiguous. What do I do?**  
Pick a reasonable interpretation, document it in the README under “Assumptions,” and move on. This is how real tickets work.

---

## 11. Questions or clarifications

Email **careers@exhibit.co.in** — we reply within 24 IST working hours.

Good luck. We read every submission carefully, and we’re rooting for you.

— The Exhibit Social Engineering Team
