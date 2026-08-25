<?php

namespace App\Http\Middleware;

use App\Models\Blok;
use App\Models\Menu;
use Closure;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Symfony\Component\HttpFoundation\Response;

class RouteMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $routeName = $request->route()?->getName();

        if (! $routeName) {
            return $next($request);
        }

        $menu = Menu::with(['main_permission', 'permissions'])
            ->where('route', $routeName)
            ->first();

        if (! $menu && str_contains($routeName, '.')) {
            $menu = Menu::with(['main_permission', 'permissions'])
                ->where('route', str($routeName)->before('.').'.index')
                ->first();
        }

        if (! $menu) {
            return $next($request);
        }

        if (str_starts_with($routeName, 'dpna-blok.') && $this->bolehMengelolaDpna($request)) {
            return $next($request);
        }

        $permission = $menu->main_permission?->name;

        if (str_ends_with($routeName, '.add_edit')) {
            $action = $request->route('id') === 'add' ? 'tambah' : 'edit';
            $permission = $menu->permissions
                ->first(fn ($permission) => str_starts_with(str($permission->name)->after(':')->value(), $action))
                ?->name;
        }

        return $menu->status && $permission && $request->user()?->can($permission)
            ? $next($request)
            : $this->deny();
    }

    private function deny(): RedirectResponse
    {
        return redirect()
            ->route('dashboard')
            ->with('failed', 'Anda tidak memiliki akses ke halaman ini.');
    }

    private function bolehMengelolaDpna(Request $request): bool
    {
        $query = Blok::query()->dapatDikelolaOleh($request->user());
        $encryptedId = $request->route('id');

        if ($encryptedId === null) {
            return $query->exists();
        }

        try {
            return $query->whereKey((int) Crypt::decrypt($encryptedId))->exists();
        } catch (DecryptException) {
            return false;
        }
    }
}
