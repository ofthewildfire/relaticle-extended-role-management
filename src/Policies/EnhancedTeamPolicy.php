<?php

declare(strict_types=1);

namespace Ofthewildfire\EnhancedRoleSystem\Policies;

use App\Models\Team;
use App\Models\User;
use Ofthewildfire\EnhancedRoleSystem\EnhancedRoleSystemPlugin;
use Illuminate\Auth\Access\HandlesAuthorization;

final readonly class EnhancedTeamPolicy
{
    use HandlesAuthorization;

    protected function getPlugin(): EnhancedRoleSystemPlugin
    {
        return app(EnhancedRoleSystemPlugin::class);
    }

    public function viewAny(): bool
    {
        return true;
    }

    public function view(User $user, Team $team): bool
    {
        return $user->belongsToTeam($team);
    }

    public function create(User $user): bool
    {
        $plugin = $this->getPlugin();
        return $plugin->isSuperAdmin($user) || $user->ownedTeams()->count() < 3;
    }

    public function update(User $user, Team $team): bool
    {
        $plugin = $this->getPlugin();
        return $plugin->hasMinimumRole($user, $team, 'admin');
    }

    public function addTeamMember(User $user, Team $team): bool
    {
        $plugin = $this->getPlugin();
        return $plugin->hasMinimumRole($user, $team, 'admin');
    }

    public function updateTeamMember(User $user, Team $team): bool
    {
        $plugin = $this->getPlugin();
        return $plugin->hasMinimumRole($user, $team, 'admin');
    }

    public function removeTeamMember(User $user, Team $team): bool
    {
        $plugin = $this->getPlugin();
        return $plugin->hasMinimumRole($user, $team, 'admin');
    }

    public function delete(User $user, Team $team): bool
    {
        $plugin = $this->getPlugin();
        return $user->ownsTeam($team) || $plugin->isSuperAdmin($user);
    }

    public function manageRoles(User $user, Team $team): bool
    {
        $plugin = $this->getPlugin();
        return $plugin->hasMinimumRole($user, $team, 'admin');
    }
}