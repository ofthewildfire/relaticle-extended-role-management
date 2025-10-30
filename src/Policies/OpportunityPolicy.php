<?php

declare(strict_types=1);

namespace Ofthewildfire\EnhancedRoleSystem\Policies;

use App\Models\Opportunity;
use App\Models\User;
use Ofthewildfire\EnhancedRoleSystem\EnhancedRoleSystemPlugin;
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
        if (!$team || !$user->belongsToTeam($team)) {
            return false;
        }
        
        $plugin = $this->getPlugin();
        return $plugin->hasResourcePermission($user, $team, 'opportunities', 'view');
    }

    public function view(User $user, Opportunity $opportunity): bool
    {
        if (!$user->belongsToTeam($opportunity->team)) {
            return false;
        }
        
        $plugin = $this->getPlugin();
        return $plugin->hasResourcePermission($user, $opportunity->team, 'opportunities', 'view');
    }

    public function create(User $user): bool
    {
        $team = Filament::getTenant();
        if (!$team) {
            return false;
        }
        
        $plugin = $this->getPlugin();
        return $plugin->hasResourcePermission($user, $team, 'opportunities', 'create');
    }

    public function update(User $user, Opportunity $opportunity): bool
    {
        $plugin = $this->getPlugin();
        
        // Check if user has edit permission for opportunities
        if (!$plugin->hasResourcePermission($user, $opportunity->team, 'opportunities', 'edit')) {
            return false;
        }
        
        // If user has delete permission, they can edit anything
        if ($plugin->hasResourcePermission($user, $opportunity->team, 'opportunities', 'delete')) {
            return true;
        }
        
        // If user only has edit permission, they can only edit their own entries
        return $opportunity->creator_id === $user->id;
    }

    public function delete(User $user, Opportunity $opportunity): bool
    {
        $plugin = $this->getPlugin();
        
        // Check if user has delete permission for opportunities
        if (!$plugin->hasResourcePermission($user, $opportunity->team, 'opportunities', 'delete')) {
            return false;
        }
        
        // If user has delete permission, they can delete anything
        return true;
    }

    public function restore(User $user, Opportunity $opportunity): bool
    {
        return $this->delete($user, $opportunity);
    }

    public function forceDelete(User $user, Opportunity $opportunity): bool
    {
        return $this->delete($user, $opportunity);
    }
}