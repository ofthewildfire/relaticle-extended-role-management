<?php

declare(strict_types=1);

namespace Ofthewildfire\EnhancedRoleSystem\Policies;

use Ofthewildfire\RelaticleModsPlugin\Models\Projects;
use App\Models\User;
use Ofthewildfire\EnhancedRoleSystem\EnhancedRoleSystemPlugin;
use Filament\Facades\Filament;

final readonly class ProjectsPolicy
{
    protected function getPlugin(): EnhancedRoleSystemPlugin
    {
        return app(EnhancedRoleSystemPlugin::class);
    }

    public function viewAny(User $user): bool
    {
        $team = Filament::getTenant();
        if (!$team || !$user->belongsToTeam($team)) {
            return false;
        }
        
        $plugin = $this->getPlugin();
        return $plugin->hasResourcePermission($user, $team, 'projects', 'view');
    }

    public function view(User $user, Projects $project): bool
    {
        if (!$user->belongsToTeam($project->team)) {
            return false;
        }
        
        $plugin = $this->getPlugin();
        return $plugin->hasResourcePermission($user, $project->team, 'projects', 'view');
    }

    public function create(User $user): bool
    {
        $team = Filament::getTenant();
        if (!$team) {
            return false;
        }
        
        $plugin = $this->getPlugin();
        return $plugin->hasResourcePermission($user, $team, 'projects', 'create');
    }

    public function update(User $user, Projects $project): bool
    {
        $plugin = $this->getPlugin();
        
        // Check if user has edit permission for projects
        if (!$plugin->hasResourcePermission($user, $project->team, 'projects', 'edit')) {
            return false;
        }
        
        // If user has delete permission, they can edit anything
        if ($plugin->hasResourcePermission($user, $project->team, 'projects', 'delete')) {
            return true;
        }
        
        // If user only has edit permission, they can only edit their own entries
        return $project->created_by === $user->id;
    }

    public function delete(User $user, Projects $project): bool
    {
        $plugin = $this->getPlugin();
        
        // Check if user has delete permission for projects
        if (!$plugin->hasResourcePermission($user, $project->team, 'projects', 'delete')) {
            return false;
        }
        
        // If user has delete permission, they can delete anything
        return true;
    }

    public function restore(User $user, Projects $project): bool
    {
        return $this->delete($user, $project);
    }

    public function forceDelete(User $user, Projects $project): bool
    {
        return $this->delete($user, $project);
    }
}