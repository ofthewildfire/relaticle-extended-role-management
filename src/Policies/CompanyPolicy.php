<?php

declare(strict_types=1);

namespace Ofthewildfire\EnhancedRoleSystem\Policies;

use App\Models\Company;
use App\Models\User;
use Ofthewildfire\EnhancedRoleSystemPlugin;
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
        return $team && $user->belongsToTeam($team);
    }

    public function view(User $user, Company $company): bool
    {
        return $user->belongsToTeam($company->team);
    }

    public function create(User $user): bool
    {
        $team = Filament::getTenant();
        $plugin = $this->getPlugin();
        return $team && $plugin->hasMinimumRole($user, $team, 'member');
    }

    public function update(User $user, Company $company): bool
    {
        $plugin = $this->getPlugin();
        
        // Admins can edit anything
        if ($plugin->hasMinimumRole($user, $company->team, 'admin')) {
            return true;
        }
        
        // Members can only edit their own entries
        if ($plugin->hasMinimumRole($user, $company->team, 'member')) {
            return $company->creator_id === $user->id;
        }
        
        return false;
    }

    public function delete(User $user, Company $company): bool
    {
        return $this->update($user, $company);
    }

    public function restore(User $user, Company $company): bool
    {
        return $this->update($user, $company);
    }

    public function forceDelete(User $user, Company $company): bool
    {
        $plugin = $this->getPlugin();
        return $plugin->hasMinimumRole($user, $company->team, 'admin');
    }
}