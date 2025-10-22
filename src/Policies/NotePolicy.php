<?php

declare(strict_types=1);

namespace Ofthewildfire\EnhancedRoleSystem\Policies;

use App\Models\Note;
use App\Models\User;
use Ofthewildfire\EnhancedRoleSystemPlugin;
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
        return $team && $user->belongsToTeam($team);
    }

    public function view(User $user, Note $note): bool
    {
        return $user->belongsToTeam($note->team);
    }

    public function create(User $user): bool
    {
        $team = Filament::getTenant();
        $plugin = $this->getPlugin();
        return $team && $plugin->hasMinimumRole($user, $team, 'member');
    }

    public function update(User $user, Note $note): bool
    {
        $plugin = $this->getPlugin();
        
        // Admins can edit anything
        if ($plugin->hasMinimumRole($user, $note->team, 'admin')) {
            return true;
        }
        
        // Members can only edit their own entries
        if ($plugin->hasMinimumRole($user, $note->team, 'member')) {
            return $note->creator_id === $user->id;
        }
        
        return false;
    }

    public function delete(User $user, Note $note): bool
    {
        return $this->update($user, $note);
    }

    public function restore(User $user, Note $note): bool
    {
        return $this->update($user, $note);
    }

    public function forceDelete(User $user, Note $note): bool
    {
        $plugin = $this->getPlugin();
        return $plugin->hasMinimumRole($user, $note->team, 'admin');
    }
}