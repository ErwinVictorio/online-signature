<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ActivityController extends Controller
{
    public function __invoke(Request $request)
    {
        return Inertia::render('Activity/Index', ['logs' => AuditLog::where('user_id', $request->user()->id)->latest('id')->paginate(25)->through(fn ($log) => $log->only('id', 'document_name', 'action', 'created_at'))]);
    }
}
