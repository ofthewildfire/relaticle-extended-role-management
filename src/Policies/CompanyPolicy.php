<?php

declare(strict_types=1);

namespace Ofthewildfire\EnhancedRoleSystem\Policies;

use App\Models\Company;
use App\Models\User;
use Ofthewildfire\EnhancedRoleSystem\EnhancedRoleSystemPlugin;
use Filament\Facades\Filament;

final readonly class CompanyPolicy
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
        return $plugin->hasResourcePermission($user, $team, 'companies', 'view');
    }

    public function view(User $user, Company $company): bool
    {
        if (!$user->belongsToTeam($company->team)) {
            return false;
        }
        
        $plugin = $this->getPlugin();
        return $plugin->hasResourcePermission($user, $company->team, 'companies', 'view');
    }

    public function create(User $user): bool
    {
        $team = Filament::getTenant();
        if (!$team) {
            return false;
        }
        
        $plugin = $this->getPlugin();
        return $plugin->hasResourcePermission($user, $team, 'companies', 'create');
    }

    public function update(User $user, Company $company): bool
    {
        $plugin = $this->getPlugin();
        
        // Check if user has edit permission for companies
        if (!$plugin->hasResourcePermission($user, $company->team, 'companies', 'edit')) {
            return false;
        }
        
        // If user has delete permission, they can edit anything
        if ($plugin->hasResourcePermission($user, $company->team, 'companies', 'delete')) {
            return true;
        }
        
        // If user only has edit permission, they can only edit their own entries
        return $company->creator_id === $user->id;
    }

    public function delete(User $user, Company $company): bool
    {
        $plugin = $this->getPlugin();
        
        // Check if user has delete permission for companies
        if (!$plugin->hasResourcePermission($user, $company->team, 'companies', 'delete')) {
            return false;
        }
        
        // If user has delete permission, they can delete anything
        return true;
    }

    public function restore(User $user, Company $company): bool
    {
        return $this->delete($user, $company);
    }

    public function forceDelete(User $user, Company $company): bool
    {
        return $this->delete($user, $company);
    }
}