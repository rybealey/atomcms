<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustHosts as Middleware;

class TrustHosts extends Middleware
{
    /**
     * Get the host patterns that should be trusted.
     *
     * @return array<int, string|null>
     */
    public function hosts(): array
    {
        return [
            $this->allSubdomainsOfApplicationUrl(),
            // nginx's own pause poll (docker/web/njs/asset-limit.js) calls in
            // from inside the web container as http://127.0.0.1/.
            '^127\.0\.0\.1$',
        ];
    }
}
