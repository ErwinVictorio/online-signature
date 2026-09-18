<?php

namespace App\Services;

use App\Jobs\ConvertWordDocument;
use App\Models\Document;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ConversionService
{
    public function dispatch(Document $document): void
    {
        try {
            ConvertWordDocument::dispatch($document->id, $document->conversion_attempt)
                ->onConnection(config('document_conversion.connection'))->onQueue(config('document_conversion.queue'))->afterCommit();
        } catch (\Throwable $e) {
            Log::error('Word conversion dispatch failed', ['document_id' => $document->id, 'exception' => $e]);
            $this->fail($document->id, $document->conversion_attempt, 'Unable to queue conversion. Ask the administrator to check the queue, then retry.');
        }
    }

    public function retry(Document $document): void
    {
        $next = DB::transaction(function () use ($document) {
            $locked = Document::whereKey($document->id)->lockForUpdate()->firstOrFail();
            // Recover a job abandoned by a terminated worker after the process timeout.
            $abandoned = $locked->conversion_status === 'converting' && $locked->conversion_started_at?->lt(now()->subMinutes(5));
            abort_unless($locked->source_format !== 'pdf' && ($locked->conversion_status === 'failed' || $abandoned), 409, 'Only failed or interrupted conversions can be retried.');
            abort_if($locked->signed_at || $locked->editor_pdf_path || ! empty($locked->placements), 409, 'Upload a new document to change an existing converted layout.');
            $locked->update(['conversion_attempt' => $locked->conversion_attempt + 1, 'conversion_status' => 'queued', 'conversion_error' => null, 'conversion_started_at' => null]);
            app(AuditLogService::class)->record('conversion_retried', $locked);
            return $locked;
        });
        $this->dispatch($next);
    }

    public function fail(int $id, int $attempt, string $message): void
    {
        DB::transaction(function () use ($id, $attempt, $message) {
            $document = Document::whereKey($id)->lockForUpdate()->first();
            if (! $document || $document->conversion_attempt !== $attempt || ! in_array($document->conversion_status, ['queued', 'converting'], true)) return;
            $document->update(['conversion_status' => 'failed', 'conversion_error' => $message]);
            app(AuditLogService::class)->record('conversion_failed', $document, $document->user_id);
        });
    }
}
