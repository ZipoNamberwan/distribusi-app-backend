<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TabulationTableMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_tabulation_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('tabulation'));

        $this->assertTrue(Schema::hasColumns('tabulation', [
            'id',
            'tanggal_update',
            'tarikan_ke',
            'idunik',
            'tahun',
            'bulan',
            'kode_prov',
            'kode_kab',
            'kode_kec',
            'kode_des',
            'status_kur',
            'jenis_ako',
            'kelas_ako',
            'nama_kor',
            'alamat',
            'mkts',
            'mktj',
            'tpk',
            'mta',
            'ta',
            'mtnus',
            'tnus',
            'rlmta',
            'rlmtnus',
            'mtgab',
            'tgab',
            'rlmtgab',
            'gpr',
            'tptt',
            'jumlah_hari',
            'error_tpk',
            'error_rlmta',
            'error_rlmtnus',
            'error_gpr',
            'error_tptt',
            'error_hari',
            'jumlah_error',
            'status_konf',
            'created_at',
            'updated_at',
        ]));
    }
}
