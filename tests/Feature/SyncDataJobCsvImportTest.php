<?php

namespace Tests\Feature;

use App\Jobs\SyncDataJob;
use App\Models\Input;
use App\Models\SyncStatus;
use App\Models\User;
use App\Services\GoogleSheetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class SyncDataJobCsvImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_imports_csv_into_input_and_marks_status_success(): void
    {
        Storage::disk('local')->makeDirectory('uploads');

        $csv = implode("\n", [
            'tanggal_update,idunik,room,bed,status',
            '2024-01-01,ID001,10,20,ok',
            '2024-01-02,ID002,5,7,ok',
        ]);

        $filename = 'test_'.Str::random(6).'.csv';
        Storage::disk('local')->put('uploads/'.$filename, $csv);

        $user = User::factory()->create();

        $status = SyncStatus::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'filename' => $filename,
            'status' => 'start',
        ]);

        $job = new SyncDataJob($status);
        $job->handle(app(GoogleSheetService::class));

        $this->assertSame(2, Input::count());
        $this->assertNotNull(Input::where('idunik', 'ID001')->first());

        $status->refresh();
        $this->assertSame('success', $status->status);
        $this->assertStringContainsString('Imported 2 rows', (string) $status->message);
    }

    public function test_it_marks_status_failed_when_csv_missing(): void
    {
        $user = User::factory()->create();

        $status = SyncStatus::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'filename' => 'missing.csv',
            'status' => 'start',
        ]);

        $job = new SyncDataJob($status);

        try {
            $job->handle(app(GoogleSheetService::class));
            $this->fail('Expected exception was not thrown');
        } catch (\Throwable) {
            // expected
        }

        $status->refresh();
        $this->assertSame('failed', $status->status);
        $this->assertStringContainsString('CSV file not found', (string) $status->message);
    }

    public function test_it_imports_xlsx_into_input_and_marks_status_success(): void
    {
        Storage::disk('local')->makeDirectory('uploads');

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([
            ['tanggal_update', 'idunik', 'room', 'bed', 'status'],
            ['2024-01-01', 'ID001', 10, 20, 'ok'],
            ['2024-01-02', 'ID002', 5, 7, 'ok'],
        ]);

        $tmpFile = tempnam(sys_get_temp_dir(), 'xlsx_');
        if ($tmpFile === false) {
            $this->fail('Unable to create temporary XLSX file');
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($tmpFile);
        $spreadsheet->disconnectWorksheets();

        $filename = 'test_'.Str::random(6).'.xlsx';
        Storage::disk('local')->put('uploads/'.$filename, file_get_contents($tmpFile));
        @unlink($tmpFile);

        $user = User::factory()->create();

        $status = SyncStatus::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'filename' => $filename,
            'status' => 'start',
        ]);

        $job = new SyncDataJob($status);
        $job->handle(app(GoogleSheetService::class));

        $this->assertSame(2, Input::count());
        $this->assertNotNull(Input::where('idunik', 'ID001')->first());

        $status->refresh();
        $this->assertSame('success', $status->status);
        $this->assertStringContainsString('Imported 2 rows', (string) $status->message);
    }

    public function test_it_marks_status_failed_when_xlsx_missing(): void
    {
        $user = User::factory()->create();

        $status = SyncStatus::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'filename' => 'missing.xlsx',
            'status' => 'start',
        ]);

        $job = new SyncDataJob($status);

        try {
            $job->handle(app(GoogleSheetService::class));
            $this->fail('Expected exception was not thrown');
        } catch (\Throwable) {
            // expected
        }

        $status->refresh();
        $this->assertSame('failed', $status->status);
        $this->assertStringContainsString('XLSX file not found', (string) $status->message);
    }
}
