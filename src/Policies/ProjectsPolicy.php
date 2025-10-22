<?php

declare(strict_types=1);

namespace Ofthewildfire\EnhancedRoleSystem\Policies;

use Ofthewildfire\RelaticleModsPlugin\Models\Projects;
use App\Models\User;
use Ofthewildfire\EnhancedRoleSystemPlugin;
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
        return $team && $user->belongsToTeam($team);
    }

    public function view(User $user, Projects $project): bool
    {
        return $user->belongsToTeam($project->team);
    }

    public function create(User $user): bool
    {
        $team = Filament::getTenant();
        $plugin = $this->getPlugin();
        return $team && $plugin->hasMinimumRole($user, $team, 'member');
    }

    public function update(User $user, Projects $project): bool
    {
        $plugin = $this->getPlugin();
        
        // Admins can edit anything
        if ($plugin->hasMinimumRole($user, $project->team, 'admin')) {
            return true;
        }
        
        // Members can only edit their own entries
        if ($plugin->hasMinimumRole($user, $project->team, 'member')) {
            return $project->created_by === $user->id;
        }
        
        return false;
    }

    public function delete(User $user, Projects $project): bool
    {
        return $this->update($user, $project);
    }

    public function restore(User $user, Projects $project): bool
    {
        return $this->update($user, $project);
    }

    public function forceDelete(User $user, Projects $project): bool
    {
        $plugin = $this->getPlugin();
        return $plugin->hasMinimumRole($user, $project->team, 'admin');
    }
}