<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ContentAudit
{
    public function record(?int $actorId, string $action, string $entityType, string|int|null $entityId, array $summary = []): string
    {
        $correlationId = (string) Str::uuid();

        DB::table('pena_content_audit')->insert([
            'correlation_id' => $correlationId,
            'actor_id' => $actorId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId === null ? null : (string) $entityId,
            'summary' => $summary === [] ? null : json_encode($summary, JSON_THROW_ON_ERROR),
            'created_at' => now(),
        ]);

        return $correlationId;
    }
}
