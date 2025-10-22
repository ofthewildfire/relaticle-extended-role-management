<?php

declare(strict_types=1);

namespace Ofthewildfire\EnhancedRoleSystem\Policies;

use App\Models\People;
use App\Models\User;
use Ofthewildfire\EnhancedRoleSystemPlugin;
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
        return $team && $user->belongsToTeam($team);
    }

    public function view(User $user, People $people): bool
    {
        return $user->belongsToTeam($people->team);
    }

    public function create(User $user): bool
    {
        $team = Filament::getTenant();
        $plugin = $this->getPlugin();
        return $team && $plugin->hasMinimumRole($user, $team, 'member');
    }

    public function update(User $user, People $people): bool
    {
        $plugin = $this->getPlugin();
        
        // Admins can edit anything
        if ($plugin->hasMinimumRole($user, $people->team, 'admin')) {
            return true;
        }
        
        // Members can only edit their own entries
        if ($plugin->hasMinimumRole($user, $people->team, 'member')) {
            return $people->creator_id === $user->id;
        }
        
        return false;
    }

    public function delete(User $user, People $people): bool
    {
        return $this->update($user, $people);
    }

    public function restore(User $user, People $people): bool
    {
        return $this->update($user, $people);
    }

    public function forceDelete(User $user, People $people): bool
    {
        $plugin = $this->getPlugin();
        return $plugin->hasMinimumRole($user, $people->team, 'admin');
    }
}