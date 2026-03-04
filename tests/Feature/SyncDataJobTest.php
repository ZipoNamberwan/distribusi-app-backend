<?php

namespace Tests\Feature;

use App\Jobs\SyncDataJob;
use App\Models\Input;
use App\Models\SyncStatus;
use App\Models\Tabulation;
use App\Services\GoogleSheetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class SyncDataJobTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_sync_data_job_creates_sync_status_on_start(): void
    {
        config(['services.google_sheets.spreadsheet_id' => '1234567890']);

        $mockService = Mockery::mock(GoogleSheetService::class);
        $mockService->shouldReceive('read')
            ->with('1234567890', 'INPUT!A1:AE1000')
            ->andReturn([
                ['tanggal_update', 'tarikan_ke', 'idunik', 'kode_kab'],
                ['2024-01-01', '1', 'ID001', 'KAB001'],
            ]);

        $mockService->shouldReceive('read')
            ->with('1234567890', 'TABULASI!A1:AZ1000')
            ->andReturn([
                ['tanggal_update', 'tarikan_ke', 'idunik', 'mkts'],
                ['2024-01-01', '1', 'ID001', '100'],
            ]);

        $this->app->instance(GoogleSheetService::class, $mockService);

        $this->assertEquals(0, SyncStatus::count());

        $job = new SyncDataJob;
        $job->handle($mockService);

        $this->assertEquals(1, SyncStatus::count());
    }

    public function test_sync_data_job_successfully_syncs_input_sheet(): void
    {
        config(['services.google_sheets.spreadsheet_id' => '1234567890']);

        $mockService = Mockery::mock(GoogleSheetService::class);
        $mockService->shouldReceive('read')
            ->with('1234567890', 'INPUT!A1:AE1000')
            ->andReturn([
                ['tanggal_update', 'tarikan_ke', 'idunik', 'kode_kab', 'room', 'bed'],
                ['2024-01-01', '1', 'ID001', 'KAB001', '10', '20'],
                ['2024-01-02', '2', 'ID002', 'KAB002', '15', '25'],
            ]);

        $mockService->shouldReceive('read')
            ->with('1234567890', 'TABULASI!A1:AZ1000')
            ->andReturn([
                ['tanggal_update', 'tarikan_ke', 'idunik'],
                ['2024-01-01', '1', 'ID001'],
            ]);

        $this->app->instance(GoogleSheetService::class, $mockService);

        $job = new SyncDataJob;
        $job->handle($mockService);

        $this->assertEquals(2, Input::count());

        $input1 = Input::where('idunik', 'ID001')->first();
        $this->assertNotNull($input1);
        $this->assertEquals('KAB001', $input1->kode_kab);
        $this->assertEquals(10, $input1->room);
        $this->assertEquals(20, $input1->bed);
    }

    public function test_sync_data_job_successfully_syncs_tabulation_sheet(): void
    {
        config(['services.google_sheets.spreadsheet_id' => '1234567890']);

        $mockService = Mockery::mock(GoogleSheetService::class);
        $mockService->shouldReceive('read')
            ->with('1234567890', 'INPUT!A1:AE1000')
            ->andReturn([
                ['tanggal_update', 'tarikan_ke', 'idunik'],
                ['2024-01-01', '1', 'ID001'],
            ]);

        $mockService->shouldReceive('read')
            ->with('1234567890', 'TABULASI!A1:AZ1000')
            ->andReturn([
                ['tanggal_update', 'tarikan_ke', 'idunik', 'mkts', 'mktj', 'tpk'],
                ['2024-01-01', '1', 'ID001', '100', '200', '85.5'],
                ['2024-01-02', '2', 'ID002', '150', '250', '90.2'],
            ]);

        $this->app->instance(GoogleSheetService::class, $mockService);

        $job = new SyncDataJob;
        $job->handle($mockService);

        $this->assertEquals(2, Tabulation::count());

        $tab1 = Tabulation::where('idunik', 'ID001')->first();
        $this->assertNotNull($tab1);
        $this->assertEquals(100, $tab1->mkts);
        $this->assertEquals(200, $tab1->mktj);
        $this->assertEquals(85.5, $tab1->tpk);
    }

    public function test_sync_data_job_creates_success_status_on_completion(): void
    {
        config(['services.google_sheets.spreadsheet_id' => '1234567890']);

        $mockService = Mockery::mock(GoogleSheetService::class);
        $mockService->shouldReceive('read')
            ->with('1234567890', 'INPUT!A1:AE1000')
            ->andReturn([
                ['tanggal_update', 'tarikan_ke', 'idunik'],
                ['2024-01-01', '1', 'ID001'],
            ]);

        $mockService->shouldReceive('read')
            ->with('1234567890', 'TABULASI!A1:AZ1000')
            ->andReturn([
                ['tanggal_update', 'tarikan_ke', 'idunik'],
                ['2024-01-01', '1', 'ID001'],
            ]);

        $this->app->instance(GoogleSheetService::class, $mockService);

        $job = new SyncDataJob;
        $job->handle($mockService);

        $status = SyncStatus::latest()->first();
        $this->assertNotNull($status);
        $this->assertEquals('success', $status->status);
        $this->assertEquals('Data synced successfully', $status->message);
    }

    public function test_sync_data_job_creates_failed_status_on_error(): void
    {
        config(['services.google_sheets.spreadsheet_id' => '1234567890']);

        $mockService = Mockery::mock(GoogleSheetService::class);
        $mockService->shouldReceive('read')
            ->with('1234567890', 'INPUT!A1:AE1000')
            ->andThrow(new \Exception('Connection failed'));

        $this->app->instance(GoogleSheetService::class, $mockService);

        $job = new SyncDataJob;
        $job->handle($mockService);

        $status = SyncStatus::latest()->first();
        $this->assertNotNull($status);
        $this->assertEquals('failed', $status->status);
        $this->assertStringContainsString('Connection failed', $status->message);
    }

    public function test_sync_data_job_handles_empty_spreadsheet_id(): void
    {
        config(['services.google_sheets.spreadsheet_id' => '']);

        $mockService = Mockery::mock(GoogleSheetService::class);
        $mockService->shouldNotReceive('read');

        $this->app->instance(GoogleSheetService::class, $mockService);

        $job = new SyncDataJob;
        $job->handle($mockService);

        $status = SyncStatus::latest()->first();
        $this->assertNotNull($status);
        $this->assertEquals('failed', $status->status);
        $this->assertStringContainsString('Spreadsheet ID is not configured', $status->message);
    }

    public function test_sync_data_job_clears_existing_data_before_sync(): void
    {
        config(['services.google_sheets.spreadsheet_id' => '1234567890']);

        // Create existing data
        Input::create([
            'kode_kab' => 'OLD001',
            'idunik' => 'OLD_ID',
        ]);

        Tabulation::create([
            'idunik' => 'OLD_ID',
            'mkts' => 999,
        ]);

        $this->assertEquals(1, Input::count());
        $this->assertEquals(1, Tabulation::count());

        $mockService = Mockery::mock(GoogleSheetService::class);
        $mockService->shouldReceive('read')
            ->with('1234567890', 'INPUT!A1:AE1000')
            ->andReturn([
                ['tanggal_update', 'kode_kab', 'idunik'],
                ['2024-01-01', 'NEW001', 'NEW_ID'],
            ]);

        $mockService->shouldReceive('read')
            ->with('1234567890', 'TABULASI!A1:AZ1000')
            ->andReturn([
                ['tanggal_update', 'idunik', 'mkts'],
                ['2024-01-01', 'NEW_ID', '100'],
            ]);

        $this->app->instance(GoogleSheetService::class, $mockService);

        $job = new SyncDataJob;
        $job->handle($mockService);

        // Check old data is replaced
        $this->assertEquals(1, Input::count());
        $this->assertEquals(1, Tabulation::count());

        $this->assertNull(Input::where('idunik', 'OLD_ID')->first());
        $this->assertNotNull(Input::where('idunik', 'NEW_ID')->first());
    }
}
