<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\GoogleSheetService;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DataPageTest extends TestCase
{
    public function test_it_renders_data_page_with_sheet_rows(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        config()->set('services.google_sheets.spreadsheet_id', 'test-spreadsheet');
        config()->set('services.google_sheets.range', 'INPUT!A1:AE5000');

        $this->mock(GoogleSheetService::class, function ($mock): void {
            $mock
                ->shouldReceive('read')
                ->once()
                ->andReturn([
                    ['id', 'code', 'name', 'value', 'data_type'],
                    ['1', '3201', 'Bogor', '10', 'populasi'],
                    ['2', '3202', 'Sukabumi', '20', 'populasi'],
                    ['3', '3203', 'Cianjur', '30', 'luas'],
                    ['4', '3204', 'Bandung', '40', 'luas'],
                ]);
        });

        $this->actingAs($user)
            ->get(route('data.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('data/Index')
                ->where('filters.data_type', null)
                ->has('headers', 5)
                ->has('rows', 4)
                ->has('dataTypeOptions', 2)
            );
    }

    public function test_it_filters_by_data_type(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        config()->set('services.google_sheets.spreadsheet_id', 'test-spreadsheet');
        config()->set('services.google_sheets.range', 'INPUT!A1:AE5000');

        $this->mock(GoogleSheetService::class, function ($mock): void {
            $mock
                ->shouldReceive('read')
                ->once()
                ->andReturn([
                    ['id', 'code', 'name', 'value', 'data_type'],
                    ['1', '3201', 'Bogor', '10', 'populasi'],
                    ['2', '3202', 'Sukabumi', '20', 'populasi'],
                    ['3', '3203', 'Cianjur', '30', 'luas'],
                    ['4', '3204', 'Bandung', '40', 'luas'],
                ]);
        });

        $this->actingAs($user)
            ->get(route('data.index', ['data_type' => 'luas']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('data/Index')
                ->where('filters.data_type', 'luas')
                ->has('rows', 2)
            );
    }

    public function test_it_can_fetch_sheet_json_endpoint(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        config()->set('services.google_sheets.spreadsheet_id', 'test-spreadsheet');
        config()->set('services.google_sheets.range', 'INPUT!A1:AE5000');

        $this->mock(GoogleSheetService::class, function ($mock): void {
            $mock
                ->shouldReceive('read')
                ->once()
                ->andReturn([
                    ['id', 'code'],
                    ['1', '3201'],
                ]);
        });

        $this->actingAs($user)
            ->get(route('data.sheet'))
            ->assertOk()
            ->assertJson([
                ['id', 'code'],
                ['1', '3201'],
            ]);
    }
}
