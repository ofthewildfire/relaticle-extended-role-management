<?php

declare(strict_types=1);

namespace Ofthewildfire\EnhancedRoleSystem\Policies;

use App\Models\People;
use App\Models\User;
use Ofthewildfire\EnhancedRoleSystem\EnhancedRoleSystemPlugin;
use Filament\Facades\Filament;

final readonly class PeoplePolicy
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
        return $plugin->hasResourcePermission($user, $team, 'people', 'view');
    }

    public function view(User $user, People $people): bool
    {
        if (!$user->belongsToTeam($people->team)) {
            return false;
        }
        
        $plugin = $this->getPlugin();
        return $plugin->hasResourcePermission($user, $people->team, 'people', 'view');
    }

    public function create(User $user): bool
    {
        $team = Filament::getTenant();
        if (!$team) {
            return false;
        }
        
        $plugin = $this->getPlugin();
        return $plugin->hasResourcePermission($user, $team, 'people', 'create');
    }

    public function update(User $user, People $people): bool
    {
        $plugin = $this->getPlugin();
        
        // Check if user has edit permission for people
        if (!$plugin->hasResourcePermission($user, $people->team, 'people', 'edit')) {
            return false;
        }
        
        // If user has delete permission, they can edit anything
        if ($plugin->hasResourcePermission($user, $people->team, 'people', 'delete')) {
            return true;
        }
        
        // If user only has edit permission, they can only edit their own entries
        return $people->creator_id === $user->id;
    }

    public function delete(User $user, People $people): bool
    {
        $plugin = $this->getPlugin();
        
        // Check if user has delete permission for people
        if (!$plugin->hasResourcePermission($user, $people->team, 'people', 'delete')) {
            return false;
        }
        
        // If user has delete permission, they can delete anything
        return true;
    }

    public function restore(User $user, People $people): bool
    {
        return $this->delete($user, $people);
    }

    public function forceDelete(User $user, People $people): bool
    {
        return $this->delete($user, $people);
    }
}