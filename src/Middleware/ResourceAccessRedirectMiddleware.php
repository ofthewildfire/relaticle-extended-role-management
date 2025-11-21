<?php

declare(strict_types=1);

namespace Ofthewildfire\EnhancedRoleSystem\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Filament\Facades\Filament;
use Ofthewildfire\EnhancedRoleSystem\EnhancedRoleSystemPlugin;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class ResourceAccessRedirectMiddleware
{
    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        if (Auth::check() && session()->has('login_redirect_url')) {
            $redirectUrl = session()->pull('login_redirect_url');
            return new RedirectResponse($redirectUrl);
        }

        if (Auth::check()) {
            $user = Auth::user();
            $team = Filament::getTenant();

            if ($user && $team && $this->isTeamResourceRequest($request)) {
                $currentResource = $this->getCurrentResource($request);
                $plugin = app(EnhancedRoleSystemPlugin::class);

                if (!$currentResource) {
                    $firstAccessibleResource = $plugin->getFirstAccessibleResource($user, $team);
                    if ($firstAccessibleResource) {
                        return new RedirectResponse("/app/team/{$team->id}/{$firstAccessibleResource}");
                    }
                }

                if ($currentResource && !$plugin->hasResourcePermission($user, $team, $currentResource, 'view')) {
                    $newUrl = $this->getRedirectUrl($request, $user, $team);
                    if ($newUrl) {
                        return new RedirectResponse($newUrl);
                    }
                }
            }
        }

        try {
            $response = $next($request);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            if (in_array($e->getStatusCode(), [403, 404]) && Auth::check()) {
                $user = Auth::user();
                $team = Filament::getTenant();

                if ($user && $team && $this->isTeamResourceRequest($request)) {
                    $newUrl = $this->getRedirectUrl($request, $user, $team);
                    if ($newUrl) {
                        return new RedirectResponse($newUrl);
                    }
                }
            }
            throw $e;
        }

        if (in_array($response->getStatusCode(), [403, 404]) && Auth::check()) {
            $user = Auth::user();
            $team = Filament::getTenant();

            if ($user && $team && $this->isTeamResourceRequest($request)) {
                $newUrl = $this->getRedirectUrl($request, $user, $team);
                if ($newUrl) {
                    return new RedirectResponse($newUrl);
                }
            }
        }

        return $response;
    }

    protected function isTeamResourceRequest(Request $request): bool
    {
        return (bool) preg_match('#^app/team/\d+(/[a-zA-Z]*)?#', $request->path());
    }

    protected function getCurrentResource(Request $request): ?string
    {
        if (preg_match('#^app/team/\d+/([a-zA-Z]+)#', $request->path(), $matches)) {
            $resource = $matches[1];
            $plugin = app(EnhancedRoleSystemPlugin::class);
            $availableResources = array_keys($plugin->getAvailableResources());

            if (in_array($resource, $availableResources)) {
                return $resource;
            }
        }

        return null;
    }

    protected function getRedirectUrl(Request $request, $user, $team): ?string
    {
        $plugin = app(EnhancedRoleSystemPlugin::class);
        $firstAccessibleResource = $plugin->getFirstAccessibleResource($user, $team);

        if (!$firstAccessibleResource) {
            return null;
        }

        if (preg_match('#^app/team/\d+/([a-zA-Z]+)#', $request->path(), $matches)) {
            $currentResource = $matches[1];
            if ($firstAccessibleResource !== $currentResource) {
                return "/app/team/{$team->id}/{$firstAccessibleResource}";
            }
        }

        return null;
    }
}
