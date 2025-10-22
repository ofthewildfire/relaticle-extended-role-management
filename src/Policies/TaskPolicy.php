<?php

declare(strict_types=1);

namespace Ofthewildfire\EnhancedRoleSystem\Policies;

use App\Models\Task;
use App\Models\User;
use Ofthewildfire\EnhancedRoleSystemPlugin;
use Filament\Facades\Filament;

final readonly class TaskPolicy
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

    public function view(User $user, Task $task): bool
    {
        return $user->belongsToTeam($task->team);
    }

    public function create(User $user): bool
    {
        $team = Filament::getTenant();
        $plugin = $this->getPlugin();
        return $team && $plugin->hasMinimumRole($user, $team, 'member');
    }

    public function update(User $user, Task $task): bool
    {
        $plugin = $this->getPlugin();
        
        // Admins can edit anything
        if ($plugin->hasMinimumRole($user, $task->team, 'admin')) {
            return true;
        }
        
        // Members can only edit their own entries
        if ($plugin->hasMinimumRole($user, $task->team, 'member')) {
            return $task->creator_id === $user->id;
        }
        
        return false;
    }

    public function delete(User $user, Task $task): bool
    {
        return $this->update($user, $task);
    }

    public function restore(User $user, Task $task): bool
    {
        return $this->update($user, $task);
    }

    public function forceDelete(User $user, Task $task): bool
    {
        $plugin = $this->getPlugin();
        return $plugin->hasMinimumRole($user, $task->team, 'admin');
    }
}