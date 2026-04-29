<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Qirolab\Theme\Theme;
use Symfony\Component\HttpFoundation\Response;

class SetThemeMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $theme = setting('theme');
        $parent = config('theme.parent');

        if (empty($theme) || $theme === '1') {
            $theme = config('theme.active', 'atom');
        }

        Theme::set($theme, $theme !== $parent ? $parent : null);

        return $next($request);
    }
}
