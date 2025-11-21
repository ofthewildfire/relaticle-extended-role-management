<?php

declare(strict_types=1);

namespace Ofthewildfire\EnhancedRoleSystem\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Filament\Facades\Filament;
use Ofthewildfire\EnhancedRoleSystem\EnhancedRoleSystemPlugin;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class ResourceAccessRedirectMiddleware
{
    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        // Visual debugging - add debug info to session
        $debugInfo = [
            'middleware_running' => true,
            'path' => $request->path(),
            'url' => $request->url(),
            'is_authenticated' => Auth::check(),
            'has_login_redirect' => session()->has('login_redirect_url')
        ];

        // Check for login redirect first
        if (Auth::check() && session()->has('login_redirect_url')) {
            $redirectUrl = session()->pull('login_redirect_url');
            $debugInfo['using_login_redirect'] = $redirectUrl;
            session()->flash('debug_info', $debugInfo);
            return redirect()->to($redirectUrl);
        }

        // Check BEFORE processing the request if user doesn't have access
        if (Auth::check()) {
            $user = Auth::user();
            $team = Filament::getTenant();

            $debugInfo['user_id'] = $user?->id;
            $debugInfo['team_id'] = $team?->id;
            $debugInfo['is_team_request'] = $this->isTeamResourceRequest($request);

            if ($user && $team && $this->isTeamResourceRequest($request)) {
                $currentResource = $this->getCurrentResource($request);
                $plugin = app(EnhancedRoleSystemPlugin::class);

                $debugInfo['current_resource'] = $currentResource;

                // If no resource specified (just /app/team/{id}), redirect to first accessible resource
                if (!$currentResource) {
                    $firstAccessibleResource = $plugin->getFirstAccessibleResource($user, $team);
                    $debugInfo['no_resource_redirect'] = $firstAccessibleResource;
                    session()->flash('debug_info', $debugInfo);

                    if ($firstAccessibleResource) {
                        return redirect()->to("/app/team/{$team->id}/{$firstAccessibleResource}");
                    } else {
                        return redirect()->to("/app/team/{$team->id}")
                            ->with('error', 'You do not have access to any resources in this team.');
                    }
                }

                // Check if user has access to current resource
                if ($currentResource) {
                    $hasPermission = $plugin->hasResourcePermission($user, $team, $currentResource, 'view');
                    $debugInfo['has_permission'] = $hasPermission;

                    if (!$hasPermission) {
                        $newUrl = $this->getRedirectUrl($request, $user, $team);
                        $debugInfo['redirect_url'] = $newUrl;
                        session()->flash('debug_info', $debugInfo);

                        if ($newUrl) {
                            return redirect()->to($newUrl);
                        } else {
                            // Show debug info instead of generic error
                            return response()->view('enhanced-role-system::debug', [
                                'debug_info' => $debugInfo,
                                'message' => 'No accessible resources found'
                            ], 403);
                        }
                    }
                }
            }
        }

        session()->flash('debug_info', $debugInfo);

        try {
            $response = $next($request);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            // Catch HTTP exceptions (like 403, 404) and handle them
            $debugInfo['caught_exception'] = $e->getStatusCode();

            if (in_array($e->getStatusCode(), [403, 404]) && Auth::check()) {
                $user = Auth::user();
                $team = Filament::getTenant();

                if ($user && $team && $this->isTeamResourceRequest($request)) {
                    $newUrl = $this->getRedirectUrl($request, $user, $team);
                    $debugInfo['exception_redirect'] = $newUrl;
                    session()->flash('debug_info', $debugInfo);

                    if ($newUrl) {
                        return redirect()->to($newUrl);
                    }
                }
            }

            // Re-throw the exception if we can't handle it
            throw $e;
        }

        $debugInfo['response_status'] = $response->getStatusCode();

        // Handle 404 and 403 errors as backup - this is crucial for the companies default issue
        if (in_array($response->getStatusCode(), [403, 404]) && Auth::check()) {
            $user = Auth::user();
            $team = Filament::getTenant();

            $debugInfo['handling_error_response'] = true;

            if ($user && $team && $this->isTeamResourceRequest($request)) {
                $newUrl = $this->getRedirectUrl($request, $user, $team);
                $debugInfo['error_redirect'] = $newUrl;
                session()->flash('debug_info', $debugInfo);

                if ($newUrl) {
                    return redirect()->to($newUrl);
                } else {
                    // If no accessible resource found, redirect to team dashboard
                    return redirect()->to("/app/team/{$team->id}")
                        ->with('error', 'You do not have access to any resources in this team.');
                }
            }
        }

        session()->flash('debug_info', $debugInfo);

        // Add a header to confirm middleware is running
        $response->headers->set('X-Resource-Middleware', 'active');

        return $response;
    }

    protected function isTeamResourceRequest(Request $request): bool
    {
        $path = $request->path();

        // Check if this matches the pattern: app/team/{id}/{resource} OR just app/team/{id}
        return (bool) preg_match('#^app/team/\d+(/[a-zA-Z]*)?#', $path);
    }

    protected function getCurrentResource(Request $request): ?string
    {
        $path = $request->path();

        // Extract resource from URL pattern: app/team/{id}/{resource}
        if (preg_match('#^app/team/\d+/([a-zA-Z]+)#', $path, $matches)) {
            $resource = $matches[1];

            // Validate that this is actually a known resource
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
        $path = $request->path();
        $plugin = app(EnhancedRoleSystemPlugin::class);

        // Find the first accessible resource
        $firstAccessibleResource = $plugin->getFirstAccessibleResource($user, $team);

        if (!$firstAccessibleResource) {
            return null;
        }

        // Extract the current URL pattern: app/team/{id}/{resource}
        if (preg_match('#^(app/team/\d+/)([a-zA-Z]+)(.*)$#', $path, $matches)) {
            $baseUrl = $matches[1]; // app/team/{id}/
            $currentResource = $matches[2]; // companies, tasks, etc.
            $remaining = $matches[3]; // any additional path

            if ($firstAccessibleResource !== $currentResource) {
                $newUrl = $baseUrl . $firstAccessibleResource;

                // Only append remaining path if it's just a simple list view (no specific record IDs)
                // This prevents redirecting to a record that might not exist in the new resource
                if (empty($remaining) || $remaining === '/' || preg_match('#^/?$#', $remaining)) {
                    $newUrl .= $remaining;
                }

                return $newUrl;
            }
        }

        return null;
    }
}
