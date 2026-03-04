<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class DataUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_upload_xlsx_and_import_input(): void
    {
        $user = User::factory()->create();

        $temp = tempnam(sys_get_temp_dir(), 'input_upload_');
        $path = $temp !== false ? $temp.'.xlsx' : sys_get_temp_dir().'/input_upload.xlsx';

        if ($temp !== false) {
            @rename($temp, $path);
        }

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->fromArray([
            ['tanggal_update', 'tarikan_ke', 'idunik', 'kode_kab'],
            ['27/01/2026 0:00:00', '1', 'ID-1', 'KAB-1'],
            [null, null, null, null],
        ], null, 'A1');

        (new Xlsx($spreadsheet))->save($path);

        $upload = new UploadedFile(
            $path,
            'input.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true,
        );

        $response = $this->actingAs($user)->post(route('data.upload.store'), [
            'target' => 'input',
            'file' => $upload,
        ]);

        $response->assertRedirect();

        $this->assertDatabaseCount('input', 1);
        $this->assertDatabaseHas('input', [
            'idunik' => 'ID-1',
            'kode_kab' => 'KAB-1',
            'tarikan_ke' => '1',
            'tanggal_update' => '2026-01-27',
        ]);

        @unlink($path);
    }

    public function test_upload_requires_kode_kab_for_input(): void
    {
        $user = User::factory()->create();

        $temp = tempnam(sys_get_temp_dir(), 'input_upload_');
        $path = $temp !== false ? $temp.'.xlsx' : sys_get_temp_dir().'/input_upload_missing.xlsx';

        if ($temp !== false) {
            @rename($temp, $path);
        }

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->fromArray([
            ['tanggal_update', 'tarikan_ke', 'idunik'],
            ['27/01/2026 0:00:00', '1', 'ID-1'],
        ], null, 'A1');

        (new Xlsx($spreadsheet))->save($path);

        $upload = new UploadedFile(
            $path,
            'input.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true,
        );

        $response = $this->from(route('data.upload'))
            ->actingAs($user)
            ->post(route('data.upload.store'), [
                'target' => 'input',
                'file' => $upload,
            ]);

        $response->assertRedirect(route('data.upload'));
        $response->assertSessionHasErrors(['file']);

        $this->assertDatabaseCount('input', 0);

        @unlink($path);
    }
}
