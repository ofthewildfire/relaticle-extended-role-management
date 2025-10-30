<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_user_resource_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('resource'); // tasks, people, companies, events, ideas, notes, opportunities, projects
            $table->enum('permission_level', ['none', 'view', 'create', 'edit', 'delete'])->default('none');
            $table->timestamps();
            
            // Unique constraint to prevent duplicate entries
            $table->unique(['team_id', 'user_id', 'resource'], 'team_user_resource_unique');
            
            // Indexes for performance
            $table->index(['team_id', 'user_id'], 'team_user_permissions_index');
            $table->index(['resource', 'permission_level'], 'resource_permission_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_user_resource_permissions');
    }
};