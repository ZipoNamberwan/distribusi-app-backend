<?php

namespace Tests\Feature;

use App\Jobs\InputJob;
use App\Models\Input;
use App\Models\SyncStatus;
use App\Services\GoogleSheetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class InputJobTest extends TestCase
{
    use RefreshDatabase;

    private function seedMonthAndYear(): array
    {
        $yearId = DB::table('years')->insertGetId(['code' => '2024', 'name' => 'Year 2024']);
        $monthId = DB::table('months')->insertGetId(['code' => '01', 'name' => 'January']);

        return ['yearId' => $yearId, 'monthId' => $monthId];
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_constructor_marks_status_loading_immediately(): void
    {
        $lookup = $this->seedMonthAndYear();
        $user = \App\Models\User::factory()->create();

        $status = SyncStatus::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'filename' => 'example.xlsx',
            'status' => 'start',
            'month_id' => $lookup['monthId'],
            'year_id' => $lookup['yearId'],
        ]);

        new InputJob($status);

        $status->refresh();
        $this->assertSame('loading', $status->status);
        $this->assertSame('Sync process started', $status->system_message);
        $this->assertSame('Sync process started', $status->user_message);
    }

    public function test_handle_marks_failed_when_filename_missing(): void
    {
        $lookup = $this->seedMonthAndYear();
        $user = \App\Models\User::factory()->create();

        $status = SyncStatus::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'filename' => '',
            'status' => 'start',
            'month_id' => $lookup['monthId'],
            'year_id' => $lookup['yearId'],
        ]);

        $job = new InputJob($status);
        $mockService = Mockery::mock(GoogleSheetService::class);

        try {
            $job->handle($mockService);
            $this->fail('Expected exception was not thrown');
        } catch (\Throwable) {
            // expected
        }

        $status->refresh();
        $this->assertSame('failed', $status->status);
        $this->assertStringContainsString('Missing filename', (string) $status->system_message);
        $this->assertSame('File configuration missing', $status->user_message);
    }

    public function test_handle_marks_failed_for_unsupported_extension(): void
    {
        $lookup = $this->seedMonthAndYear();
        $user = \App\Models\User::factory()->create();

        $status = SyncStatus::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'filename' => 'bad.txt',
            'status' => 'start',
            'month_id' => $lookup['monthId'],
            'year_id' => $lookup['yearId'],
        ]);

        $job = new InputJob($status);
        $mockService = Mockery::mock(GoogleSheetService::class);

        try {
            $job->handle($mockService);
            $this->fail('Expected exception was not thrown');
        } catch (\Throwable) {
            // expected
        }

        $status->refresh();
        $this->assertSame('failed', $status->status);
        $this->assertStringContainsString('.csv or .xlsx', (string) $status->system_message);
        $this->assertSame('Uploaded file must be .csv or .xlsx', $status->user_message);
    }
}
