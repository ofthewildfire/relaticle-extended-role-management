<?php

declare(strict_types=1);

namespace Ofthewildfire\EnhancedRoleSystem\Policies;

use App\Models\Task;
use App\Models\User;
use Ofthewildfire\EnhancedRoleSystem\EnhancedRoleSystemPlugin;
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
        if (!$team || !$user->belongsToTeam($team)) {
            return false;
        }
        
        $plugin = $this->getPlugin();
        return $plugin->hasResourcePermission($user, $team, 'tasks', 'view');
    }

    public function view(User $user, Task $task): bool
    {
        if (!$user->belongsToTeam($task->team)) {
            return false;
        }
        
        $plugin = $this->getPlugin();
        return $plugin->hasResourcePermission($user, $task->team, 'tasks', 'view');
    }

    public function create(User $user): bool
    {
        $team = Filament::getTenant();
        if (!$team) {
            return false;
        }
        
        $plugin = $this->getPlugin();
        return $plugin->hasResourcePermission($user, $team, 'tasks', 'create');
    }

    public function update(User $user, Task $task): bool
    {
        $plugin = $this->getPlugin();
        
        // Check if user has edit permission for tasks
        if (!$plugin->hasResourcePermission($user, $task->team, 'tasks', 'edit')) {
            return false;
        }
        
        // If user has delete permission, they can edit anything
        if ($plugin->hasResourcePermission($user, $task->team, 'tasks', 'delete')) {
            return true;
        }
        
        // If user only has edit permission, they can only edit their own entries
        return $task->creator_id === $user->id;
    }

    public function delete(User $user, Task $task): bool
    {
        $plugin = $this->getPlugin();
        
        // Check if user has delete permission for tasks
        if (!$plugin->hasResourcePermission($user, $task->team, 'tasks', 'delete')) {
            return false;
        }
        
        // If user has delete permission, they can delete anything
        return true;
    }

    public function restore(User $user, Task $task): bool
    {
        return $this->delete($user, $task);
    }

    public function forceDelete(User $user, Task $task): bool
    {
        return $this->delete($user, $task);
    }
}