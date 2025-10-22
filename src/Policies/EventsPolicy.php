<?php

declare(strict_types=1);

namespace Ofthewildfire\EnhancedRoleSystem\Policies;

use Ofthewildfire\RelaticleModsPlugin\Models\Events;
use App\Models\User;
use Ofthewildfire\EnhancedRoleSystemPlugin;
use Filament\Facades\Filament;

final readonly class EventsPolicy
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

    public function view(User $user, Events $event): bool
    {
        return $user->belongsToTeam($event->team);
    }

    public function create(User $user): bool
    {
        $team = Filament::getTenant();
        $plugin = $this->getPlugin();
        return $team && $plugin->hasMinimumRole($user, $team, 'member');
    }

    public function update(User $user, Events $event): bool
    {
        $plugin = $this->getPlugin();
        
        // Admins can edit anything
        if ($plugin->hasMinimumRole($user, $event->team, 'admin')) {
            return true;
        }
        
        // Members can only edit their own entries
        if ($plugin->hasMinimumRole($user, $event->team, 'member')) {
            return $event->created_by === $user->id;
        }
        
        return false;
    }

    public function delete(User $user, Events $event): bool
    {
        return $this->update($user, $event);
    }

    public function restore(User $user, Events $event): bool
    {
        return $this->update($user, $event);
    }

    public function forceDelete(User $user, Events $event): bool
    {
        $plugin = $this->getPlugin();
        return $plugin->hasMinimumRole($user, $event->team, 'admin');
    }
}