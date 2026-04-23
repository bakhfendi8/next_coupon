<?php

namespace App\Jobs;

use App\Models\CouponEvent;
use App\Services\CouponReservationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class CleanExpiredReservationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /*
    |--------------------------------------------------------------------------
    | Queue: low
    | Dispatched by the scheduler every minute via Kernel.php.
    | Finds and cleans up stuck/orphaned coupon reservations.
    |--------------------------------------------------------------------------
    | Two-pass cleanup strategy:
    |
    | Pass 1 — Redis scan
    |   Find keys matching "coupon:reservation:*" with no TTL (-1).
    |   These are reservations that lost their expiry somehow.
    |   Delete them and emit a 'released' event.
    |
    | Pass 2 — MySQL cross-check
    |   Find 'reserved' events in coupon_events older than 10 minutes
    |   with no corresponding 'consumed' or 'released' event.
    |   These are orphans where the Redis key already expired silently.
    |   Emit a 'released' event to close the audit trail.
    |--------------------------------------------------------------------------
    */

    public int    $tries   = 1;  // don't retry cleanup jobs
    public int    $timeout = 60;

    public function __construct()
    {
        $this->onQueue('low');
    }

    public function handle(CouponReservationService $reservations): void
    {
        $released = 0;

        // ── Pass 1: Redis scan for no-TTL keys ────────────────────────────────
        $cursor = '0';
        do {
            [$cursor, $keys] = Redis::scan($cursor, [
                'match' => 'coupon:reservation:*',
                'count' => 100,
            ]);

            foreach ($keys as $key) {
                $ttl = Redis::ttl($key);

                // TTL = -1 means key exists but has no expiry (should never happen).
                if ($ttl === -1) {
                    Redis::del($key);
                    Log::warning('[CleanExpiredReservationsJob] Deleted no-TTL key', ['key' => $key]);

                    // Parse couponId and userId from "coupon:reservation:{couponId}:{userId}"
                    $parts = explode(':', $key);
                    if (count($parts) === 4) {
                        [, , $couponId, $userId] = $parts;
                        RecordCouponEventJob::dispatch(
                            couponId:       (int) $couponId,
                            userId:         (int) $userId,
                            event:          'released',
                            payload:        ['reason' => 'no_ttl_cleanup'],
                            couponKey:      "cleanup:notl:{$couponId}:{$userId}:" . now()->timestamp,
                        )->onQueue('low');
                        $released++;
                    }
                }
                // TTL = -2 means key is gone — already cleaned by Redis expiry. Skip.
                // TTL > 0 means reservation is still valid. Skip.
            }
        } while ($cursor !== '0');

        // ── Pass 2: MySQL orphan check ────────────────────────────────────────
        // Find 'reserved' events older than 10 minutes with no follow-up event.
        $orphans = CouponEvent::where('event', 'reserved')
            ->where('occurred_at', '<', now()->subMinutes(10))
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('coupon_events as ce2')
                    ->whereColumn('ce2.coupon_id', 'coupon_events.coupon_id')
                    ->whereColumn('ce2.user_id',   'coupon_events.user_id')
                    ->whereIn('ce2.event', ['consumed', 'released'])
                    ->where('ce2.occurred_at', '>', DB::raw('coupon_events.occurred_at'));
            })
            ->get();

        foreach ($orphans as $orphan) {
            RecordCouponEventJob::dispatchSync(
                couponId:       $orphan->coupon_id,
                userId:         $orphan->user_id,
                event:          'released',
                payload:        ['reason' => 'ttl_expired_orphan'],
                couponKey:      "{$orphan->coupon_key}:auto_released",
            );
            $released++;

            Log::info('[CleanExpiredReservationsJob] Orphan reservation closed', [
                'coupon_id' => $orphan->coupon_id,
                'user_id'   => $orphan->user_id,
            ]);
        }

        Log::info('[CleanExpiredReservationsJob] Complete', [
            'released' => $released,
        ]);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('[CleanExpiredReservationsJob] Failed', ['error' => $e->getMessage()]);
    }
}