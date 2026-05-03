<?php

namespace App\Http\Controllers\Shop\Stripe;

use App\Actions\SendCurrency;
use App\Http\Controllers\Controller;
use App\Models\Shop\DiamondStripeTransaction;
use App\Models\Shop\StripeWebhookEvent;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Event;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use UnexpectedValueException;

class StripeWebhookController extends Controller
{
    public function handle(Request $request, SendCurrency $sendCurrency): Response
    {
        $secret = config('stripe.webhook_secret');
        if (! $secret) {
            Log::error('Stripe webhook hit but STRIPE_WEBHOOK_SECRET is not configured');

            return response('Webhook not configured', 500);
        }

        try {
            $event = Webhook::constructEvent(
                $request->getContent(),
                $request->header('Stripe-Signature') ?? '',
                $secret,
            );
        } catch (UnexpectedValueException $e) {
            Log::warning('Stripe webhook invalid payload', ['error' => $e->getMessage()]);

            return response('Invalid payload', 400);
        } catch (SignatureVerificationException $e) {
            Log::warning('Stripe webhook bad signature', ['error' => $e->getMessage()]);

            return response('Invalid signature', 400);
        }

        $log = StripeWebhookEvent::firstOrCreate(
            ['stripe_event_id' => $event->id],
            ['event_type' => $event->type],
        );

        if (! $log->wasRecentlyCreated) {
            return response('Already processed', 200);
        }

        try {
            match ($event->type) {
                'checkout.session.completed' => $this->onCheckoutCompleted($event, $sendCurrency),
                'checkout.session.expired' => $this->markStatusFromSession($event, DiamondStripeTransaction::STATUS_EXPIRED),
                'payment_intent.payment_failed' => $this->onPaymentFailed($event),
                default => null,
            };
        } catch (\Throwable $e) {
            Log::error('Stripe webhook processing failed', [
                'event_id' => $event->id,
                'event_type' => $event->type,
                'error' => $e->getMessage(),
            ]);

            $log->delete();

            return response('Processing failed', 500);
        }

        return response('OK', 200);
    }

    private function onCheckoutCompleted(Event $event, SendCurrency $sendCurrency): void
    {
        $session = $event->data->object;

        if (($session->payment_status ?? null) !== 'paid') {
            Log::info('Stripe checkout.session.completed without paid status', [
                'session_id' => $session->id,
                'payment_status' => $session->payment_status ?? null,
            ]);

            return;
        }

        DB::transaction(function () use ($session, $sendCurrency) {
            $txn = DiamondStripeTransaction::where('checkout_session_id', $session->id)
                ->lockForUpdate()
                ->first();

            if (! $txn) {
                Log::warning('Stripe checkout.session.completed for unknown session', [
                    'session_id' => $session->id,
                ]);

                return;
            }

            if ($txn->status === DiamondStripeTransaction::STATUS_COMPLETED) {
                return;
            }

            $user = User::find($txn->user_id);
            if (! $user) {
                Log::error('Stripe checkout completed for missing user', [
                    'user_id' => $txn->user_id,
                    'session_id' => $session->id,
                ]);

                return;
            }

            $txn->update([
                'status' => DiamondStripeTransaction::STATUS_COMPLETED,
                'payment_intent_id' => $session->payment_intent ?? null,
            ]);

            $sendCurrency->execute($user, 'diamonds', $txn->amount_diamonds);
        });
    }

    private function onPaymentFailed(Event $event): void
    {
        $intent = $event->data->object;
        $sessionId = $intent->metadata['checkout_session_id'] ?? null;

        $query = DiamondStripeTransaction::query();
        if ($sessionId) {
            $query->where('checkout_session_id', $sessionId);
        } else {
            $query->where('payment_intent_id', $intent->id);
        }

        $query->where('status', DiamondStripeTransaction::STATUS_PENDING)
            ->update(['status' => DiamondStripeTransaction::STATUS_FAILED]);
    }

    private function markStatusFromSession(Event $event, string $status): void
    {
        $session = $event->data->object;

        DiamondStripeTransaction::where('checkout_session_id', $session->id)
            ->where('status', DiamondStripeTransaction::STATUS_PENDING)
            ->update(['status' => $status]);
    }
}
