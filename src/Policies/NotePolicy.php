<?php

declare(strict_types=1);

namespace Ofthewildfire\EnhancedRoleSystem\Policies;

use App\Models\Note;
use App\Models\User;
use Ofthewildfire\EnhancedRoleSystem\EnhancedRoleSystemPlugin;
use Filament\Facades\Filament;

final readonly class NotePolicy
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
        return $plugin->hasResourcePermission($user, $team, 'notes', 'view');
    }

    public function view(User $user, Note $note): bool
    {
        if (!$user->belongsToTeam($note->team)) {
            return false;
        }
        
        $plugin = $this->getPlugin();
        return $plugin->hasResourcePermission($user, $note->team, 'notes', 'view');
    }

    public function create(User $user): bool
    {
        $team = Filament::getTenant();
        if (!$team) {
            return false;
        }
        
        $plugin = $this->getPlugin();
        return $plugin->hasResourcePermission($user, $team, 'notes', 'create');
    }

    public function update(User $user, Note $note): bool
    {
        $plugin = $this->getPlugin();
        
        // Check if user has edit permission for notes
        if (!$plugin->hasResourcePermission($user, $note->team, 'notes', 'edit')) {
            return false;
        }
        
        // If user has delete permission, they can edit anything
        if ($plugin->hasResourcePermission($user, $note->team, 'notes', 'delete')) {
            return true;
        }
        
        // If user only has edit permission, they can only edit their own entries
        return $note->creator_id === $user->id;
    }

    public function delete(User $user, Note $note): bool
    {
        $plugin = $this->getPlugin();
        
        // Check if user has delete permission for notes
        if (!$plugin->hasResourcePermission($user, $note->team, 'notes', 'delete')) {
            return false;
        }
        
        // If user has delete permission, they can delete anything
        return true;
    }

    public function restore(User $user, Note $note): bool
    {
        return $this->delete($user, $note);
    }

    public function forceDelete(User $user, Note $note): bool
    {
        return $this->delete($user, $note);
    }
}