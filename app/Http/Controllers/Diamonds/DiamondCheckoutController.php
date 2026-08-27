<?php

namespace App\Http\Controllers\Diamonds;

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

class DiamondCheckoutController extends Controller
{
    public function __invoke(DiamondCheckoutFormRequest $request): JsonResponse
    {
        $user = AuthenticatedUser::from($request);
        $diamonds = $request->integer('diamonds');

        try {
            Stripe::setApiKey(config('services.stripe.secret'));

            $session = Session::create([
                'ui_mode' => 'embedded_page',
                'mode' => 'payment',
                // Checkout always collects an email; prefilling the account's
                // address removes the field from the form entirely. Skip
                // invalid stored addresses - Stripe rejects them at create.
                ...$this->customerEmail($user),
                'redirect_on_completion' => 'never',
                'line_items' => [[
                    'quantity' => 1,
                    'price_data' => [
                        'currency' => 'usd',
                        'unit_amount' => $diamonds, // 1 diamond = 1 cent
                        'product_data' => ['name' => $diamonds . ' Diamonds'],
                    ],
                ]],
                // Stripe metadata values must be strings.
                'metadata' => [
                    'user_id' => (string) $user->id,
                    'diamonds' => (string) $diamonds,
                ],
            ]);
        } catch (ApiErrorException $exception) {
            Log::warning('Stripe checkout session creation failed.', [
                'user_id' => $user->getKey(),
                'diamonds' => $diamonds,
                'exception_class' => $exception::class,
                'exception_message' => $exception->getMessage(),
            ]);

            return $this->jsonResponse([
                'message' => __('Unable to start checkout. Please try again shortly.'),
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

        return $this->jsonResponse([
            'clientSecret' => $session->client_secret,
            'publishableKey' => config('services.stripe.key'),
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
