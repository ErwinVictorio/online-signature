<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Services\AuditLogService;
use App\Services\ConversionService;
use Illuminate\Support\Facades\Gate;

class ConversionController extends Controller
{
    public function retry(Document $document, ConversionService $service)
    {
        Gate::authorize('update', $document);
        $service->retry($document);
        return back()->with('success', 'Conversion queued again.');
    }

    public function review(Document $document, AuditLogService $audit)
    {
        Gate::authorize('update', $document);
        abort_unless($document->editor_ready && $document->source_format !== 'pdf', 409);
        $document->update(['conversion_reviewed_at' => now()]);
        $audit->record('converted_layout_reviewed', $document);
        return response()->json(['reviewed' => true]);
    }
}
