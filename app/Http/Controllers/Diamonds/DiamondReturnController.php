<?php

namespace App\Http\Controllers\Diamonds;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The landing page for the crypto checkout tab (DiamondCryptoCheckoutController
 * sets this as the hosted session's success_url / cancel_url). It's purely
 * informational: diamonds are credited by the signature-verified webhook, not
 * here, so this page never touches Stripe or the order - it just tells the
 * player what happened and to head back to their game tab.
 *
 * Public by design - Stripe redirects the customer's browser here, and a
 * confirmation message shouldn't be gated behind a fresh login or blocked
 * during maintenance.
 */
class DiamondReturnController extends Controller
{
    public function __invoke(Request $request): View
    {
        // Only 'canceled' is treated specially; anything else (including a
        // missing/garbage value) shows the success message, since Stripe only
        // sends the browser to success_url after a completed confirmation.
        $canceled = $request->query('status') === 'canceled';

        return view('diamonds.return', [
            'canceled' => $canceled,
        ]);
    }
}
