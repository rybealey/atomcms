<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

class DeployStatusController extends Controller
{
    private const STATE_FILE = 'deploy-state.json';

    public function show(): JsonResponse
    {
        $payload = $this->readState() ?? ['status' => 'idle'];

        return response()
            ->json(['data' => $payload])
            ->header('Cache-Control', 'no-store');
    }

    private function readState(): ?array
    {
        if (!Storage::disk('local')->exists(self::STATE_FILE)) {
            return null;
        }

        $raw = Storage::disk('local')->get(self::STATE_FILE);
        if ($raw === null || $raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || !isset($decoded['status'])) {
            return null;
        }

        return $decoded;
    }
}
