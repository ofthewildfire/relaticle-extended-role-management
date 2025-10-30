<?php

declare(strict_types=1);

namespace Ofthewildfire\EnhancedRoleSystem\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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
                
                // Check if user has access to current resource
                if ($currentResource && !$plugin->hasResourcePermission($user, $team, $currentResource, 'view')) {
                    $newUrl = $this->getRedirectUrl($request, $user, $team);
                    
                    if ($newUrl) {
                        return redirect()->to($newUrl);
                    }
                }
            }
        }
        
        $response = $next($request);
        
        // Also handle 403 errors as backup
        if ($response->getStatusCode() === 403 && Auth::check()) {
            $user = Auth::user();
            $team = Filament::getTenant();
            
            if ($user && $team && $this->isTeamResourceRequest($request)) {
                $newUrl = $this->getRedirectUrl($request, $user, $team);
                
                if ($newUrl) {
                    return redirect()->to($newUrl);
                }
            }
        }
        
        return $response;
    }
    
    protected function isTeamResourceRequest(Request $request): bool
    {
        $path = $request->path();
        
        // Check if this matches the pattern: app/team/{id}/{resource}
        return preg_match('#^app/team/\d+/[a-zA-Z]+#', $path);
    }
    
    protected function getCurrentResource(Request $request): ?string
    {
        $path = $request->path();
        
        // Extract resource from URL pattern: app/team/{id}/{resource}
        if (preg_match('#^app/team/\d+/([a-zA-Z]+)#', $path, $matches)) {
            return $matches[1]; // companies, tasks, etc.
        }
        
        return null;
    }
    
    protected function getRedirectUrl(Request $request, $user, $team): ?string
    {
        $path = $request->path();
        $plugin = app(EnhancedRoleSystemPlugin::class);
        
        // Extract the current URL pattern: app/team/{id}/{resource}
        if (preg_match('#^(app/team/\d+/)([a-zA-Z]+)(.*)$#', $path, $matches)) {
            $baseUrl = $matches[1]; // app/team/{id}/
            $currentResource = $matches[2]; // companies, tasks, etc.
            $remaining = $matches[3]; // any additional path
            
            // Find the first accessible resource
            $firstAccessibleResource = $plugin->getFirstAccessibleResource($user, $team);
            
            if ($firstAccessibleResource && $firstAccessibleResource !== $currentResource) {
                // Replace the resource part with the accessible one
                return $baseUrl . $firstAccessibleResource . $remaining;
            }
        }
        
        return null;
    }
}