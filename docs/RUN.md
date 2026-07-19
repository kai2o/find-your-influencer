# Run the complete FindYourInfluencer stack (Windows PowerShell)

Prerequisites: PHP 8.2+, Composer, Node, Redis service, PostgreSQL 17.

## One-time setup

```powershell
cd "C:\Users\Admin\OneDrive - Vidyalankar School of Information Technology\CODE\FindYourInfluencer"
copy .env.example .env   # if needed
# Edit .env: DB_*, APIFY_*, GOOGLE_* (optional until you add Google creds)
composer install
npm install
php artisan key:generate
php artisan migrate
php artisan db:seed
npm run build
```

### Google OAuth (when you have credentials)

In Google Cloud Console → APIs & Services → Credentials → OAuth 2.0 Client:

- Authorized redirect URI: `http://127.0.0.1:8000/auth/google/callback`

Add to `.env`:

```env
GOOGLE_CLIENT_ID=your-client-id.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=your-client-secret
GOOGLE_REDIRECT_URI=http://127.0.0.1:8000/auth/google/callback
APP_URL=http://127.0.0.1:8000
```

Then restart `php artisan serve`.

## Start the complete system (4 terminals)

```powershell
# Terminal 1 — HTTP
php artisan serve --host=127.0.0.1 --port=8000

# Terminal 2 — Queue (Apify jobs)
php artisan queue:work --tries=3 --timeout=120

# Terminal 3 — Scheduler (assignment: every 10 minutes)
# The command profiles:refresh-stale runs on :00/:10/:20/:30/:40/:50.
# Empty ticks (dispatched_count = 0) are SUCCESS — they mean no profile was
# older than 1 hour (or never refreshed). That is expected, not a failure.
# Overlap-safe via withoutOverlapping + Cache lock.
php artisan schedule:work

# Terminal 4 (optional while developing UI) — Vite HMR
npm run dev
```

Or one-liner using the Composer `dev` script (if you prefer):

```powershell
composer run dev
```

## URLs

- App: http://127.0.0.1:8000  
- Login: http://127.0.0.1:8000/login  
- Health: http://127.0.0.1:8000/healthz  
- Performance: http://127.0.0.1:8000/performance  

Default seed login: `admin@example.com` / `password`

## Benchmark Apify timings

Measures real provider fetch time (API ms) for handles — useful for large accounts like cristiano:

```powershell
# Uses PROFILE_PROVIDER / APIFY_TOKEN from .env
php artisan profiles:benchmark-fetch cristiano nasa natgeo ka_st_bh

# Also write benchmark rows into ops_events (visible on Performance)
php artisan profiles:benchmark-fetch cristiano nasa --persist
```

Duration on Performance is worker wall-clock after the job is picked up (not queue wait). Expand a job/api row to see `job_duration_ms` and `api_duration_ms`.
