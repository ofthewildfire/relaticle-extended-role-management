<?php

declare(strict_types=1);

namespace Ofthewildfire\EnhancedRoleSystem\Pages;

use App\Models\User;
use Ofthewildfire\EnhancedRoleSystem\EnhancedRoleSystemPlugin;
use Filament\Actions;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ManageTeamRoles extends Page implements Tables\Contracts\HasTable
{
    use Tables\Concerns\InteractsWithTable;

    protected static string $view = 'filament.pages.manage-team-roles';
    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationLabel = 'Team Roles';
    protected static ?string $navigationGroup = 'Team Management';
    protected static ?int $navigationSort = 10;

    public static function canAccess(): bool
    {
        $user = auth()->user();
        $team = Filament::getTenant();
        
        // Temporarily allow access for super admin email
        if ($user && $user->email === 'fuascailtkirsten@gmail.com') {
            return true;
        }
        
        $plugin = app(EnhancedRoleSystemPlugin::class);
        return $team && $plugin->hasMinimumRole($user, $team, 'admin');
    }

    public function getTitle(): string
    {
        return 'Manage Team Roles';
    }

    public function getHeading(): string
    {
        return 'Team Role Management';
    }

    public function getSubheading(): string
    {
        return 'Manage user roles and permissions for your team.';
    }

    public function table(Table $table): Table
    {
        $plugin = app(EnhancedRoleSystemPlugin::class);
        
        return $table
            ->query(
                User::query()
                    ->whereHas('teams', fn(Builder $q) => 
                        $q->where('teams.id', Filament::getTenant()->id)
                    )
                    ->with(['teams' => fn($q) => 
                        $q->where('teams.id', Filament::getTenant()->id)
                        ->withPivot('role', 'created_at', 'updated_at')
                    ])
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\BadgeColumn::make('role')
                    ->getStateUsing(function (User $record) use ($plugin) {
                        return $plugin->getRoleInTeam($record, Filament::getTenant());
                    })
                    ->formatStateUsing(function ($state) use ($plugin) {
                        return $state ? $plugin->getRoleLabel($state) : 'No Role';
                    })
                    ->color(function ($state) use ($plugin) {
                        return $state ? $plugin->getRoleColor($state) : 'gray';
                    }),
                Tables\Columns\TextColumn::make('joined_at')
                    ->label('Joined')
                    ->getStateUsing(function (User $record) {
                        $team = Filament::getTenant();
                        $membership = DB::table('team_user')
                            ->where('team_id', $team->id)
                            ->where('user_id', $record->id)
                            ->first();
                        
                        return $membership?->created_at;
                    })
                    ->dateTime()
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\Action::make('changeRole')
                    ->label('Change Role')
                    ->icon('heroicon-o-user-circle')
                    ->visible(fn(User $record) => $this->canManageUserRole($record))
                    ->form([
                        Forms\Components\Select::make('role')
                            ->label('Role')
                            ->options(fn() => $this->getAvailableRoles())
                            ->default(function (User $record) use ($plugin) {
                                return $plugin->getRoleInTeam($record, Filament::getTenant());
                            })
                            ->selectablePlaceholder(false)
                            ->required(),
                    ])
                    ->action(function (User $record, array $data) {
                        $this->changeUserRole($record, $data['role']);
                    }),
                Tables\Actions\Action::make('remove')
                    ->label('Remove from Team')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->visible(fn(User $record) => $this->canRemoveUser($record))
                    ->requiresConfirmation()
                    ->action(fn(User $record) => $this->removeUserFromTeam($record)),
            ])
            ->headerActions([
                Tables\Actions\Action::make('addUser')
                    ->label('Add User to Team')
                    ->icon('heroicon-o-plus')
                    ->color('primary')
                    ->form([
                        Forms\Components\Select::make('user_id')
                            ->label('Select User')
                            ->options(fn() => $this->getAllUsers())
                            ->searchable()
                            ->required()
                            ->visible(fn() => $this->isSuperAdmin() || $this->isTeamOwner()),
                        Forms\Components\TextInput::make('email')
                            ->label('Email Address (for new invitations)')
                            ->email()
                            ->visible(fn() => !$this->isSuperAdmin() && !$this->isTeamOwner())
                            ->required(),
                        Forms\Components\Select::make('role')
                            ->label('Role')
                            ->options(fn() => $this->getAvailableRoles())
                            ->default('member')
                            ->required(),
                    ])
                    ->action(function (array $data) {
                        if (isset($data['user_id'])) {
                            $this->addExistingUserToTeam((int) $data['user_id'], $data['role']);
                        } else {
                            $this->inviteUser($data['email'], $data['role']);
                        }
                    }),
            ]);
    }

    protected function getAvailableRoles(): array
    {
        $plugin = app(EnhancedRoleSystemPlugin::class);
        $currentUser = auth()->user();
        $team = Filament::getTenant();
        
        $availableRoles = $plugin->getAvailableRolesForUser($currentUser, $team);
        
        $options = [];
        foreach ($availableRoles as $role) {
            $options[$role] = $plugin->getRoleLabel($role);
        }
        
        return $options;
    }

    protected function canManageUserRole(User $user): bool
    {
        $plugin = app(EnhancedRoleSystemPlugin::class);
        $currentUser = auth()->user();
        $team = Filament::getTenant();
        
        // Can't manage yourself
        if ($user->id === $currentUser->id) {
            return false;
        }
        
        // Team owners can manage ANYONE (including other super admins they added)
        if ($team->user_id === $currentUser->id) {
            return true;
        }
        
        $currentUserRole = $plugin->getRoleInTeam($currentUser, $team);
        $targetUserRole = $plugin->getRoleInTeam($user, $team);
        
        if (!$currentUserRole || !$targetUserRole) {
            return false;
        }
        
        // Check role hierarchy for non-owners
        $roleHierarchy = [
            'viewer' => 1,
            'member' => 2,
            'admin' => 3,
            'super_admin' => 4,
        ];
        
        $currentLevel = $roleHierarchy[$currentUserRole] ?? 0;
        $targetLevel = $roleHierarchy[$targetUserRole] ?? 0;
        
        return $currentLevel > $targetLevel || 
               ($currentUserRole === 'admin' && $targetUserRole !== 'super_admin');
    }

    protected function canRemoveUser(User $user): bool
    {
        return $this->canManageUserRole($user);
    }

    protected function changeUserRole(User $user, string $newRole): void
    {
        $team = Filament::getTenant();
        
        $user->teams()->updateExistingPivot($team->id, [
            'role' => $newRole,
        ]);
        
        $plugin = app(EnhancedRoleSystemPlugin::class);
        
        Notification::make()
            ->title('Role Updated')
            ->body("User {$user->name} role changed to {$plugin->getRoleLabel($newRole)}")
            ->success()
            ->send();
            
        // Refresh the table
        $this->resetTable();
    }

    protected function removeUserFromTeam(User $user): void
    {
        $team = Filament::getTenant();
        $user->teams()->detach($team->id);
        
        Notification::make()
            ->title('User Removed')
            ->body("User {$user->name} has been removed from the team")
            ->success()
            ->send();
            
        // Refresh the table
        $this->resetTable();
    }

    protected function inviteUser(string $email, string $role): void
    {
        // For now, just show a notification
        $plugin = app(EnhancedRoleSystemPlugin::class);
        
        Notification::make()
            ->title('Invitation Sent')
            ->body("Invitation sent to {$email} with role {$plugin->getRoleLabel($role)}")
            ->success()
            ->send();
    }

    protected function getAllUsers(): array
    {
        // Get all users in the application for selection
        $users = User::withoutGlobalScopes()->orderBy('name')->get();
        
        $result = [];
        foreach ($users as $user) {
            $result[$user->id] = $user->name . ' (' . $user->email . ')';
        }
        
        return $result;
    }

    protected function getAvailableUsers(): array
    {
        $currentTeam = Filament::getTenant();
        
        if (!$currentTeam) {
            return [];
        }
        
        // Get users not already in this team
        $existingUserIds = DB::table('team_user')
            ->where('team_id', $currentTeam->id)
            ->pluck('user_id')
            ->toArray();
        
        return User::whereNotIn('id', $existingUserIds)
            ->orderBy('name')
            ->get()
            ->mapWithKeys(function (User $user) {
                return [$user->id => $user->name . ' (' . $user->email . ')'];
            })
            ->toArray();
    }

    protected function isSuperAdmin(): bool
    {
        $plugin = app(EnhancedRoleSystemPlugin::class);
        return $plugin->isSuperAdmin(auth()->user());
    }

    protected function isTeamOwner(): bool
    {
        $team = Filament::getTenant();
        $user = auth()->user();
        
        return $team && $user && $team->user_id === $user->id;
    }

    protected function addExistingUserToTeam(int $userId, string $role): void
    {
        // Use withoutGlobalScopes to find the user
        $user = User::withoutGlobalScopes()->find($userId);
        $team = Filament::getTenant();
        
        if (!$user) {
            Notification::make()
                ->title('Error')
                ->body("User with ID {$userId} not found")
                ->danger()
                ->send();
            return;
        }
        
        if (!$team) {
            Notification::make()
                ->title('Error')
                ->body('Current team not found')
                ->danger()
                ->send();
            return;
        }
        
        try {
            // Check if already a member
            $exists = DB::table('team_user')
                ->where('team_id', $team->id)
                ->where('user_id', $userId)
                ->exists();
            
            if ($exists) {
                Notification::make()
                    ->title('Already a Member')
                    ->body("User {$user->name} is already a member of this team")
                    ->warning()
                    ->send();
                return;
            }
            
            // Add to team using the relationship method
            $user->teams()->attach($team->id, [
                'role' => $role,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            $plugin = app(EnhancedRoleSystemPlugin::class);
            
            Notification::make()
                ->title('User Added Successfully')
                ->body("User {$user->name} has been added to the team with role {$plugin->getRoleLabel($role)}")
                ->success()
                ->send();
                
            // Refresh the table
            $this->resetTable();
            
        } catch (\Exception $e) {
            Notification::make()
                ->title('Error Adding User')
                ->body("Failed to add user: " . $e->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function searchUsers(string $search): array
    {
        // Search ALL users in the app
        return User::where(function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%");
            })
            ->limit(50)
            ->orderBy('name')
            ->get()
            ->mapWithKeys(function (User $user) {
                return [$user->id => $user->name . ' (' . $user->email . ')'];
            })
            ->toArray();
    }

    protected function getUserLabel($value): ?string
    {
        if (!$value) return null;
        
        $user = User::find($value);
        return $user ? $user->name . ' (' . $user->email . ')' : null;
    }
}