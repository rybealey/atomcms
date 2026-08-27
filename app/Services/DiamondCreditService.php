<?php

namespace App\Services;

use App\Models\User;
use App\Models\WebsiteDiamondOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Credits a paid diamonds order onto the emulator's shared `users.vip_points`
 * column. Standalone from the CMS shop system's website_balance and its
 * force-disconnect delivery model - this never disconnects the player.
 */
class DiamondCreditService
{
    /**
     * Deliberately typed to the CONCRETE RconService (the Arcturus/JSON-
     * dialect client), not the driver-selected Rcon contract. The deployed
     * EMULATOR_DRIVER=plus binds Rcon::class to PlusRconService, whose wire
     * format is fire-and-forget and cannot represent a real ack - and its
     * givepoints translator only understands a CurrencyTypes enum for
     * 'type', not the raw int this class sends, which throws past a narrow
     * catch. A paid diamonds purchase needs a real ack (or a real failure we
     * can catch) to decide between "credited" and "fall back to the DB
     * increment", so this always talks to the JSON-dialect client the
     * PlusEMU emulator now speaks, regardless of which driver is configured
     * for the rest of the app.
     */
    public function __construct(
        private readonly RconService $rcon,
    ) {}

    public function credit(WebsiteDiamondOrder $order): void
    {
        $user = User::find($order->user_id);

        if (! $user) {
            // Orphan order: the user row is gone (e.g. account deleted after
            // checkout started). Nothing to credit; log for support.
            Log::warning('Diamond order paid for a user that no longer exists.', [
                'order_id' => $order->id,
                'user_id' => $order->user_id,
            ]);

            return;
        }

        if ($user->online) {
            try {
                $response = $this->rcon->sendCommand('givepoints', [
                    'user_id' => $user->id,
                    'points' => $order->diamonds,
                    'type' => 5, // diamonds (CurrencyTypes::Diamonds)
                ]);

                if ($response->successful()) {
                    return; // ack received: emulator updated memory + DB + purse
                }

                // Well-formed response but the emulator rejected it (e.g. its
                // in-memory `online` flag was stale and the user had already
                // disconnected). Route through the same fallback as a
                // connection failure below.
                $this->logFallbackWarning($order, $user->id, $response->status, $response->message);
            } catch (Throwable $exception) {
                // Broad on purpose: this covers the expected
                // RconConnectionException (socket/timeout/malformed-JSON
                // failures) AND any other rcon-layer surprise (e.g. an
                // unexpected wire response shape). A paid order must never
                // let an exception here escape uncredited - every failure
                // mode has to reach the DB fallback below.
                //
                // Caveat shared with the non-ack branch above: if the RCON
                // path fails for an ONLINE user, the DB increment below can
                // still be overwritten by that user's logout write-back,
                // since the emulator flushes its in-memory purse to the DB
                // on disconnect. We deliberately do not force-disconnect the
                // user to dodge this - that is the CMS shop's approach and
                // it is off limits here. Instead we log a warning naming the
                // order id so a support script can reconcile afterwards.
                $this->logFallbackWarning(
                    $order,
                    $user->id,
                    null,
                    null,
                    $exception::class,
                    $exception->getMessage(),
                );
            }
        }

        DB::table('users')->where('id', $user->id)->increment('vip_points', $order->diamonds);
    }

    private function logFallbackWarning(
        WebsiteDiamondOrder $order,
        int $userId,
        ?int $rconStatus,
        ?string $rconMessage,
        ?string $exceptionClass = null,
        ?string $exceptionMessage = null,
    ): void {
        Log::warning('RCON givepoints did not succeed for an online user; DB fallback may be overwritten by their logout write-back.', [
            'order_id' => $order->id,
            'user_id' => $userId,
            'diamonds' => $order->diamonds,
            'rcon_status' => $rconStatus,
            'rcon_message' => $rconMessage,
            'exception_class' => $exceptionClass,
            'exception_message' => $exceptionMessage,
        ]);
    }
}
