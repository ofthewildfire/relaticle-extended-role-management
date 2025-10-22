<?php

declare(strict_types=1);

namespace Ofthewildfire\EnhancedRoleSystem\Policies;

use Ofthewildfire\RelaticleModsPlugin\Models\Ideas;
use App\Models\User;
use Ofthewildfire\EnhancedRoleSystemPlugin;
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
        return $team && $user->belongsToTeam($team);
    }

    public function view(User $user, Ideas $idea): bool
    {
        return $user->belongsToTeam($idea->team);
    }

    public function create(User $user): bool
    {
        $team = Filament::getTenant();
        $plugin = $this->getPlugin();
        return $team && $plugin->hasMinimumRole($user, $team, 'member');
    }

    public function update(User $user, Ideas $idea): bool
    {
        $plugin = $this->getPlugin();
        
        // Admins can edit anything
        if ($plugin->hasMinimumRole($user, $idea->team, 'admin')) {
            return true;
        }
        
        // Members can only edit their own entries
        if ($plugin->hasMinimumRole($user, $idea->team, 'member')) {
            return $idea->created_by === $user->id;
        }
        
        return false;
    }

    public function delete(User $user, Ideas $idea): bool
    {
        return $this->update($user, $idea);
    }

    public function restore(User $user, Ideas $idea): bool
    {
        return $this->update($user, $idea);
    }

    public function forceDelete(User $user, Ideas $idea): bool
    {
        $plugin = $this->getPlugin();
        return $plugin->hasMinimumRole($user, $idea->team, 'admin');
    }
}