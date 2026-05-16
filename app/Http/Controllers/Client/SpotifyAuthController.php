<?php

namespace App\Http\Controllers\Client;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Spotify OAuth (Authorization-Code) for the room-102 ("The Muse") music
 * player — Option C, true Web Playback SDK (per-user Premium). The only
 * HTTP tier in the stack, so it owns the redirect flow + token store;
 * mirrors how the Discord link is delivered (a fork migration + a
 * controller). Tokens land on users.spotify_* (added by the
 * 2026_05_16_000001 migration); the pixeltower-rp plugin's SpotifyClient
 * and the Nitro MusicEngine both read this contract.
 *
 * All three routes sit in the authed web group: connect/callback ride the
 * logged-in browser session; token() is called same-origin (cookie) by
 * the in-client MusicEngine.
 */
class SpotifyAuthController extends Controller
{
    private const AUTHORIZE = 'https://accounts.spotify.com/authorize';
    private const TOKEN = 'https://accounts.spotify.com/api/token';
    private const ME = 'https://api.spotify.com/v1/me';
    private const SCOPES = 'streaming user-read-email user-read-private '
        . 'user-modify-playback-state user-read-playback-state';

    public function connect(Request $request)
    {
        $cfg = config('services.spotify');
        if (empty($cfg['client_id']) || empty($cfg['redirect_uri'])) {
            return $this->closePage('Spotify is not configured yet. Ask an admin.');
        }

        $state = Str::random(40);
        $request->session()->put('spotify_oauth_state', $state);

        return redirect()->away(self::AUTHORIZE . '?' . http_build_query([
            'response_type' => 'code',
            'client_id'     => $cfg['client_id'],
            'scope'         => self::SCOPES,
            'redirect_uri'  => $cfg['redirect_uri'],
            'state'         => $state,
        ]));
    }

    public function callback(Request $request)
    {
        if ($request->query('error')) {
            return $this->closePage('Spotify connection was cancelled.');
        }

        $expected = $request->session()->pull('spotify_oauth_state');
        $state = $request->query('state');
        if (!$state || !$expected || !hash_equals($expected, $state)) {
            return $this->closePage('Invalid Spotify session. Please try again.');
        }

        $code = $request->query('code');
        $cfg = config('services.spotify');
        if (!$code || empty($cfg['client_id'])) {
            return $this->closePage('Spotify connection failed. Please try again.');
        }

        $res = Http::asForm()
            ->withBasicAuth($cfg['client_id'], $cfg['client_secret'])
            ->post(self::TOKEN, [
                'grant_type'   => 'authorization_code',
                'code'         => $code,
                'redirect_uri' => $cfg['redirect_uri'],
            ]);
        if (!$res->ok()) {
            return $this->closePage('Spotify rejected the connection. Please try again.');
        }

        $tok = $res->json();
        $access = $tok['access_token'] ?? null;
        $refresh = $tok['refresh_token'] ?? null;
        $expiresIn = (int) ($tok['expires_in'] ?? 3600);
        if (!$access || !$refresh) {
            return $this->closePage('Spotify did not return the expected tokens.');
        }

        $premium = false;
        $me = Http::withToken($access)->get(self::ME);
        if ($me->ok()) {
            $premium = ($me->json('product') === 'premium');
        }

        DB::table('users')->where('id', Auth::id())->update([
            'spotify_access_token'     => $access,
            'spotify_refresh_token'    => $refresh,
            'spotify_token_expires_at' => time() + $expiresIn,
            'spotify_premium'          => $premium ? 1 : 0,
        ]);

        return $this->closePage($premium
            ? 'Spotify connected. Head back to The Muse and enjoy the music.'
            : 'Spotify connected, but this account is not Premium — the Web '
                . 'Playback SDK needs Premium to hear the room music.');
    }

    public function token(Request $request)
    {
        $row = DB::table('users')->where('id', Auth::id())->first([
            'spotify_access_token',
            'spotify_refresh_token',
            'spotify_token_expires_at',
        ]);

        if (!$row || !$row->spotify_refresh_token) {
            return response()->json(['error' => 'not_linked'], 401);
        }

        $now = time();
        if ($row->spotify_access_token && (int) $row->spotify_token_expires_at > $now + 30) {
            return response()->json(['access_token' => $row->spotify_access_token]);
        }

        $cfg = config('services.spotify');
        if (empty($cfg['client_id'])) {
            return response()->json(['error' => 'not_configured'], 503);
        }

        $res = Http::asForm()
            ->withBasicAuth($cfg['client_id'], $cfg['client_secret'])
            ->post(self::TOKEN, [
                'grant_type'    => 'refresh_token',
                'refresh_token' => $row->spotify_refresh_token,
            ]);
        if (!$res->ok()) {
            return response()->json(['error' => 'refresh_failed'], 401);
        }

        $tok = $res->json();
        $access = $tok['access_token'] ?? null;
        if (!$access) {
            return response()->json(['error' => 'refresh_failed'], 401);
        }
        $expiresIn = (int) ($tok['expires_in'] ?? 3600);

        DB::table('users')->where('id', Auth::id())->update([
            'spotify_access_token'     => $access,
            'spotify_token_expires_at' => $now + $expiresIn,
            // Spotify may rotate the refresh token.
            'spotify_refresh_token'    => $tok['refresh_token'] ?? $row->spotify_refresh_token,
        ]);

        return response()->json(['access_token' => $access]);
    }

    private function closePage(string $message)
    {
        $safe = e($message);

        return response(
            '<!doctype html><html><head><meta charset="utf-8">'
            . '<title>Spotify - PixelRP</title><meta name="viewport" '
            . 'content="width=device-width,initial-scale=1">'
            . '<style>body{font-family:Ubuntu,Arial,sans-serif;background:#1F2833;'
            . 'color:#fff;display:flex;min-height:100vh;align-items:center;'
            . 'justify-content:center;text-align:center;padding:24px}'
            . '.c{max-width:420px}h1{color:#1DB954;font-size:20px;margin:0 0 12px}'
            . 'p{opacity:.85;line-height:1.5}</style></head><body><div class="c">'
            . '<h1>The Muse</h1><p>' . $safe . '</p>'
            . '<p>You can close this tab and return to the game.</p>'
            . '</div></body></html>'
        )->header('Content-Type', 'text/html');
    }
}
