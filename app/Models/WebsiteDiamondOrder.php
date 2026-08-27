<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A standalone Stripe-backed diamonds purchase, tracked from checkout-session
 * creation through webhook-confirmed crediting. Not part of the website_balance
 * shop system.
 *
 * @property int $id
 * @property int $user_id
 * @property int $diamonds
 * @property int $amount_cents
 * @property string $currency
 * @property string $stripe_session_id
 * @property string $status
 * @property Carbon|null $paid_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebsiteDiamondOrder newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebsiteDiamondOrder newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebsiteDiamondOrder query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebsiteDiamondOrder whereAmountCents($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebsiteDiamondOrder whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebsiteDiamondOrder whereCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebsiteDiamondOrder whereDiamonds($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebsiteDiamondOrder whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebsiteDiamondOrder wherePaidAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebsiteDiamondOrder whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebsiteDiamondOrder whereStripeSessionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebsiteDiamondOrder whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebsiteDiamondOrder whereUserId($value)
 *
 * @mixin \Eloquent
 */
class WebsiteDiamondOrder extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'user_id',
        'diamonds',
        'amount_cents',
        'currency',
        'stripe_session_id',
        'status',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'diamonds' => 'integer',
            'amount_cents' => 'integer',
            'paid_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
