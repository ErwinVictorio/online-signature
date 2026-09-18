<?php

namespace App\Http\Controllers;

use App\Models\Signature;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class SignatureController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('Signatures/Index', ['signatures' => Signature::where('user_id', $request->user()->id)->orderByDesc('is_default')->latest()->get()]);
    }

    public function store(Request $request, AuditLogService $audit)
    {
        $data = $request->validate(['name' => 'required|string|max:100', 'image' => 'required|image|mimes:png,jpg,jpeg|mimetypes:image/png,image/jpeg|extensions:png,jpg,jpeg|max:2048|dimensions:min_width=10,min_height=10,max_width=4000,max_height=4000', 'is_default' => 'sometimes|boolean']);
        $file = $request->file('image');
        [$width, $height] = getimagesize($file->getRealPath());
        $path = $file->store('signatures/'.$request->user()->id, 'private');
        try {
            DB::transaction(function () use ($request, $data, $file, $width, $height, $path, $audit) {
                User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
                $query = Signature::where('user_id', $request->user()->id);
                $default = $request->boolean('is_default') || ! $query->exists();
                if ($default) {
                    $query->update(['is_default' => false]);
                }
                Signature::create(['user_id' => $request->user()->id, 'name' => $data['name'], 'image_path' => $path, 'mime_type' => $file->getMimeType(), 'width' => $width, 'height' => $height, 'is_default' => $default]);
                $audit->record('signature_added');
            });
        } catch (\Throwable $e) {
            Storage::disk('private')->delete($path);
            throw $e;
        }

        return back()->with('success', 'Signature saved.');
    }

    public function image(Signature $signature)
    {
        Gate::authorize('view', $signature);
        abort_unless(Storage::disk('private')->exists($signature->image_path), 404);

        return Storage::disk('private')->response($signature->image_path, 'signature', ['Content-Type' => $signature->mime_type, 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function makeDefault(Signature $signature, AuditLogService $audit)
    {
        Gate::authorize('update', $signature);
        DB::transaction(function () use ($signature, $audit) {
            User::whereKey($signature->user_id)->lockForUpdate()->firstOrFail();
            Signature::where('user_id', $signature->user_id)->update(['is_default' => false]);
            $signature->update(['is_default' => true]);
            $audit->record('default_signature_changed');
        });

        return back()->with('success', 'Default signature updated.');
    }

    public function destroy(Signature $signature, AuditLogService $audit)
    {
        Gate::authorize('delete', $signature);
        DB::transaction(function () use ($signature, $audit) {
            User::whereKey($signature->user_id)->lockForUpdate()->firstOrFail();
            $signature->delete();
            if ($signature->is_default) {
                Signature::where('user_id', $signature->user_id)->oldest()->first()?->update(['is_default' => true]);
            }
            $audit->record('signature_removed');
        });
        Storage::disk('private')->delete($signature->image_path);

        return back()->with('success', 'Signature deleted. Existing signed PDFs are unchanged.');
    }
}
