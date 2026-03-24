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
        Schema::create('phenomenas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('month_id')->nullable()->constrained('months');
            $table->foreignId('year_id')->constrained('years');
            $table->foreignId('regency_id')->constrained('regencies');
            $table->text('description');
            $table->timestamps();
        });
    }

    /** 
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('phenomenas');
    }
};