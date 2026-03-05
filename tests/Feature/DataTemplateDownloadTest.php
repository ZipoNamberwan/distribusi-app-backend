<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DataTemplateDownloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_downloads_input_template(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('template/template_vhts.xlsx', 'dummy-template');

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('data.template'))
            ->assertOk()
            ->assertDownload('template_vhts.xlsx');
    }

    public function test_it_returns_404_when_template_is_missing(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('data.template'))
            ->assertNotFound();
    }
}
