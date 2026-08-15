<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->nullable()->constrained()->nullOnDelete();
            $table->string('label');
            $table->string('username')->nullable();

            // Encrypted by an Eloquent cast, so these are ciphertext at rest
            // and never appear in a query log or a database dump in the clear.
            $table->text('password')->nullable();
            $table->text('notes')->nullable();

            $table->string('url')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['client_id', 'label']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('credentials');
    }
};
