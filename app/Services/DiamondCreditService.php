<?php

namespace App\Services;

use App\Contracts\Rcon;
use App\Exceptions\RconConnectionException;
use App\Models\User;
use App\Models\WebsiteDiamondOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Credits a paid diamonds order onto the emulator's shared `users.vip_points`
 * column. Standalone from the CMS shop system's website_balance and its
 * force-disconnect delivery model - this never disconnects the player.
 */
class DiamondCreditService
{
    public function __construct(
        private readonly Rcon $rcon,
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
                $this->rcon->sendCommand('givepoints', [
                    'user_id' => $user->id,
                    'points' => $order->diamonds,
                    'type' => 5, // diamonds (CurrencyTypes::Diamonds)
                ]);

                return; // ack received: emulator updated memory + DB + purse
            } catch (RconConnectionException) {
                // Fall through to the DB fallback below. Caveat: if the RCON
                // path fails for an ONLINE user, the DB increment below can
                // still be overwritten by that user's logout write-back,
                // since the emulator flushes its in-memory purse to the DB
                // on disconnect. We deliberately do not force-disconnect the
                // user to dodge this - that is the CMS shop's approach and
                // it is off limits here. Instead we log a warning naming the
                // order id so a support script can reconcile afterwards.
                Log::warning('RCON givepoints failed for an online user; DB fallback may be overwritten by their logout write-back.', [
                    'order_id' => $order->id,
                    'user_id' => $user->id,
                    'diamonds' => $order->diamonds,
                ]);
            }
        }

        DB::table('users')->where('id', $user->id)->increment('vip_points', $order->diamonds);
    }
}
