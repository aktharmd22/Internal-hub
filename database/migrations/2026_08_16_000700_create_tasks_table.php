<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('client_id')->nullable()->constrained()->nullOnDelete();

            // Set when the task was raised automatically by a renewal, which is
            // how a reminder turns into something that cannot be ignored.
            $table->foreignId('asset_id')->nullable()->constrained()->nullOnDelete();

            $table->string('reference', 16)->unique();
            $table->string('title');
            $table->longText('description')->nullable();
            $table->string('priority', 10)->default('normal');
            $table->string('status', 16)->default('open');

            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('due_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedInteger('estimated_minutes')->nullable();

            $table->foreignId('parent_id')->nullable()->constrained('tasks')->cascadeOnDelete();
            $table->string('source', 20)->default('manual');

            // A quality signal per employee once there is enough history.
            $table->unsignedInteger('reopen_count')->default(0);

            $table->text('hold_reason')->nullable();
            $table->foreignId('blocked_by_task_id')->nullable()->constrained('tasks')->nullOnDelete();

            $table->timestamp('last_activity_at')->nullable();
            $table->boolean('is_archived')->default(false);
            $table->timestamps();

            $table->index(['assigned_to', 'status']);
            $table->index(['due_at', 'status']);
            $table->index(['client_id', 'status']);
            $table->index(['project_id', 'status']);
            $table->index('asset_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
