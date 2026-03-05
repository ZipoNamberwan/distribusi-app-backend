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
        Schema::create('input', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->date('tanggal_update')->nullable()->index();
            $table->string('tarikan_ke', 32)->nullable();
            $table->string('idunik', 64)->nullable()->index();

            $table->unsignedSmallInteger('tahun')->nullable()->index();
            $table->unsignedTinyInteger('bulan')->nullable()->index();

            $table->string('kode_prov', 8)->nullable()->index();
            $table->string('kode_kec', 8)->nullable()->index();
            $table->string('kode_desa', 16)->nullable()->index();

            $table->unsignedTinyInteger('status_kunjungan')->nullable()->index();
            $table->unsignedTinyInteger('jenis_akomodasi')->nullable()->index();
            $table->unsignedTinyInteger('kelas_akomodasi')->nullable()->index();
            $table->string('nama_komersial')->nullable();
            $table->text('alamat')->nullable();

            $table->unsignedInteger('room')->default(0);
            $table->unsignedInteger('bed')->default(0);
            $table->unsignedInteger('room_yesterday')->default(0);
            $table->unsignedInteger('room_in')->default(0);

            $table->string('kode_kab', 16)->index();
            $table->unsignedTinyInteger('day')->nullable()->index();

            $table->unsignedInteger('room_out')->default(0);

            $table->unsignedInteger('wna_yesterday')->default(0);
            $table->unsignedInteger('wni_yesterday')->default(0);

            $table->unsignedInteger('wna_in')->default(0);
            $table->unsignedInteger('wni_in')->default(0);

            $table->unsignedInteger('wna_out')->default(0);
            $table->unsignedInteger('wni_out')->default(0);

            $table->string('status', 32)->nullable()->index();

            $table->unsignedInteger('room_per')->default(0);
            $table->unsignedInteger('bed_per_day')->default(0);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('input');
    }
};
