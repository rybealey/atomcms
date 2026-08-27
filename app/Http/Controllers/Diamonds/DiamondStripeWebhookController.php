<?php

namespace App\Http\Controllers\Diamonds;

use App\Http\Controllers\Controller;
use App\Models\WebsiteDiamondOrder;
use App\Services\DiamondCreditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Checkout\Session;
use Stripe\Event;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Exception\UnexpectedValueException;
use Stripe\Webhook;
use Throwable;

class DiamondStripeWebhookController extends Controller
{
    public function __invoke(Request $request, DiamondCreditService $credit): JsonResponse
    {
        $webhookSecret = config('services.stripe.webhook_secret');

        if (blank($webhookSecret)) {
            // Never call constructEvent() with an empty secret: Stripe's SDK
            // signs against it, and a blank secret is not a "verification
            // failed" case worth 400-ing (which would look like a spoofed
            // request) - it's a misconfigured deployment. Fail loudly.
            Log::error('Diamonds Stripe webhook secret is not configured; refusing to verify.');

            return $this->jsonResponse(['message' => 'Webhook is not configured.'], 500);
        }

        try {
            $event = Webhook::constructEvent(
                $request->getContent(),
                (string) $request->header('Stripe-Signature'),
                $webhookSecret,
            );
        } catch (UnexpectedValueException|SignatureVerificationException $exception) {
            Log::warning('Diamonds Stripe webhook signature verification failed.', [
                'exception_class' => $exception::class,
            ]);

            return $this->jsonResponse(['message' => 'Invalid payload or signature.'], 400);
        }

        if ($event->type === 'checkout.session.completed') {
            $this->handleCheckoutCompleted($event, $credit);
        }

        // Always 200 on handled (verified) events, including types we don't
        // act on, so Stripe does not retry them forever.
        return $this->jsonResponse([]);
    }

    private function handleCheckoutCompleted(Event $event, DiamondCreditService $credit): void
    {
        /** @var Session $session */
        $session = $event->data->object;

        if ($session->payment_status !== 'paid') {
            return;
        }

        // The transaction only locks the row, checks idempotency and flips
        // the order to paid - it does NOT call the RCON/DB credit. Crediting
        // inside the transaction risked a worse failure mode: if the grant
        // succeeded but the commit then failed (e.g. a deadlock retry
        // exhausting `attempts`), the order would roll back to pending and a
        // Stripe retry would credit the user a second time. With crediting
        // moved after the commit, the fallback chain in DiamondCreditService
        // makes the post-commit credit near-certain (worst case is a plain
        // DB increment); the residual risk is a crash between commit and
        // credit, which leaves a paid-but-uncredited order that a support
        // script can reconcile from the log below - strictly better than a
        // double-credit.
        $order = DB::transaction(function () use ($session): ?WebsiteDiamondOrder {
            $order = WebsiteDiamondOrder::where('stripe_session_id', $session->id)
                ->lockForUpdate()
                ->first();

            // Missing order, or already handled by a prior delivery of this
            // event (Stripe may retry): idempotent no-op.
            if (! $order || $order->status !== WebsiteDiamondOrder::STATUS_PENDING) {
                return null;
            }

            $order->update([
                'status' => WebsiteDiamondOrder::STATUS_PAID,
                'paid_at' => now(),
            ]);

            return $order;
        }, attempts: 3);

        if ($order === null) {
            return;
        }

        try {
            $credit->credit($order);
        } catch (Throwable $exception) {
            // The order is already committed as paid; never let a credit
            // failure bubble into a 500 back to Stripe (that would trigger a
            // retry and risk a double-credit on top of an already-paid
            // order). Log for reconciliation instead.
            Log::error('Diamond order committed as paid but crediting threw.', [
                'order_id' => $order->id,
                'exception_class' => $exception::class,
            ]);
        }
    }
}
