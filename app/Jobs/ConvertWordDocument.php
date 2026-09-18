<?php

namespace App\Jobs;

use App\Models\Document;
use App\Services\AuditLogService;
use App\Services\ConversionService;
use App\Services\PdfInspector;
use App\Services\WordConverter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Exception\ProcessTimedOutException;

class ConvertWordDocument implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;
    public int $timeout = 150;
    public bool $failOnTimeout = true;

    public function __construct(public int $documentId, public int $attempt) {}

    public function backoff(): array { return [15]; }

    public function handle(WordConverter $converter, PdfInspector $inspector): void
    {
        $document = DB::transaction(function () {
            $document = Document::whereKey($this->documentId)->lockForUpdate()->first();
            if (! $document || $document->conversion_attempt !== $this->attempt || $document->conversion_status !== 'queued') return null;
            $document->update(['conversion_status' => 'converting', 'conversion_started_at' => now(), 'conversion_error' => null]);
            app(AuditLogService::class)->record('conversion_started', $document, $document->user_id);
            return $document;
        });
        if (! $document) return;
        $path = null;
        try {
            $converter->convert(Storage::disk('private')->path($document->file_path), $document->source_format, function ($pdf) use ($document, $inspector, &$path) {
                $pages = $inspector->inspect(new UploadedFile($pdf, 'converted.pdf', 'application/pdf', null, true));
                $path = 'converted-documents/'.$document->user_id.'/'.Str::uuid().'.pdf';
                $stream = fopen($pdf, 'rb');
                try { Storage::disk('private')->put($path, $stream); } finally { fclose($stream); }
                $published = DB::transaction(function () use ($path, $pages) {
                    $current = Document::whereKey($this->documentId)->lockForUpdate()->first();
                    if (! $current || $current->conversion_attempt !== $this->attempt || $current->conversion_status !== 'converting') return false;
                    $current->update(['editor_pdf_path' => $path, 'page_count' => $pages, 'conversion_status' => 'ready', 'converted_at' => now(), 'conversion_error' => null]);
                    app(AuditLogService::class)->record('conversion_succeeded', $current, $current->user_id);
                    return true;
                });
                if (! $published) Storage::disk('private')->delete($path);
            });
        } catch (\Throwable $e) {
            if ($path) Storage::disk('private')->delete($path);
            Log::warning('Word conversion failed', ['document_id' => $this->documentId, 'attempt' => $this->attempt, 'exception' => $e]);
            if ($e instanceof ProcessTimedOutException && $this->job && $this->attempts() < $this->tries) {
                Document::whereKey($this->documentId)->where('conversion_attempt', $this->attempt)->where('conversion_status', 'converting')->update(['conversion_status' => 'queued']);
                throw $e;
            }
            $message = ! $converter->available()
                ? 'Word conversion is unavailable. Ask the administrator to install LibreOffice and configure its executable, then retry.'
                : 'Unable to convert this Word file. It may be damaged, password protected, too complex, or exceed the 40 MB / 500 page output limit. Export a new DOCX or PDF and try again.';
            app(ConversionService::class)->fail($this->documentId, $this->attempt, $message);
        }
    }

    public function failed(?\Throwable $exception): void
    {
        app(ConversionService::class)->fail($this->documentId, $this->attempt, 'Conversion was interrupted or timed out. Retry, or upload a smaller document.');
    }
}
