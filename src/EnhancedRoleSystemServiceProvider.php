<?php

declare(strict_types=1);

namespace Ofthewildfire\EnhancedRoleSystem;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Ofthewildfire\EnhancedRoleSystem\Policies\EnhancedTeamPolicy;
use Ofthewildfire\EnhancedRoleSystem\Policies\CompanyPolicy;
use Ofthewildfire\EnhancedRoleSystem\Policies\PeoplePolicy;
use Ofthewildfire\EnhancedRoleSystem\Policies\TaskPolicy;
use Ofthewildfire\EnhancedRoleSystem\Policies\OpportunityPolicy;
use Ofthewildfire\EnhancedRoleSystem\Policies\NotePolicy;
use Ofthewildfire\EnhancedRoleSystem\Policies\EventsPolicy;
use Ofthewildfire\EnhancedRoleSystem\Policies\IdeasPolicy;
use Ofthewildfire\EnhancedRoleSystem\Policies\ProjectsPolicy;
use App\Models\Team;
use App\Models\Company;
use App\Models\People;
use App\Models\Task;
use App\Models\Opportunity;
use App\Models\Note;
use Ofthewildfire\RelaticleModsPlugin\Models\Events;
use Ofthewildfire\RelaticleModsPlugin\Models\Ideas;
use Ofthewildfire\RelaticleModsPlugin\Models\Projects;

class EnhancedRoleSystemServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register middleware early
        $this->registerMiddleware();
    }

    public function boot(): void
    {
      $this->loadMigrationsFrom(__DIR__.'/../database');
      $this->loadViewsFrom(__DIR__.'/../resources/views', 'enhanced-role-system');
      $this->registerPolicies();
      $this->publishMigrations();
      $this->registerLoginRedirect();
      $this->registerRoutes();
    }

    protected function registerRoutes(): void
    {
        // Register a simple redirect route that catches resource access attempts
        app('router')->group(['middleware' => ['web', 'auth']], function () {
            // Catch specific resource routes and redirect if no access
            app('router')->get('app/team/{team}/companies', function ($teamId) {
                $user = auth()->user();
                $team = $user->teams()->find($teamId);
                
                if (!$team) {
                    abort(404, 'Team not found');
                }
                
                $plugin = app(EnhancedRoleSystemPlugin::class);
                
                // Check if user has access to companies
                if (!$plugin->hasResourcePermission($user, $team, 'companies', 'view')) {
                    // Find first accessible resource
                    $firstAccessibleResource = $plugin->getFirstAccessibleResource($user, $team);
                    
                    if ($firstAccessibleResource) {
                        return redirect()->to("/app/team/{$teamId}/{$firstAccessibleResource}");
                    } else {
                        abort(403, 'You do not have access to any resources in this team.');
                    }
                }
                
                // If they do have access, let Filament handle it normally
                // This route won't match and Filament's route will take over
                abort(404);
                
            })->where('team', '[0-9]+');
            
            // Add similar routes for other resources
            $resources = ['tasks', 'people', 'events', 'ideas', 'notes', 'opportunities', 'projects'];
            
            foreach ($resources as $resource) {
                app('router')->get("app/team/{team}/{$resource}", function ($teamId) use ($resource) {
                    $user = auth()->user();
                    $team = $user->teams()->find($teamId);
                    
                    if (!$team) {
                        abort(404, 'Team not found');
                    }
                    
                    $plugin = app(EnhancedRoleSystemPlugin::class);
                    
                    // Check if user has access to this resource
                    if (!$plugin->hasResourcePermission($user, $team, $resource, 'view')) {
                        // Find first accessible resource
                        $firstAccessibleResource = $plugin->getFirstAccessibleResource($user, $team);
                        
                        if ($firstAccessibleResource) {
                            return redirect()->to("/app/team/{$teamId}/{$firstAccessibleResource}");
                        } else {
                            abort(403, 'You do not have access to any resources in this team.');
                        }
                    }
                    
                    // If they do have access, let Filament handle it normally
                    abort(404);
                    
                })->where('team', '[0-9]+');
            }
        });
    }

    protected function registerMiddleware(): void
    {
        $router = app('router');
        $router->pushMiddlewareToGroup('web', \Ofthewildfire\EnhancedRoleSystem\Middleware\ResourceAccessRedirectMiddleware::class);
        
        $kernel = app(\Illuminate\Contracts\Http\Kernel::class);
        $kernel->pushMiddleware(\Ofthewildfire\EnhancedRoleSystem\Middleware\ResourceAccessRedirectMiddleware::class);
    }

    protected function registerPolicies(): void
    {
        Gate::policy(Team::class, EnhancedTeamPolicy::class);
        Gate::policy(Company::class, CompanyPolicy::class);
        Gate::policy(People::class, PeoplePolicy::class);
        Gate::policy(Task::class, TaskPolicy::class);
        Gate::policy(Opportunity::class, OpportunityPolicy::class);
        Gate::policy(Note::class, NotePolicy::class);
        Gate::policy(Events::class, EventsPolicy::class);
        Gate::policy(Ideas::class, IdeasPolicy::class);
        Gate::policy(Projects::class, ProjectsPolicy::class);
    }

    protected function publishMigrations(): void
    {
        //
    }

    protected function registerLoginRedirect(): void
    {
        // Listen for login events and redirect to appropriate resource
        \Event::listen(\Illuminate\Auth\Events\Login::class, function ($event) {
            $user = $event->user;
            
            // Only handle if this is a web request and user just logged in
            if (request()->expectsJson() || !$user) {
                return;
            }
            
            // Manually resolve tenant since Filament::getTenant() returns null at this point
            $team = $this->resolveTenantFromRequest($user);
            
            if (!$team) {
                return;
            }
            
            try {
                $plugin = app(EnhancedRoleSystemPlugin::class);
                $firstAccessibleResource = $plugin->getFirstAccessibleResource($user, $team);
                
                if ($firstAccessibleResource) {
                    $redirectUrl = "/app/team/{$team->id}/{$firstAccessibleResource}";
                    session()->put('login_redirect_url', $redirectUrl);
                }
            } catch (\Exception $e) {
                return;
            }
        });
    }

    protected function resolveTenantFromRequest($user)
    {
        $request = request();
        
        // Try to extract team ID from the current request URL
        $path = $request->path();
        if (preg_match('#^app/team/(\d+)#', $path, $matches)) {
            $teamId = $matches[1];
            return $user->teams()->find($teamId);
        }
        
        // Try to get team from subdomain or host
        $host = $request->getHost();
        if (preg_match('#^team(\d+)\.#', $host, $matches)) {
            $teamId = $matches[1];
            return $user->teams()->find($teamId);
        }
        
        // Check referer URL for team context
        $referer = $request->header('referer');
        if ($referer && preg_match('#/app/team/(\d+)#', $referer, $matches)) {
            $teamId = $matches[1];
            return $user->teams()->find($teamId);
        }
        
        // Fallback: use user's current team or first team
        if (method_exists($user, 'current_team_id') && $user->current_team_id) {
            return $user->teams()->find($user->current_team_id);
        }
        
        // Last resort: return the first team the user belongs to
        return $user->teams()->first();
    }

}