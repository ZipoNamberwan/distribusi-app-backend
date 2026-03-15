<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('error_types', function (Blueprint $table) {
            $table->id();
            $table->string('column_name');
            $table->string('code')->nullable();
            $table->string('color')->nullable();
        });

        Schema::create('confirmations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('input_id')->constrained('inputs');
            $table->foreignId('error_type_id')->constrained('error_types');
            $table->enum('status', ['not_confirmed', 'confirmed', 'approved', 'pending', 'other'])->default('not_confirmed');
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(false);
            $table->foreignUuid('sent_by_id')->nullable()->constrained('users');
            $table->foreignUuid('approved_by_id')->nullable()->constrained('users');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('confirmations');
        Schema::dropIfExists('error_types');
    }
};
