<?php

namespace App\Http\Middleware;

use App\Support\AssetPass;
use Illuminate\Cookie\Middleware\EncryptCookies as Middleware;

class EncryptCookies extends Middleware
{
    /**
     * The names of the cookies that should not be encrypted.
     *
     * @var array<int, string>
     */
    protected $except = [
        // pixelrp: nginx reads these raw (secure_link) - see AssetPass.
        AssetPass::USER,
        AssetPass::EXPIRES,
        AssetPass::TIER,
        AssetPass::SIGNATURE,
    ];
}
