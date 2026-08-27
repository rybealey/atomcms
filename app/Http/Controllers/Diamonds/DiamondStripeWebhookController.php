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

class DiamondStripeWebhookController extends Controller
{
    public function __invoke(Request $request, DiamondCreditService $credit): JsonResponse
    {
        try {
            $event = Webhook::constructEvent(
                $request->getContent(),
                (string) $request->header('Stripe-Signature'),
                config('services.stripe.webhook_secret'),
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

        DB::transaction(function () use ($session, $credit): void {
            $order = WebsiteDiamondOrder::where('stripe_session_id', $session->id)
                ->lockForUpdate()
                ->first();

            // Missing order, or already handled by a prior delivery of this
            // event (Stripe may retry): idempotent no-op.
            if (! $order || $order->status !== WebsiteDiamondOrder::STATUS_PENDING) {
                return;
            }

            $order->update([
                'status' => WebsiteDiamondOrder::STATUS_PAID,
                'paid_at' => now(),
            ]);

            $credit->credit($order);
        });
    }
}
