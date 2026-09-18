<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Document;

class AuditLogService
{
    public function record(string $action, ?Document $document = null, ?int $actorId = null): void
    {
        AuditLog::create([
            'user_id' => $actorId ?? auth()->id(), 'document_id' => $document?->id,
            'document_name' => $document?->original_name, 'action' => $action,
            'ip_address' => $actorId ? null : request()->ip(), 'user_agent' => $actorId ? null : mb_substr(request()->userAgent() ?? '', 0, 500),
        ]);
    }
}
