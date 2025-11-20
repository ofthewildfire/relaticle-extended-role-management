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
        // Check for login redirect first
        if (Auth::check() && session()->has('login_redirect_url')) {
            $redirectUrl = session()->pull('login_redirect_url');
            return redirect()->to($redirectUrl);
        }
        
        // Check BEFORE processing the request if user doesn't have access
        if (Auth::check()) {
            $user = Auth::user();
            $team = Filament::getTenant();
            
            if ($user && $team && $this->isTeamResourceRequest($request)) {
                $currentResource = $this->getCurrentResource($request);
                $plugin = app(EnhancedRoleSystemPlugin::class);
                
                // If no resource specified (just /app/team/{id}), redirect to first accessible resource
                if (!$currentResource) {
                    // Prevent infinite loops by checking if we already redirected
                    if (session()->has('no_resource_access_check')) {
                        session()->forget('no_resource_access_check');
                        abort(403, 'You do not have access to any resources in this team.');
                    }
                    
                    $firstAccessibleResource = $plugin->getFirstAccessibleResource($user, $team);
                    if ($firstAccessibleResource) {
                        return redirect()->to("/app/team/{$team->id}/{$firstAccessibleResource}");
                    } else {
                        session()->put('no_resource_access_check', true);
                        return redirect()->to("/app/team/{$team->id}")
                            ->with('error', 'You do not have access to any resources in this team.');
                    }
                }
                
                // Check if user has access to current resource
                if ($currentResource && !$plugin->hasResourcePermission($user, $team, $currentResource, 'view')) {
                    $newUrl = $this->getRedirectUrl($request, $user, $team);
                    
                    if ($newUrl) {
                        return redirect()->to($newUrl);
                    } else {
                        // If no accessible resource found, redirect to team dashboard or show error
                        return redirect()->to("/app/team/{$team->id}")
                            ->with('error', 'You do not have access to any resources in this team.');
                    }
                }
            }
        }
        
        $response = $next($request);
        
        // Handle 404 and 403 errors as backup - this is crucial for the companies default issue
        if (in_array($response->getStatusCode(), [403, 404]) && Auth::check()) {
            $user = Auth::user();
            $team = Filament::getTenant();
            
            if ($user && $team && $this->isTeamResourceRequest($request)) {
                $newUrl = $this->getRedirectUrl($request, $user, $team);
                
                if ($newUrl) {
                    return redirect()->to($newUrl);
                } else {
                    // If no accessible resource found, redirect to team dashboard
                    return redirect()->to("/app/team/{$team->id}")
                        ->with('error', 'You do not have access to any resources in this team.');
                }
            }
        }
        
        return $response;
    }
    
    protected function isTeamResourceRequest(Request $request): bool
    {
        $path = $request->path();
        
        // Check if this matches the pattern: app/team/{id}/{resource} OR just app/team/{id}
        return preg_match('#^app/team/\d+(/[a-zA-Z]*)?#', $path);
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
            \Log::warning('No accessible resource found for user', [
                'user_id' => $user->id,
                'team_id' => $team->id,
                'path' => $path
            ]);
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
                
                \Log::info('Redirecting user to accessible resource', [
                    'user_id' => $user->id,
                    'team_id' => $team->id,
                    'from_resource' => $currentResource,
                    'to_resource' => $firstAccessibleResource,
                    'new_url' => $newUrl
                ]);
                
                return $newUrl;
            }
        }
        
        return null;
    }
}