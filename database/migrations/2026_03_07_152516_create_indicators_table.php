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
        Schema::create('indicators', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code');
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code');
        });

        Schema::create('indicator_values', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('regency_id')->constrained('regencies');
            $table->foreignId('month_id')->constrained('months');
            $table->foreignId('year_id')->constrained('years');
            $table->foreignId('indicator_id')->constrained('indicators');
            $table->foreignId('category_id')->constrained('categories');
            $table->decimal('value', 8, 2); 

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('indicators');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('indicators');
    }
};
