<?php

namespace Tests\Feature;

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DataPageTest extends TestCase
{
    public function test_it_renders_data_page_with_dummy_rows(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('data.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('data/Index')
                ->where('filters.data_type', null)
                ->has('rows', 4)
                ->has('dataTypeOptions', 2)
            );
    }

    public function test_it_filters_by_data_type(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('data.index', ['data_type' => 'luas']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('data/Index')
                ->where('filters.data_type', 'luas')
                ->has('rows', 2)
            );
    }
}
