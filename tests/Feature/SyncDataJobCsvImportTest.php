<?php

namespace Tests\Feature;

use App\Jobs\SyncDataJob;
use App\Models\Input;
use App\Models\SyncStatus;
use App\Models\User;
use App\Services\GoogleSheetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class SyncDataJobCsvImportTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{yearId: int, monthId: int, regencyId: int, yearCode: string, monthCode: string, regencyShortCode: string}
     */
    private function seedForeignKeyLookups(): array
    {
        $yearCode = '2024';
        $monthCode = '01';
        $regencyShortCode = 'KAB01';

        $yearId = DB::table('years')->insertGetId([
            'code' => $yearCode,
            'name' => 'Year 2024',
        ]);

        $monthId = DB::table('months')->insertGetId([
            'code' => $monthCode,
            'name' => 'January',
        ]);

        $regencyId = DB::table('regencies')->insertGetId([
            'short_code' => $regencyShortCode,
            'long_code' => 'LONG-'.$regencyShortCode,
            'name' => 'Regency '.$regencyShortCode,
        ]);

        return [
            'yearId' => $yearId,
            'monthId' => $monthId,
            'regencyId' => $regencyId,
            'yearCode' => $yearCode,
            'monthCode' => $monthCode,
            'regencyShortCode' => $regencyShortCode,
        ];
    }

    public function test_it_imports_csv_into_input_and_marks_status_success(): void
    {
        $lookups = $this->seedForeignKeyLookups();

        Storage::disk('local')->makeDirectory('uploads');

        $csv = implode("\n", [
            'tanggal_update,idunik,kode_kab,room,bed,status',
            '2024-01-01,ID001,'.$lookups['regencyShortCode'].',10,20,ok',
            '2024-01-02,ID002,'.$lookups['regencyShortCode'].',5,7,ok',
        ]);

        $filename = 'test_'.Str::random(6).'.csv';
        Storage::disk('local')->put('uploads/'.$filename, $csv);

        $user = User::factory()->create();

        $status = SyncStatus::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'filename' => $filename,
            'status' => 'start',
            'month_id' => $lookups['monthId'],
            'year_id' => $lookups['yearId'],
        ]);

        $job = new SyncDataJob($status);
        $job->handle(app(GoogleSheetService::class));

        $this->assertSame(2, Input::count());

        $input1 = Input::where('idunik', 'ID001')->first();
        $this->assertNotNull($input1);
        $this->assertSame($lookups['yearId'], (int) $input1->tahun);
        $this->assertSame($lookups['monthId'], (int) $input1->bulan);
        $this->assertSame($lookups['regencyId'], (int) $input1->kode_kab);
        $this->assertSame((string) $status->id, (string) $input1->sync_status_id);
        $this->assertSame($user->id, (string) $input1->user_id);

        $status->refresh();
        $this->assertSame('success', $status->status);
        $this->assertStringContainsString('Imported 2 rows', (string) $status->system_message);
    }

    public function test_it_marks_status_failed_when_fk_code_is_unknown(): void
    {
        $lookups = $this->seedForeignKeyLookups();

        Storage::disk('local')->makeDirectory('uploads');

        $csv = implode("\n", [
            'tanggal_update,idunik,kode_kab,room,bed,status',
            '2024-01-01,ID001,UNKNOWN,10,20,ok',
        ]);

        $filename = 'test_'.Str::random(6).'.csv';
        Storage::disk('local')->put('uploads/'.$filename, $csv);

        $user = User::factory()->create();

        $status = SyncStatus::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'filename' => $filename,
            'status' => 'start',
            'month_id' => $lookups['monthId'],
            'year_id' => $lookups['yearId'],
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
        $this->assertStringContainsString('Unknown', (string) $status->system_message);
        $this->assertStringContainsString('references not found', (string) $status->user_message);
    }

    public function test_it_marks_status_failed_when_csv_missing(): void
    {
        $lookups = $this->seedForeignKeyLookups();

        $user = User::factory()->create();

        $status = SyncStatus::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'filename' => 'missing.csv',
            'status' => 'start',
            'month_id' => $lookups['monthId'],
            'year_id' => $lookups['yearId'],
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
        $this->assertStringContainsString('CSV file not found', (string) $status->system_message);
    }

    public function test_it_imports_xlsx_into_input_and_marks_status_success(): void
    {
        $lookups = $this->seedForeignKeyLookups();

        Storage::disk('local')->makeDirectory('uploads');

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray([
            ['tanggal_update', 'idunik', 'kode_kab', 'room', 'bed', 'status'],
            ['2024-01-01', 'ID001', $lookups['regencyShortCode'], 10, 20, 'ok'],
            ['2024-01-02', 'ID002', $lookups['regencyShortCode'], 5, 7, 'ok'],
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
            'month_id' => $lookups['monthId'],
            'year_id' => $lookups['yearId'],
        ]);

        $job = new SyncDataJob($status);
        $job->handle(app(GoogleSheetService::class));

        $this->assertSame(2, Input::count());

        $input1 = Input::where('idunik', 'ID001')->first();
        $this->assertNotNull($input1);
        $this->assertSame($lookups['yearId'], (int) $input1->tahun);
        $this->assertSame($lookups['monthId'], (int) $input1->bulan);
        $this->assertSame($lookups['regencyId'], (int) $input1->kode_kab);
        $this->assertSame((string) $status->id, (string) $input1->sync_status_id);
        $this->assertSame($user->id, (string) $input1->user_id);

        $status->refresh();
        $this->assertSame('success', $status->status);
        $this->assertStringContainsString('Imported 2 rows', (string) $status->system_message);
    }

    public function test_it_marks_status_failed_when_xlsx_missing(): void
    {
        $lookups = $this->seedForeignKeyLookups();

        $user = User::factory()->create();

        $status = SyncStatus::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'filename' => 'missing.xlsx',
            'status' => 'start',
            'month_id' => $lookups['monthId'],
            'year_id' => $lookups['yearId'],
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
        $this->assertStringContainsString('XLSX file not found', (string) $status->system_message);
    }

    public function test_it_deletes_stale_input_rows_for_same_month_and_year_after_import(): void
    {
        $lookups = $this->seedForeignKeyLookups();

        Storage::disk('local')->makeDirectory('uploads');

        $user = User::factory()->create();

        // Create a previous SyncStatus and a stale Input row associated with it
        $oldStatus = SyncStatus::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'filename' => 'old.csv',
            'status' => 'success',
            'month_id' => $lookups['monthId'],
            'year_id' => $lookups['yearId'],
        ]);

        DB::table('input')->insert([
            'id' => (string) Str::uuid(),
            'tahun' => $lookups['yearId'],
            'bulan' => $lookups['monthId'],
            'kode_kab' => $lookups['regencyId'],
            'user_id' => $user->id,
            'sync_status_id' => (string) $oldStatus->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame(1, Input::count());

        $csv = implode("\n", [
            'tanggal_update,idunik,kode_kab,room,bed,status',
            '2024-01-01,ID_NEW,'.$lookups['regencyShortCode'].',10,20,ok',
        ]);

        $filename = 'test_'.Str::random(6).'.csv';
        Storage::disk('local')->put('uploads/'.$filename, $csv);

        $newStatus = SyncStatus::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'filename' => $filename,
            'status' => 'start',
            'month_id' => $lookups['monthId'],
            'year_id' => $lookups['yearId'],
        ]);

        $job = new SyncDataJob($newStatus);
        $job->handle(app(GoogleSheetService::class));

        // Only the newly imported row should exist; the stale row is deleted
        $this->assertSame(1, Input::count());
        $this->assertSame(1, Input::where('sync_status_id', (string) $newStatus->id)->count());
        $this->assertSame(0, Input::where('sync_status_id', (string) $oldStatus->id)->count());
    }
}
