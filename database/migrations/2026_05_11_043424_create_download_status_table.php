<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('download_statuses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained('users');
            $table->string('filename');
            $table->enum('status', ['start', 'loading', 'success', 'failed', 'success with error']);
            $table->enum('type', ['raw_data', 'tabulation', 'other']);
            $table->text('system_message')->nullable();
            $table->text('user_message')->nullable();
            $table->foreignId('month_id')->nullable()->constrained('months');
            $table->foreignId('year_id')->nullable()->constrained('years');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('download_status');
    }
};
