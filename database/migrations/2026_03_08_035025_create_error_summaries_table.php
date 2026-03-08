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
        Schema::create('errors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->nullable();
        });

        Schema::create('error_summaries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('regency_id')->constrained('regencies');
            $table->foreignId('month_id')->constrained('months');
            $table->foreignId('year_id')->constrained('years');
            $table->foreignId('error_id')->constrained('errors');
            $table->foreignId('category_id')->constrained('categories');
            $table->integer('value')->default(0);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('error_summaries');
    }
};
