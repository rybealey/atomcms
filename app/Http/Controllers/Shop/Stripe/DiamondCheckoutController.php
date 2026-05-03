<?php

namespace App\Http\Controllers\Shop\Stripe;

use App\Http\Controllers\Controller;
use App\Models\Shop\DiamondStripeTransaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Stripe\Checkout\Session as StripeCheckoutSession;
use Stripe\Exception\ApiErrorException;
use Stripe\Stripe;

class DiamondCheckoutController extends Controller
{
    public function createSession(Request $request): JsonResponse
    {
        $config = config('stripe.diamonds');
        $step = (int) $config['amount_step'];

        $data = $request->validate([
            'amount' => [
                'required',
                'integer',
                'min:'.(int) $config['min_amount'],
                'max:'.(int) $config['max_amount'],
                static fn ($attr, $value, $fail) => $value % $step === 0
                    ? null
                    : $fail("The {$attr} must be a multiple of {$step}."),
            ],
        ]);

        $user = $request->user();
        $diamonds = (int) $data['amount'];
        $cents = $diamonds * (int) config('stripe.diamonds.rate_usd_cents');

        Stripe::setApiKey(config('stripe.secret'));

        try {
            $session = StripeCheckoutSession::create([
                'mode' => 'payment',
                'client_reference_id' => (string) $user->id,
                'metadata' => [
                    'user_id' => (string) $user->id,
                    'diamond_amount' => (string) $diamonds,
                ],
                'payment_intent_data' => [
                    'metadata' => [
                        'user_id' => (string) $user->id,
                        'diamond_amount' => (string) $diamonds,
                    ],
                ],
                'line_items' => [[
                    'quantity' => 1,
                    'price_data' => [
                        'currency' => 'usd',
                        'unit_amount' => $cents,
                        'product_data' => [
                            'name' => number_format($diamonds).' Diamonds',
                            'description' => 'In-game currency for '.setting('hotel_name'),
                        ],
                    ],
                ]],
                'success_url' => route('stripe.diamonds.success').'?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => route('stripe.diamonds.cancel').'?session_id={CHECKOUT_SESSION_ID}',
            ]);
        } catch (ApiErrorException $e) {
            Log::error('Stripe Checkout session create failed', [
                'user_id' => $user->id,
                'diamonds' => $diamonds,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['message' => __('Could not start payment')], 502);
        }

        DiamondStripeTransaction::create([
            'user_id' => $user->id,
            'checkout_session_id' => $session->id,
            'amount_diamonds' => $diamonds,
            'amount_usd_cents' => $cents,
            'status' => DiamondStripeTransaction::STATUS_PENDING,
        ]);

        return response()->json([
            'url' => $session->url,
            'session_id' => $session->id,
        ]);
    }

    public function success(Request $request): View
    {
        return view('shop.stripe-success');
    }

    public function cancel(Request $request): View
    {
        $sessionId = $request->query('session_id');
        if ($sessionId) {
            DiamondStripeTransaction::where('checkout_session_id', $sessionId)
                ->where('user_id', $request->user()?->id)
                ->where('status', DiamondStripeTransaction::STATUS_PENDING)
                ->update(['status' => DiamondStripeTransaction::STATUS_CANCELLED]);
        }

        return view('shop.stripe-cancel');
    }
}
