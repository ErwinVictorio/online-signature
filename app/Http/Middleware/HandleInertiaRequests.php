<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The Blade view that wraps every Inertia response.
     */
    protected $rootView = 'app';

    /**
     * The props shared with every page.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return array_merge(parent::share($request), [
            'auth' => ['user' => fn () => $request->user()?->only('id', 'name', 'email')],
            'app' => [
                'name' => config('app.name'),
            ],
            // Flash messages, so any page can report the result of an action.
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ]);
    }
}
