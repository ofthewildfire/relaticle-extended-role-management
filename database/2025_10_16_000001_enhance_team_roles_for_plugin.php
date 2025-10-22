<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // First, update existing NULL roles to 'member' for regular users
        DB::table('team_user')
            ->whereNull('role')
            ->update(['role' => 'member']);

        // Update team owners to 'admin' role
        $teams = DB::table('teams')->get();
        foreach ($teams as $team) {
            DB::table('team_user')
                ->where('team_id', $team->id)
                ->where('user_id', $team->user_id)
                ->update(['role' => 'admin']);
        }

        // Set super admin role for configured admin email
        $superAdminEmail = 'fuascailtkirsten@gmail.com';
        $user = DB::table('users')->where('email', $superAdminEmail)->first();
        if ($user) {
            // Give super admin role in all teams they belong to
            DB::table('team_user')
                ->where('user_id', $user->id)
                ->update(['role' => 'super_admin']);
        }

        // Make role column non-nullable with default
        Schema::table('team_user', function (Blueprint $table) {
            $table->string('role')->default('member')->change();
        });

        // Add index for better performance on role queries
        Schema::table('team_user', function (Blueprint $table) {
            $table->index(['team_id', 'role'], 'team_user_team_role_index');
        });
    }

    public function down(): void
    {
        // Remove the index
        Schema::table('team_user', function (Blueprint $table) {
            $table->dropIndex('team_user_team_role_index');
        });

        // Make role column nullable again
        Schema::table('team_user', function (Blueprint $table) {
            $table->string('role')->nullable()->change();
        });

        // Reset all roles to NULL
        DB::table('team_user')->update(['role' => null]);
    }
};