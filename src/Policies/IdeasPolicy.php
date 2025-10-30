<?php

declare(strict_types=1);

namespace Ofthewildfire\EnhancedRoleSystem\Policies;

use Ofthewildfire\RelaticleModsPlugin\Models\Ideas;
use App\Models\User;
use Ofthewildfire\EnhancedRoleSystem\EnhancedRoleSystemPlugin;
use Filament\Facades\Filament;

final readonly class IdeasPolicy
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
        return $plugin->hasResourcePermission($user, $team, 'ideas', 'view');
    }

    public function view(User $user, Ideas $idea): bool
    {
        if (!$user->belongsToTeam($idea->team)) {
            return false;
        }
        
        $plugin = $this->getPlugin();
        return $plugin->hasResourcePermission($user, $idea->team, 'ideas', 'view');
    }

    public function create(User $user): bool
    {
        $team = Filament::getTenant();
        if (!$team) {
            return false;
        }
        
        $plugin = $this->getPlugin();
        return $plugin->hasResourcePermission($user, $team, 'ideas', 'create');
    }

    public function update(User $user, Ideas $idea): bool
    {
        $plugin = $this->getPlugin();
        
        // Check if user has edit permission for ideas
        if (!$plugin->hasResourcePermission($user, $idea->team, 'ideas', 'edit')) {
            return false;
        }
        
        // If user has delete permission, they can edit anything
        if ($plugin->hasResourcePermission($user, $idea->team, 'ideas', 'delete')) {
            return true;
        }
        
        // If user only has edit permission, they can only edit their own entries
        return $idea->created_by === $user->id;
    }

    public function delete(User $user, Ideas $idea): bool
    {
        $plugin = $this->getPlugin();
        
        // Check if user has delete permission for ideas
        if (!$plugin->hasResourcePermission($user, $idea->team, 'ideas', 'delete')) {
            return false;
        }
        
        // If user has delete permission, they can delete anything
        return true;
    }

    public function restore(User $user, Ideas $idea): bool
    {
        return $this->delete($user, $idea);
    }

    public function forceDelete(User $user, Ideas $idea): bool
    {
        return $this->delete($user, $idea);
    }
}