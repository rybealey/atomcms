<?php

namespace App\Support;

/**
 * URLs for the hotel's own nitro-imager, in habbo-imaging style.
 *
 * Every avatar on the website is a real render of the player's figure - the
 * same sprites the game draws - so the parameters here are the imager's own:
 * direction, head_direction, headonly, size (s/m/l), action, gesture and
 * img_format (gif animates every frame of an action).
 */
final class Imager
{
    /** @param  array<string, string|int>  $params */
    public static function figure(string $look, array $params = []): string
    {
        $base = (string) setting('avatar_imager', '/imaging/?figure=');

        $query = http_build_query(array_filter($params, fn (mixed $value): bool => $value !== null && $value !== ''));

        return $base . rawurlencode($look) . ($query !== '' ? '&' . $query : '');
    }

    /** A full-body standing figure, facing the viewer's right. */
    public static function body(string $look, string $size = 'm', int $direction = 2): string
    {
        return self::figure($look, ['direction' => $direction, 'head_direction' => $direction, 'size' => $size]);
    }

    /** Head and shoulders only. */
    public static function head(string $look, string $size = 'm', int $direction = 2): string
    {
        return self::figure($look, ['direction' => $direction, 'head_direction' => $direction, 'headonly' => 1, 'size' => $size]);
    }

    /** An animated walk, as a GIF of every walk frame. Direction 2 walks right, 6 walks left. */
    public static function walking(string $look, int $direction = 2, string $size = 's'): string
    {
        return self::figure($look, ['direction' => $direction, 'head_direction' => $direction, 'action' => 'wlk', 'size' => $size, 'img_format' => 'gif']);
    }
}
