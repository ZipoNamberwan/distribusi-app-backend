<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_can_delete_a_user()
    {
        $user = User::factory()->create();

        $response = $this->post(route('user.delete'), [
            'id' => $user->id,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseMissing('users', [
            'id' => $user->id,
        ]);
    }
}
