<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeployStatusResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var array{status: string, progress: int, step: string|null, etaSeconds: int|null, changelog: array<string, mixed>|null} $payload */
        $payload = $this->resource;

        return [
            'status' => $payload['status'],
            'progress' => $payload['progress'],
            'step' => $payload['step'],
            'etaSeconds' => $payload['etaSeconds'],
            'changelog' => $payload['changelog'],
        ];
    }
}
