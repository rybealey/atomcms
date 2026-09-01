<?php

namespace App\Http\Controllers\Diamonds;

use App\Contracts\Rcon;
use App\Http\Controllers\Controller;
use App\Http\Requests\Diamonds\DiamondCheckoutFormRequest;
use App\Models\User;
use App\Models\WebsiteDiamondOrder;
use App\Support\AuthenticatedUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Stripe\Checkout\Session;
use Stripe\Exception\ApiErrorException;
use Stripe\Stripe;

/**
 * Crypto (stablecoin) purchases run through a Stripe-HOSTED Checkout page
 * opened in a new browser tab, NOT the embedded form the card flow uses
 * (see DiamondCheckoutController). Crypto is a redirect-based method - paying
 * sends the customer out to crypto.stripe.com - and the game client is served
 * inside an <iframe> (see the themed client/nitro.blade.php views), where
 * embedded Checkout's redirect can't cleanly reach the top window. Hosting it
 * and popping it into its own tab sidesteps the iframe entirely. Delivery is
 * identical to the card flow: the same signature-verified webhook credits the
 * order once Stripe reports it paid.
 */
class DiamondCryptoCheckoutController extends Controller
{
    /**
     * How long the player is shielded (passive status) after launching the
     * crypto tab, mirroring the card flow - they've stepped away to a wallet,
     * so they shouldn't be attackable in-game while they're gone.
     */
    private const PASSIVE_SECONDS = 300;

    public function __construct(private readonly Rcon $rcon) {}

    public function __invoke(DiamondCheckoutFormRequest $request): JsonResponse
    {
        $user = AuthenticatedUser::from($request);
        $diamonds = $request->integer('diamonds');

        try {
            Stripe::setApiKey(config('services.stripe.secret'));

            // Hosted page (the default ui_mode) so the redirect-based crypto
            // flow works. Restricted to the 'crypto' method - this session is
            // reached only from the dedicated "Pay with crypto" button, so it
            // stays a pure crypto checkout instead of re-offering cards the
            // player already declined on the embedded form.
            $session = Session::create([
                'mode' => 'payment',
                'payment_method_types' => ['crypto'],
                // Prefill removes the email field from the hosted page; skip an
                // invalid stored address (Stripe rejects it at create).
                ...$this->customerEmail($user),
                'line_items' => [[
                    'quantity' => 1,
                    'price_data' => [
                        // Crypto requires USD line items - the same currency the
                        // card flow already uses. 1 diamond = 1 cent.
                        'currency' => 'usd',
                        'unit_amount' => $diamonds,
                        'product_data' => ['name' => $diamonds . ' Diamonds'],
                    ],
                ]],
                // Stripe metadata values must be strings.
                'metadata' => [
                    'user_id' => (string) $user->id,
                    'diamonds' => (string) $diamonds,
                ],
                // Lands the new tab on a standalone "you're done, head back to
                // the game" page - never inside the game route, which would
                // boot a second client in the tab.
                'success_url' => route('diamonds.return') . '?status=success',
                'cancel_url' => route('diamonds.return') . '?status=canceled',
            ]);
        } catch (ApiErrorException $exception) {
            Log::warning('Stripe crypto checkout session creation failed.', [
                'user_id' => $user->getKey(),
                'diamonds' => $diamonds,
                'exception_class' => $exception::class,
                'exception_message' => $exception->getMessage(),
            ]);

            return $this->jsonResponse([
                'message' => __('Unable to start crypto checkout. Please try again shortly.'),
            ], 422);
        }

        WebsiteDiamondOrder::create([
            'user_id' => $user->id,
            'diamonds' => $diamonds,
            'amount_cents' => $diamonds,
            'currency' => 'usd',
            'stripe_session_id' => $session->id,
            'status' => WebsiteDiamondOrder::STATUS_PENDING,
        ]);

        // Best effort - checkout must not fail because the emulator is
        // unreachable (dispatchCommand already swallows and logs transport
        // errors).
        $this->rcon->grantPassive($user, self::PASSIVE_SECONDS);

        return $this->jsonResponse([
            'url' => $session->url,
        ]);
    }

    /**
     * @return array{customer_email?: string}
     */
    private function customerEmail(User $user): array
    {
        $email = $user->mail;

        if (blank($email) || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return [];
        }

        return ['customer_email' => $email];
    }
}
