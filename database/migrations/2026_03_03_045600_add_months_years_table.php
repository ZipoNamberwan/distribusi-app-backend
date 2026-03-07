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
        Schema::create('months', function (Blueprint $table) {
            $table->id()->primary();
            $table->string('code');
            $table->string('name');
        });

        Schema::create('years', function (Blueprint $table) {
            $table->id()->primary();
            $table->string('code');
            $table->string('name');
        });

        Schema::create('regencies', function (Blueprint $table) {
            $table->id()->primary();
            $table->string('short_code');
            $table->string('long_code');
            $table->string('name');
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('months');
        Schema::dropIfExists('years');
    }
};
