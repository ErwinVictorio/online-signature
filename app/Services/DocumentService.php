<?php

namespace App\Services;

use App\Models\Document;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DocumentService
{
    public function store(UploadedFile $file, int $userId): Document
    {
        $format = strtolower($file->getClientOriginalExtension());
        $pages = $format === 'pdf' ? app(PdfInspector::class)->inspect($file) : null;
        if ($format !== 'pdf') {
            $format = app(WordUploadInspector::class)->inspect($file);
        }
        $path = $file->store('documents/'.$userId, 'private');
        if (! $path) {
            throw new \RuntimeException('Unable to store uploaded document.');
        }
        try {
            $document = DB::transaction(function () use ($file, $userId, $pages, $path, $format) {
                $document = Document::create([
                    'user_id' => $userId, 'original_name' => mb_substr(basename(str_replace('\\', '/', $file->getClientOriginalName())), 0, 240),
                    'file_path' => $path, 'page_count' => $pages, 'file_size' => $file->getSize(),
                    'source_format' => $format, 'editor_pdf_path' => $format === 'pdf' ? $path : null,
                    'conversion_status' => $format === 'pdf' ? 'not_required' : 'queued', 'conversion_attempt' => $format === 'pdf' ? 0 : 1,
                ]);
                app(AuditLogService::class)->record('document_uploaded', $document);
                if ($format !== 'pdf') {
                    app(AuditLogService::class)->record('conversion_queued', $document);
                }

                return $document;
            });
        } catch (\Throwable $e) {
            Storage::disk('private')->delete($path);
            throw $e;
        }
        if ($format !== 'pdf') {
            app(ConversionService::class)->dispatch($document);
        }

        return $document;
    }
}
