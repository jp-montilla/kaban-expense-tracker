<?php

namespace App\Http\Middleware;

use App\Services\Wallets;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $success = $request->session()->get('success');
        $error = $request->session()->get('error');
        $flash = array_filter([
            'success' => is_string($success) ? $success : null,
            'error' => is_string($error) ? $error : null,
        ]);

        if ($flash !== []) {
            Inertia::flash($flash);
        }

        return [
            ...parent::share($request),
            'appName' => config('app.name'),
            'today' => CarbonImmutable::today()->toDateString(),
            'credits' => $request->user() ? app(Wallets::class)->credits() : [],
            'auth' => [
                'user' => $request->user() ? [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                    'is_admin' => $request->user()->isAdmin(),
                    'is_superadmin' => $request->user()->isSuperAdmin(),
                ] : null,
            ],
            'flash' => [
                'success' => $flash['success'] ?? null,
                'error' => $flash['error'] ?? null,
                'at' => $flash === [] ? null : (int) round(microtime(true) * 1000),
            ],
        ];
    }
}
