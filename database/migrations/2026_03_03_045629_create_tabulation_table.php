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
        Schema::create('tabulation', function (Blueprint $table) {
            $table->id();

            // Reference / identifier columns
            $table->date('tanggal_update')->nullable()->index();
            $table->string('tarikan_ke', 32)->nullable();
            $table->string('idunik', 64)->nullable()->index();

            $table->unsignedSmallInteger('tahun')->nullable()->index();
            $table->unsignedTinyInteger('bulan')->nullable()->index();

            $table->string('kode_prov', 8)->nullable()->index();
            $table->string('kode_kab', 16)->nullable()->index();
            $table->string('kode_kec', 8)->nullable()->index();
            $table->string('kode_desa', 16)->nullable()->index();

            $table->unsignedTinyInteger('status_kunjungan')->nullable()->index();
            $table->unsignedTinyInteger('jenis_akomodasi')->nullable()->index();
            $table->unsignedTinyInteger('kelas_akomodasi')->nullable()->index();
            $table->string('nama_komersial')->nullable();
            $table->text('alamat')->nullable();

            // Metric columns from this image
            $table->unsignedInteger('mkts')->default(0);
            $table->unsignedInteger('mktj')->default(0);
            $table->decimal('tpk', 12, 6)->default(0);
            $table->unsignedInteger('mta')->default(0);

            $table->unsignedInteger('ta')->default(0);
            $table->unsignedInteger('mtnus')->default(0);
            $table->unsignedInteger('tnus')->default(0);

            $table->decimal('rlmta', 12, 6)->default(0);
            $table->decimal('rlmtnus', 12, 6)->default(0);

            $table->unsignedInteger('mtgab')->default(0);
            $table->unsignedInteger('tgab')->default(0);
            $table->decimal('rlmtgab', 12, 6)->default(0);

            $table->decimal('gpr', 12, 6)->default(0);
            $table->decimal('tptt', 12, 6)->default(0);

            $table->unsignedTinyInteger('jumlah_hari')->nullable();

            $table->unsignedInteger('error_tpk')->default(0);
            $table->unsignedInteger('error_rlmta')->default(0);
            $table->unsignedInteger('error_rlmtnus')->default(0);
            $table->unsignedInteger('error_gpr')->default(0);
            $table->unsignedInteger('error_tptt')->default(0);
            $table->unsignedInteger('error_hari')->default(0);
            $table->unsignedInteger('jumlah_error')->default(0);

            $table->string('status_konf', 32)->nullable()->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tabulation');
    }
};
