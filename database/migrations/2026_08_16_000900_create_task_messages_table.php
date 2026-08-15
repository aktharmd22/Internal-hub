<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();

            // Null for system messages: status changes, reassignments, due-date
            // changes. They live in the same thread so the timeline and the
            // conversation are one continuous history.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->text('body')->nullable();
            $table->string('type', 20)->default('text');
            $table->foreignId('reply_to_id')->nullable()->constrained('task_messages')->nullOnDelete();

            // Voice notes
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->json('waveform')->nullable();
            $table->text('transcript')->nullable();

            $table->timestamp('edited_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index(['task_id', 'id']);
            $table->index(['task_id', 'created_at']);
        });

        Schema::create('message_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_message_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('read_at');

            $table->unique(['task_message_id', 'user_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_reads');
        Schema::dropIfExists('task_messages');
    }
};
