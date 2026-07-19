<?php

namespace App\Http\Controllers;

use App\Jobs\FetchProfileJob;
use App\Models\Profile;
use App\Services\Ops\OpsEventRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Throwable;

class WebhookController extends Controller
{
    public function __invoke(Request $request, string $provider, OpsEventRecorder $ops): JsonResponse
    {
        $event = $ops->start('webhook', 'webhook.'.$provider, meta: [
            'provider' => $provider,
        ]);

        try {
            $secret = (string) config('services.webhook.secret');
            $signature = (string) $request->header('X-Webhook-Signature', '');
            $payload = $request->getContent();
            $expected = hash_hmac('sha256', $payload, $secret);

            if (! hash_equals($expected, $signature)) {
                $ops->finish($event, 'failed', ['outcome' => 'rejected', 'reason' => 'invalid_signature']);
                throw new HttpException(401, 'Invalid webhook signature');
            }

            $eventId = (string) ($request->header('X-Webhook-Id') ?: $request->input('id') ?: '');

            if ($eventId === '') {
                $ops->finish($event, 'failed', ['outcome' => 'rejected', 'reason' => 'missing_id']);
                throw new HttpException(422, 'Missing webhook id');
            }

            $replayKey = "webhook:replay:{$provider}:{$eventId}";

            if (! Redis::set($replayKey, '1', 'EX', 86400, 'NX')) {
                $ops->finish($event, 'failed', ['outcome' => 'rejected', 'reason' => 'replay', 'webhook_id' => $eventId]);
                throw new HttpException(409, 'Replay detected');
            }

            $username = Profile::normalizeUsername((string) $request->input('username', ''));
            $dispatched = false;

            if ($username !== '') {
                $profile = Profile::query()->where('username', $username)->first();

                if ($profile) {
                    FetchProfileJob::dispatch($profile->id);
                    $dispatched = true;
                    $event->update(['profile_id' => $profile->id]);
                }
            }

            $ops->finish($event, 'success', [
                'outcome' => 'accepted',
                'webhook_id' => $eventId,
                'dispatched' => $dispatched,
                'username' => $username !== '' ? $username : null,
            ]);

            return response()->json(['status' => 'accepted'], 200);
        } catch (HttpException $e) {
            throw $e;
        } catch (Throwable $e) {
            $ops->finish($event, 'failed', [
                'outcome' => 'error',
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
