<?php

declare(strict_types=1);

namespace Ofthewildfire\EnhancedRoleSystem;

use Filament\Contracts\Plugin;
use Filament\Panel;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Ofthewildfire\EnhancedRoleSystem\EnhancedRoleSystemServiceProvider;

class EnhancedRoleSystemPlugin implements Plugin
{
    protected bool $canCreateTeams = true;
    protected array $superAdminEmails = [];
    protected bool $enableRoleManagement = true;

    public static function make(): static
    {
        return app(static::class);
    }

    public function getId(): string
    {
        return 'enhanced-role-system';
    }

    public function register(Panel $panel): void
    {
        // Register the service provider
        app()->register(EnhancedRoleSystemServiceProvider::class);

        // Register role management page if enabled
        if ($this->enableRoleManagement) {
            $panel->pages([
                \Ofthewildfire\EnhancedRoleSystem\Pages\ManageTeamRoles::class,
            ]);
        }

    }

    public function boot(Panel $panel): void
    {
        $this->registerGates();
        $this->registerPolicies();
        $this->registerMiddleware();
    }

    public function superAdminEmails(array $emails): static
    {
        $this->superAdminEmails = $emails;
        return $this;
    }

    public function canCreateTeams(bool $can = true): static
    {
        $this->canCreateTeams = $can;
        return $this;
    }

    public function enableRoleManagement(bool $enable = true): static
    {
        $this->enableRoleManagement = $enable;
        return $this;
    }

    protected function registerGates(): void
    {
        Gate::define('manage-team-roles', function ($user, $team) {
            return $this->hasMinimumRole($user, $team, 'admin');
        });

        Gate::define('create-teams', function ($user) {
            return $this->isSuperAdmin($user) || $this->canCreateTeams;
        });

        Gate::define('access-admin-panel', function ($user) {
            return $this->isSuperAdmin($user);
        });

        Gate::define('invite-team-members', function ($user, $team) {
            return $this->hasMinimumRole($user, $team, 'admin');
        });
    }

    protected function registerPolicies(): void
    {
    }

    protected function registerMiddleware(): void
    {
        // Register resource access redirect middleware
        app('router')->pushMiddlewareToGroup('web', \Ofthewildfire\EnhancedRoleSystem\Middleware\ResourceAccessRedirectMiddleware::class);
    }

    public function getRoleInTeam($user, $team): ?string
    {
        if (!$user || !$team) {
            return null;
        }

        // Use direct DB query since Eloquent relationships seem problematic
        $membership = \DB::table('team_user')
            ->where('team_id', $team->id)
            ->where('user_id', $user->id)
            ->first();
        
        return $membership?->role;
    }

    public function hasMinimumRole($user, $team, string $minimumRole): bool
    {
        if (!$user || !$team) {
            return false;
        }
        
        // Team owners always have full permissions (they created the team)
        if ($team->user_id === $user->id) {
            return true;
        }
        
        $userRole = $this->getRoleInTeam($user, $team);
        
        if (!$userRole) {
            return false;
        }

        $roleHierarchy = [
            'viewer' => 1,
            'member' => 2,
            'admin' => 3,
            'super_admin' => 4,
        ];

        return ($roleHierarchy[$userRole] ?? 0) >= ($roleHierarchy[$minimumRole] ?? 999);
    }

    public function isSuperAdmin($user): bool
    {
        if (!$user) {
            return false;
        }

        // Check if user is in super admin emails list
        if (in_array($user->email, $this->superAdminEmails)) {
            return true;
        }

        // Check if user has super_admin role in any team
        return $user->teams()
            ->wherePivot('role', 'super_admin')
            ->exists();
    }

    public function getRoleLabel(string $role): string
    {
        return match($role) {
            'viewer' => 'Viewer',
            'member' => 'Member',
            'admin' => 'Admin',
            'super_admin' => 'Super Admin',
            default => ucfirst($role),
        };
    }

    public function getRoleColor(string $role): string
    {
        return match($role) {
            'viewer' => 'gray',
            'member' => 'blue',
            'admin' => 'green',
            'super_admin' => 'red',
            default => 'gray',
        };
    }

