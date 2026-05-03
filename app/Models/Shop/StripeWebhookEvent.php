<?php

namespace App\Models\Shop;

use Illuminate\Database\Eloquent\Model;

class StripeWebhookEvent extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = [
        'created_at' => 'datetime',
    ];
}
