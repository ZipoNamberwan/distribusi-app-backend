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
        Schema::create('final_numbers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('month_id')->nullable()->constrained('months');
            $table->foreignId('year_id')->constrained('years');
            $table->foreignId('category_id')->constrained('categories');
            $table->foreignId('regency_id')->constrained('regencies');
            $table->decimal('value', 10, 2)->default(0)->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('final_numbers');
    }
};
