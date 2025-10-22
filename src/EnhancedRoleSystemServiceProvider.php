<?php

declare(strict_types=1);

namespace Ofthewildfire\EnhancedRoleSystem;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Ofthewildfire\EnhancedRoleSystem\Policies\EnhancedTeamPolicy;
use Ofthewildfire\EnhancedRoleSystem\Policies\CompanyPolicy;
use Ofthewildfire\EnhancedRoleSystem\Policies\PeoplePolicy;
use Ofthewildfire\EnhancedRoleSystem\Policies\TaskPolicy;
use Ofthewildfire\EnhancedRoleSystem\Policies\OpportunityPolicy;
use Ofthewildfire\EnhancedRoleSystem\Policies\NotePolicy;
use Ofthewildfire\EnhancedRoleSystem\Policies\EventsPolicy;
use Ofthewildfire\EnhancedRoleSystem\Policies\IdeasPolicy;
use Ofthewildfire\EnhancedRoleSystem\Policies\ProjectsPolicy;
use App\Models\Team;
use App\Models\Company;
use App\Models\People;
use App\Models\Task;
use App\Models\Opportunity;
use App\Models\Note;
use Ofthewildfire\RelaticleModsPlugin\Models\Events;
use Ofthewildfire\RelaticleModsPlugin\Models\Ideas;
use Ofthewildfire\RelaticleModsPlugin\Models\Projects;

class EnhancedRoleSystemServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register any services
    }

    public function boot(): void
    {
      $this->loadMigrationsFrom(__DIR__.'/../database');
      $this->loadViewsFrom(__DIR__.'/../resources/views', 'enhanced-role-system');
      $this->registerPolicies();
      $this->publishMigrations();
    }

    protected function registerPolicies(): void
    {
        Gate::policy(Team::class, EnhancedTeamPolicy::class);
        Gate::policy(Company::class, CompanyPolicy::class);
        Gate::policy(People::class, PeoplePolicy::class);
        Gate::policy(Task::class, TaskPolicy::class);
        Gate::policy(Opportunity::class, OpportunityPolicy::class);
        Gate::policy(Note::class, NotePolicy::class);
        Gate::policy(Events::class, EventsPolicy::class);
        Gate::policy(Ideas::class, IdeasPolicy::class);
        Gate::policy(Projects::class, ProjectsPolicy::class);
    }

    protected function publishMigrations(): void
    {
        // Migrations in the regular app place... the default db/migrations :)
    }

}