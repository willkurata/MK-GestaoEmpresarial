<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
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
     * O site público usa uma root view própria (pt-BR, sem tema escuro do app).
     */
    public function rootView(Request $request): string
    {
        return $this->isSiteRequest($request) ? 'site' : $this->rootView;
    }

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
        if ($this->isSiteRequest($request)) {
            return $this->shareSite($request);
        }

        $companyUser = $request->user('company_user');

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $request->user(),
                'company' => $request->user('company'),
                'companyUser' => $companyUser,
                'permissions' => [
                    'reverseSales' => $companyUser?->can('reverse-sales') ?? false,
                    'manageProducts' => $companyUser?->can('manage-products') ?? false,
                    'manageExpenses' => $companyUser?->can('manage-expenses') ?? false,
                    'viewReports' => $companyUser?->can('view-reports') ?? false,
                    'manageUsers' => $companyUser?->can('manage-users') ?? false,
                ],
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'temporaryPassword' => fn () => $request->session()->pull('temporaryPassword'),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    private function isSiteRequest(Request $request): bool
    {
        return $request->routeIs('site.*');
    }

    /**
     * Props públicas do site. Não compartilha dados de autenticação do
     * MK Gestão, pois as páginas do site podem ser servidas por cache/CDN.
     *
     * @return array<string, mixed>
     */
    private function shareSite(Request $request): array
    {
        return [
            'errors' => parent::share($request)['errors'],
            'site' => [
                'url' => $request->getSchemeAndHttpHost(),
                'gestao_url' => config('site.gestao_url'),
                'contact' => config('site.contact'),
                'cities' => config('site.cities'),
                'creci' => config('site.creci'),
                'crc' => config('site.crc'),
            ],
        ];
    }
}
