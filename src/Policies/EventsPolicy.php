<?php

declare(strict_types=1);

namespace Ofthewildfire\EnhancedRoleSystem\Policies;

use Ofthewildfire\RelaticleModsPlugin\Models\Events;
use App\Models\User;
use Ofthewildfire\EnhancedRoleSystem\EnhancedRoleSystemPlugin;
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
        if (!$team || !$user->belongsToTeam($team)) {
            return false;
        }
        
        $plugin = $this->getPlugin();
        return $plugin->hasResourcePermission($user, $team, 'events', 'view');
    }

    public function view(User $user, Events $event): bool
    {
        if (!$user->belongsToTeam($event->team)) {
            return false;
        }
        
        $plugin = $this->getPlugin();
        return $plugin->hasResourcePermission($user, $event->team, 'events', 'view');
    }

    public function create(User $user): bool
    {
        $team = Filament::getTenant();
        if (!$team) {
            return false;
        }
        
        $plugin = $this->getPlugin();
        return $plugin->hasResourcePermission($user, $team, 'events', 'create');
    }

    public function update(User $user, Events $event): bool
    {
        $plugin = $this->getPlugin();
        
        // Check if user has edit permission for events
        if (!$plugin->hasResourcePermission($user, $event->team, 'events', 'edit')) {
            return false;
        }
        
        // If user has delete permission, they can edit anything
        if ($plugin->hasResourcePermission($user, $event->team, 'events', 'delete')) {
            return true;
        }
        
        // If user only has edit permission, they can only edit their own entries
        return $event->created_by === $user->id;
    }

    public function delete(User $user, Events $event): bool
    {
        $plugin = $this->getPlugin();
        
        // Check if user has delete permission for events
        if (!$plugin->hasResourcePermission($user, $event->team, 'events', 'delete')) {
            return false;
        }
        
        // If user has delete permission, they can delete anything
        return true;
    }

    public function restore(User $user, Events $event): bool
    {
        return $this->delete($user, $event);
    }

    public function forceDelete(User $user, Events $event): bool
    {
        return $this->delete($user, $event);
    }
}