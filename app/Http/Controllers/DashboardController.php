<?php

namespace App\Http\Controllers;

use App\Models\Document;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $query = Document::where('user_id', $request->user()->id);

        return Inertia::render('Dashboard', [
            'stats' => ['total' => (clone $query)->count(), 'pending' => (clone $query)->whereNull('signed_at')->count(), 'signed' => (clone $query)->whereNotNull('signed_at')->count(), 'month' => (clone $query)->where('created_at', '>=', now()->startOfMonth())->count()],
            'recent' => $query->latest()->limit(6)->get(),
        ]);
    }
}
