<?php

namespace App\Http\Controllers;

use App\Models\PlacementTemplate;
use App\Services\AuditLogService;
use App\Services\PlacementValidator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class TemplateController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('Templates/Index', ['templates' => PlacementTemplate::where('user_id', $request->user()->id)->latest()->paginate(15)]);
    }

    public function store(Request $request, PlacementValidator $validator, AuditLogService $audit)
    {
        $data = $request->validate(['name' => 'required|string|max:100', 'page_count' => 'required|integer|min:1|max:500']);
        $placements = $validator->validate($request->all(), $request->user()->id, $data['page_count'], true);
        $template = DB::transaction(function () use ($request, $data, $placements, $audit) {
            $template = PlacementTemplate::create([...$data, 'user_id' => $request->user()->id, 'placements' => $placements]);
            $audit->record('template_created');

            return $template;
        });

        return response()->json(['template' => $template], 201);
    }

    public function update(Request $request, PlacementTemplate $template, AuditLogService $audit)
    {
        Gate::authorize('update', $template);
        $data = $request->validate(['name' => 'required|string|max:100']);
        DB::transaction(function () use ($template, $data, $audit) {
            $template->update($data);
            $audit->record('template_renamed');
        });

        return back()->with('success', 'Template renamed.');
    }

    public function destroy(PlacementTemplate $template, AuditLogService $audit)
    {
        Gate::authorize('delete', $template);
        DB::transaction(function () use ($template, $audit) {
            $template->delete();
            $audit->record('template_deleted');
        });

        return back()->with('success', 'Template deleted.');
    }
}
