<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\PlacementTemplate;
use App\Models\Signature;
use App\Services\AuditLogService;
use App\Services\DocumentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class DocumentController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate(['search' => 'nullable|string|max:200', 'status' => 'nullable|in:uploaded,editing,signed']);

        return Inertia::render('Documents/Index', [
            'documents' => Document::where('user_id', $request->user()->id)
                ->when($filters['search'] ?? null, fn ($q, $s) => $q->where('original_name', 'like', '%'.$s.'%'))
                ->when($filters['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
                ->latest()->paginate(15)->withQueryString(), 'filters' => $filters,
        ]);
    }

    public function create()
    {
        return Inertia::render('Documents/Create');
    }

    public function store(Request $request, DocumentService $service)
    {
        $request->validate(['file' => 'required|file|extensions:pdf,docx,doc|max:20480']);
        if (strtolower($request->file('file')->getClientOriginalExtension()) === 'pdf') {
            $request->validate(['file' => 'mimes:pdf|mimetypes:application/pdf']);
        }
        $document = $service->store($request->file('file'), $request->user()->id);

        return redirect()->route($document->editor_ready ? 'documents.edit' : 'documents.show', $document)
            ->with('success', $document->editor_ready ? 'PDF uploaded successfully.' : 'Word document uploaded. Conversion is queued.');
    }

    public function show(Document $document, AuditLogService $audit)
    {
        Gate::authorize('view', $document);
        $audit->record('document_opened', $document);

        return Inertia::render('Documents/Show', ['document' => $document, 'versions' => DB::table('document_versions')->where('document_id', $document->id)->orderByDesc('version')->get(['version', 'created_at'])]);
    }

    public function edit(Request $request, Document $document, AuditLogService $audit)
    {
        Gate::authorize('update', $document);
        $document->editorPath();
        $audit->record('document_opened', $document);

        return Inertia::render('Documents/Editor', ['document' => $document, 'signatures' => Signature::where('user_id', $request->user()->id)->orderByDesc('is_default')->latest()->get(), 'templates' => PlacementTemplate::where('user_id', $request->user()->id)->orderBy('name')->get()]);
    }

    public function file(Document $document)
    {
        Gate::authorize('view', $document);
        $path = $document->editorPath();
        abort_unless(Storage::disk('private')->exists($path), 404);

        return Storage::disk('private')->response($path, 'document.pdf', ['Content-Type' => 'application/pdf', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function download(Request $request, Document $document, AuditLogService $audit)
    {
        Gate::authorize('view', $document);
        $version = $request->query('version');
        $converted = $version === 'converted';
        $signed = ! in_array($version, ['original', 'converted'], true);
        $path = $converted ? $document->editorPath() : ($signed ? $document->signed_file_path : $document->file_path);
        if ($signed && $version !== null) {
            abort_unless(ctype_digit((string) $version), 404);
            $path = DB::table('document_versions')->where('document_id', $document->id)->where('version', $version)->value('file_path');
        }
        abort_unless($path && Storage::disk('private')->exists($path), 404, 'The requested copy is not available.');
        $audit->record($converted ? 'converted_document_downloaded' : ($signed ? 'signed_document_downloaded' : 'original_document_downloaded'), $document);
        $format = ! $signed && ! $converted ? $document->source_format : 'pdf';
        $mime = match ($format) {
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'doc' => 'application/msword', default => 'application/pdf'
        };
        $name = pathinfo($document->original_name, PATHINFO_FILENAME).($converted ? '-converted' : ($signed ? '-signed'.($version ? '-v'.$version : '') : '')).'.'.$format;

        return Storage::disk('private')->download($path, $name, ['Content-Type' => $mime, 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function destroy(Document $document, AuditLogService $audit)
    {
        Gate::authorize('delete', $document);
        $paths = DB::transaction(function () use ($document, $audit) {
            $locked = Document::whereKey($document->id)->lockForUpdate()->firstOrFail();
            $paths = array_unique(array_filter([$locked->file_path, $locked->editor_pdf_path, $locked->signed_file_path, ...DB::table('document_versions')->where('document_id', $locked->id)->pluck('file_path')->all()]));
            $audit->record('document_deleted', $locked);
            $locked->delete();

            return $paths;
        });
        Storage::disk('private')->delete($paths);

        return redirect()->route('documents.index')->with('success', 'Document deleted.');
    }
}
