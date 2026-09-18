<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Services\AuditLogService;
use App\Services\PdfInspector;
use App\Services\PlacementValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class SignedDocumentController extends Controller
{
    public function draft(Request $request, Document $document, PlacementValidator $validator, AuditLogService $audit)
    {
        Gate::authorize('update', $document);
        $request->validate(['revision' => 'required|integer|min:0']);
        $placements = $validator->validate($request->all(), $request->user()->id, $document->page_count);
        $revision = DB::transaction(function () use ($request, $document, $placements, $audit) {
            $locked = Document::whereKey($document->id)->lockForUpdate()->firstOrFail();
            abort_if($locked->revision !== $request->integer('revision'), 409, 'This document changed in another tab. Reload before saving.');
            $locked->update(['placements' => $placements, 'status' => 'editing', 'revision' => $locked->revision + 1]);
            $audit->record('placements_saved', $locked);

            return $locked->revision;
        });

        return response()->json(['revision' => $revision]);
    }

    public function store(Request $request, Document $document, PlacementValidator $validator, PdfInspector $inspector, AuditLogService $audit)
    {
        Gate::authorize('update', $document);
        $request->validate(['revision' => 'required|integer|min:0', 'file' => 'required|file|mimes:pdf|mimetypes:application/pdf|extensions:pdf|max:40960', 'placements' => 'required|string|max:200000']);
        try {
            $placements = json_decode($request->input('placements'), true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw ValidationException::withMessages(['placements' => 'Invalid placement data.']);
        }
        $placements = $validator->validate(['placements' => $placements], $request->user()->id, $document->page_count, true);
        $pages = $inspector->inspect($request->file('file'));
        if ($pages !== $document->page_count) {
            throw ValidationException::withMessages(['file' => 'The signed copy must retain every original page.']);
        }
        $path = $request->file('file')->store('signed-documents/'.$request->user()->id, 'private');
        try {
            $revision = DB::transaction(function () use ($request, $document, $placements, $path, $audit) {
                $locked = Document::whereKey($document->id)->lockForUpdate()->firstOrFail();
                abort_if($locked->revision !== $request->integer('revision'), 409, 'This document changed in another tab. Reload before signing.');
                $locked->update(['signed_file_path' => $path, 'signed_at' => now(), 'status' => 'signed', 'placements' => $placements, 'revision' => $locked->revision + 1]);
                DB::table('document_versions')->insert(['document_id' => $locked->id, 'file_path' => $path, 'version' => DB::table('document_versions')->where('document_id', $locked->id)->count() + 1, 'created_at' => now()]);
                $audit->record('document_signed', $locked);

                return $locked->revision;
            });
        } catch (\Throwable $e) {
            Storage::disk('private')->delete($path);
            throw $e;
        }

        return response()->json(['revision' => $revision, 'download_url' => route('documents.download', $document)]);
    }
}
