<?php

declare(strict_types=1);

namespace Ofthewildfire\EnhancedRoleSystem\Policies;

use App\Models\Opportunity;
use App\Models\User;
use Ofthewildfire\EnhancedRoleSystemPlugin;
use Filament\Facades\Filament;

final readonly class OpportunityPolicy
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

    public function view(User $user, Opportunity $opportunity): bool
    {
        return $user->belongsToTeam($opportunity->team);
    }

    public function create(User $user): bool
    {
        $team = Filament::getTenant();
        $plugin = $this->getPlugin();
        return $team && $plugin->hasMinimumRole($user, $team, 'member');
    }

    public function update(User $user, Opportunity $opportunity): bool
    {
        $plugin = $this->getPlugin();
        
        // Admins can edit anything
        if ($plugin->hasMinimumRole($user, $opportunity->team, 'admin')) {
            return true;
        }
        
        // Members can only edit their own entries
        if ($plugin->hasMinimumRole($user, $opportunity->team, 'member')) {
            return $opportunity->creator_id === $user->id;
        }
        
        return false;
    }

    public function delete(User $user, Opportunity $opportunity): bool
    {
        return $this->update($user, $opportunity);
    }

    public function restore(User $user, Opportunity $opportunity): bool
    {
        return $this->update($user, $opportunity);
    }

    public function forceDelete(User $user, Opportunity $opportunity): bool
    {
        $plugin = $this->getPlugin();
        return $plugin->hasMinimumRole($user, $opportunity->team, 'admin');
    }
}