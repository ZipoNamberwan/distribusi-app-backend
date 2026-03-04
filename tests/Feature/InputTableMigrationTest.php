<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class InputTableMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_input_table_has_expected_columns(): void
    {
        $this->assertTrue(Schema::hasTable('input'));

        $this->assertTrue(Schema::hasColumns('input', [
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
            'room',
            'bed',
            'room_yesterday',
            'room_in',
            'day',
            'room_out',
            'wna_yesterday',
            'wni_yesterday',
            'wna_in',
            'wni_in',
            'wna_out',
            'wni_out',
            'status',
            'room_per',
            'bed_per_day',
            'created_at',
            'updated_at',
        ]));
    }
}
