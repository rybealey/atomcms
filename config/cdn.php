<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Tier B asset CDN signed-cookie secret
    |--------------------------------------------------------------------------
    |
    | Shared secret between AtomCMS (which mints the cdn_h cookie) and the
    | nginx cdn.<domain> vhost (which validates it via secure_link_md5). Must
    | be the SAME value in both places. Empty in dev — the cdn vhost is
    | prod-only, and CdnAssetCookieMiddleware no-ops when this is blank.
    | Generate with `openssl rand -base64 48`. Rotating it invalidates every
    | outstanding asset cookie at once.
    |
    */
    'secret' => env('CDN_SECRET', ''),

    /*
    | How long (seconds) a freshly minted asset cookie is valid. nginx
    | independently enforces this expiry from the cdn_e cookie, so a stale
    | replay is rejected even if the browser keeps sending it.
    */
    'ttl' => (int) env('CDN_COOKIE_TTL', 86400),
];
