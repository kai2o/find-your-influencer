<?php

namespace App\Http\Controllers;

use App\Enums\ProfileStatus;
use App\Jobs\FetchProfileJob;
use App\Models\Profile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class WatchlistController extends Controller
{
    public function index(Request $request): Response
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'in:pending,fetching,fetched,failed'],
        ]);

        $profiles = Profile::query()
            ->when($validated['q'] ?? null, fn ($q, $search) => $q->where('username', 'like', '%'.strtolower($search).'%'))
            ->when($validated['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->orderByDesc('last_refreshed_at')
            ->orderBy('username')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Profile $profile) => [
                'id' => $profile->id,
                'username' => $profile->username,
                'status' => $profile->status->value,
                'followers_count' => $profile->followers_count,
                'following_count' => $profile->following_count,
                'posts_count' => $profile->posts_count,
                'last_refreshed_at' => $profile->last_refreshed_at?->timezone('Asia/Kolkata')->toIso8601String(),
                'last_error' => $profile->last_error,
            ]);

        return Inertia::render('watchlist/index', [
            'profiles' => $profiles,
            'filters' => [
                'q' => $validated['q'] ?? '',
                'status' => $validated['status'] ?? '',
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('watchlist/create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'username' => ['required', 'string', 'max:100', 'regex:/^@?[A-Za-z0-9._]+$/'],
        ]);

        $username = Profile::normalizeUsername($validated['username']);

        $existing = Profile::query()->where('username', $username)->first();

        if ($existing?->status === ProfileStatus::Fetching) {
            return Redirect::route('watchlist.show', $existing)
                ->with('error', 'A fetch is already in progress for this handle.');
        }

        $profile = Profile::query()->firstOrCreate(
            ['username' => $username],
            [
                'platform' => 'instagram',
                'status' => ProfileStatus::Pending,
            ]
        );

        if ($profile->wasRecentlyCreated || $profile->status === ProfileStatus::Failed || $profile->status === ProfileStatus::Pending) {
            $profile->update(['status' => ProfileStatus::Pending, 'last_error' => null]);
            $this->dispatchFetch($profile->id);

            return Redirect::route('watchlist.show', $profile)
                ->with('success', $this->usesSyncFetch()
                    ? 'Profile fetched.'
                    : 'Profile queued for fetch.');
        }

        return Redirect::route('watchlist.show', $profile)
            ->with('success', 'Handle already on the watchlist.');
    }

    public function show(Profile $profile): Response
    {
        $snapshotModels = $profile->snapshots()
            ->where('captured_at', '>=', now('UTC')->subDays(30))
            ->orderByDesc('captured_at')
            ->get();

        $snapshots = $snapshotModels->map(fn ($s) => [
            'id' => $s->id,
            'followers_count' => $s->followers_count,
            'following_count' => $s->following_count,
            'posts_count' => $s->posts_count,
            'followers_delta' => $s->followers_delta,
            'captured_at' => $s->captured_at->timezone('Asia/Kolkata')->toIso8601String(),
        ]);

        $oldest = $snapshotModels->last();
        $newest = $snapshotModels->first();
        $periodNetDelta = null;

        if ($oldest && $newest && $snapshotModels->count() >= 2) {
            $periodNetDelta = (int) $newest->followers_count - (int) $oldest->followers_count;
        } elseif ($newest && $newest->followers_delta !== null && $snapshotModels->count() === 1) {
            $periodNetDelta = (int) $newest->followers_delta;
        }

        $positiveDeltas = $snapshotModels->filter(fn ($s) => $s->followers_delta !== null && $s->followers_delta > 0);
        $negativeDeltas = $snapshotModels->filter(fn ($s) => $s->followers_delta !== null && $s->followers_delta < 0);

        return Inertia::render('watchlist/show', [
            'profile' => [
                'id' => $profile->id,
                'username' => $profile->username,
                'status' => $profile->status->value,
                'bio' => $profile->bio,
                'profile_picture_url' => $profile->profile_picture_url,
                'followers_count' => $profile->followers_count,
                'following_count' => $profile->following_count,
                'posts_count' => $profile->posts_count,
                'last_refreshed_at' => $profile->last_refreshed_at?->timezone('Asia/Kolkata')->toIso8601String(),
                'last_error' => $profile->last_error,
            ],
            'snapshots' => $snapshots,
            'performance' => [
                'window_days' => 30,
                'snapshot_count' => $snapshotModels->count(),
                'period_net_delta' => $periodNetDelta,
                'largest_gain' => $positiveDeltas->isEmpty() ? null : (int) $positiveDeltas->max('followers_delta'),
                'largest_drop' => $negativeDeltas->isEmpty() ? null : (int) $negativeDeltas->min('followers_delta'),
                'avg_delta' => $snapshotModels->whereNotNull('followers_delta')->avg('followers_delta'),
            ],
        ]);
    }

    public function refetch(Profile $profile): RedirectResponse
    {
        $profile->update([
            'status' => ProfileStatus::Pending,
            'last_error' => null,
        ]);

        $this->dispatchFetch($profile->id);

        return Redirect::route('watchlist.show', $profile)
            ->with('success', $this->usesSyncFetch()
                ? 'Profile re-fetched.'
                : 'Re-fetch queued.');
    }

    /**
     * Fake (or missing Apify token) runs inline for instant UI; live Apify stays queued.
     */
    private function usesSyncFetch(): bool
    {
        $driver = config('services.profile.driver', 'apify');

        return $driver === 'fake' || empty(config('services.apify.token'));
    }

    private function dispatchFetch(int $profileId): void
    {
        if ($this->usesSyncFetch()) {
            FetchProfileJob::dispatchSync($profileId);

            return;
        }

        FetchProfileJob::dispatch($profileId);
    }
}
