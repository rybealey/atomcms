<?php

namespace App\Http\Controllers\Shop;

use App\Actions\SendCurrency;
use App\Http\Controllers\Controller;
use App\Models\Shop\WebsiteStripeTransaction;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\Webhook;

class StripeController extends Controller
{
    private StripeClient $stripe;

    public function __construct()
    {
        $this->stripe = new StripeClient(config('stripe.secret_key'));
    }

    public function checkout(Request $request): JsonResponse
    {
        $min = (int) config('stripe.min_diamonds');
        $max = (int) config('stripe.max_diamonds');

        $data = $request->validate([
            'diamonds' => "required|integer|min:{$min}|max:{$max}",
        ]);

        $user = $request->user();
        $diamonds = (int) $data['diamonds'];
        $amountCents = $diamonds * (int) config('stripe.rate_cents_per_diamond');
        $currency = strtolower(config('stripe.currency', 'USD'));

        $session = $this->stripe->checkout->sessions->create([
            'mode' => 'payment',
            'line_items' => [[
                'price_data' => [
                    'currency' => $currency,
                    'product_data' => [
                        'name' => number_format($diamonds).' Diamonds',
                        'description' => 'For '.$user->username.' on '.setting('hotel_name'),
                    ],
                    'unit_amount' => $amountCents,
                ],
                'quantity' => 1,
            ]],
            'metadata' => [
                'user_id'  => (string) $user->id,
                'username' => (string) $user->username,
                'diamonds' => (string) $diamonds,
            ],
            'client_reference_id' => (string) $user->id,
            'success_url' => route('stripe.success'),
            'cancel_url'  => route('stripe.cancelled'),
        ]);

        WebsiteStripeTransaction::create([
            'user_id' => $user->id,
            'checkout_session_id' => $session->id,
            'status' => 'pending',
            'diamonds' => $diamonds,
            'amount_cents' => $amountCents,
            'currency' => strtoupper($currency),
        ]);

        return response()->json(['url' => $session->url]);
    }

    public function webhook(Request $request, SendCurrency $sendCurrency): Response
    {
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature');
        $secret = config('stripe.webhook_secret');

        try {
            $event = Webhook::constructEvent($payload, $signature, $secret);
        } catch (\UnexpectedValueException | SignatureVerificationException $e) {
            Log::warning('Stripe webhook rejected', ['error' => $e->getMessage()]);
            return response('', 400);
        }

        if (in_array($event->type, ['checkout.session.completed', 'checkout.session.async_payment_succeeded'], true)) {
            $this->handleCompletedSession($event, $sendCurrency);
        } else {
            Log::info('Stripe webhook received (no handler)', ['type' => $event->type, 'id' => $event->id]);
        }

        return response('', 200);
    }

    public function success(): \Illuminate\View\View
    {
        return view('shop.stripe.success');
    }

    public function cancelled(): \Illuminate\View\View
    {
        return view('shop.stripe.cancelled');
    }

    private function handleCompletedSession($event, SendCurrency $sendCurrency): void
    {
        $session = $event->data->object;
        $sessionId = $session->id;

        $transaction = WebsiteStripeTransaction::where('checkout_session_id', $sessionId)->first();
        if ($transaction === null) {
            Log::warning('Stripe webhook: no local transaction', ['session' => $sessionId]);
            return;
        }

        if ($transaction->status === 'completed') {
            return;
        }

        $userId = (int) ($session->metadata['user_id'] ?? 0);
        $diamonds = (int) ($session->metadata['diamonds'] ?? 0);

        if ($userId <= 0 || $diamonds <= 0) {
            $transaction->update(['status' => 'failed', 'event_id' => $event->id]);
            Log::warning('Stripe webhook: bad metadata', ['session' => $sessionId, 'metadata' => $session->metadata]);
            return;
        }

        $user = User::find($userId);
        if ($user === null) {
            $transaction->update(['status' => 'failed', 'event_id' => $event->id]);
            Log::warning('Stripe webhook: user not found', ['user_id' => $userId, 'session' => $sessionId]);
            return;
        }

        DB::transaction(function () use ($transaction, $user, $diamonds, $session, $event, $sendCurrency) {
            $sendCurrency->execute($user, 'diamonds', $diamonds);
            $transaction->update([
                'status' => 'completed',
                'event_id' => $event->id,
                'payment_intent_id' => $session->payment_intent ?? null,
            ]);
        });

        Log::info('Stripe webhook: diamonds awarded', [
            'user_id' => $user->id,
            'diamonds' => $diamonds,
            'session' => $sessionId,
        ]);
    }
}