    public function getAvailableRolesForUser($user, $team): array
    {
        if (!$user || !$team) {
            return [];
        }
        
        // Team owners can assign any role (they created the team)
        if ($team->user_id === $user->id) {
            return ['viewer', 'member', 'admin', 'super_admin'];
        }
        
        $userRole = $this->getRoleInTeam($user, $team);
        
        return match($userRole) {
            'super_admin' => ['viewer', 'member', 'admin', 'super_admin'],
            'admin' => ['viewer', 'member', 'admin'],
            default => [],
        };
    }

    public function getResourcePermission($user, $team, string $resource): string
    {
        if (!$user || !$team) {
            return 'none';
        }

        // Team owners have full access to all resources
        if ($team->user_id === $user->id) {
            return 'delete';
        }

        // Check for specific resource permission
        $permission = \DB::table('team_user_resource_permissions')
            ->where('team_id', $team->id)
            ->where('user_id', $user->id)
            ->where('resource', $resource)
            ->value('permission_level');

        if ($permission) {
            return $permission;
        }

        // Fall back to role-based permissions if no specific resource permission exists
        $userRole = $this->getRoleInTeam($user, $team);
        
        return match($userRole) {
            'super_admin' => 'delete',
            'admin' => 'delete',
            'member' => 'create',
            'viewer' => 'view',
            default => 'none',
        };
    }

    public function hasResourcePermission($user, $team, string $resource, string $requiredPermission): bool
    {
        if (!$user || !$team) {
            return false;
        }

        $userPermission = $this->getResourcePermission($user, $team, $resource);
        
        $permissionHierarchy = [
            'none' => 0,
            'view' => 1,
            'create' => 2,
            'edit' => 3,
            'delete' => 4,
        ];

        return ($permissionHierarchy[$userPermission] ?? 0) >= ($permissionHierarchy[$requiredPermission] ?? 999);
    }

    public function setResourcePermission($user, $team, string $resource, string $permissionLevel): bool
    {
        if (!$user || !$team) {
            return false;
        }

        // Validate permission level
        $validPermissions = ['none', 'view', 'create', 'edit', 'delete'];
        if (!in_array($permissionLevel, $validPermissions)) {
            return false;
        }

        \DB::table('team_user_resource_permissions')
            ->updateOrInsert(
                [
                    'team_id' => $team->id,
                    'user_id' => $user->id,
                    'resource' => $resource,
                ],
                [
                    'permission_level' => $permissionLevel,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );

        return true;
    }

    public function getAvailableResources(): array
    {
        return [
            'tasks' => 'Tasks',
            'people' => 'People',
            'companies' => 'Companies',
            'events' => 'Events',
            'ideas' => 'Ideas',
            'notes' => 'Notes',
            'opportunities' => 'Opportunities',
            'projects' => 'Projects',
        ];
    }

    public function getPermissionLevels(): array
    {
        return [
            'none' => 'No Access',
            'view' => 'View Only',
            'create' => 'View & Create',
            'edit' => 'View, Create & Edit',
            'delete' => 'Full Access',
        ];
    }

    public function getUserResourcePermissions($user, $team): array
    {
        if (!$user || !$team) {
            return [];
        }

        $permissions = \DB::table('team_user_resource_permissions')
            ->where('team_id', $team->id)
            ->where('user_id', $user->id)
            ->pluck('permission_level', 'resource')
            ->toArray();

        // Fill in defaults for resources without specific permissions
        $resources = array_keys($this->getAvailableResources());
        foreach ($resources as $resource) {
            if (!isset($permissions[$resource])) {
                $permissions[$resource] = $this->getResourcePermission($user, $team, $resource);
            }
        }

        return $permissions;
    }

    public function getFirstAccessibleResource($user, $team): ?string
    {
        if (!$user || !$team) {
            return null;
        }

        // Priority order for default resource
        $priorityOrder = ['tasks', 'notes', 'people', 'projects', 'opportunities', 'ideas', 'events', 'companies'];
        
        foreach ($priorityOrder as $resource) {
            if ($this->hasResourcePermission($user, $team, $resource, 'view')) {
                return $resource;
            }
        }
        
        return null;
    }

}