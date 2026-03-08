<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class IndicatorValuesDataResponseTest extends TestCase
{
    use RefreshDatabase;

    private function seedData(): array
    {
        $yearId = DB::table('years')->insertGetId(['code' => '2024', 'name' => 'Year 2024']);
        $monthId = DB::table('months')->insertGetId(['code' => '01', 'name' => 'January']);

        $regencyId = DB::table('regencies')->insertGetId([
            'short_code' => 'KAB01',
            'long_code'  => '32.01',
            'name'       => 'Regency One',
        ]);

        $tpkId = DB::table('indicators')->insertGetId(['name' => 'TPK', 'short_name' => 'TPK', 'code' => 'TPK', 'scale_factor' => 100]);
        $bintangId = DB::table('categories')->insertGetId(['name' => 'Bintang', 'short_name' => 'B', 'code' => '1']);
        $nonBintangId = DB::table('categories')->insertGetId(['name' => 'Non Bintang', 'short_name' => 'NB', 'code' => '2']);

        // TPK Bintang: numerator=60, denominator=100
        DB::table('indicator_values')->insert([
            'id'           => (string) Str::uuid(),
            'regency_id'   => $regencyId,
            'month_id'     => $monthId,
            'year_id'      => $yearId,
            'indicator_id' => $tpkId,
            'category_id'  => $bintangId,
            'numerator'    => 60,
            'denominator'  => 100,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        // TPK Non Bintang: numerator=50, denominator=80
        DB::table('indicator_values')->insert([
            'id'           => (string) Str::uuid(),
            'regency_id'   => $regencyId,
            'month_id'     => $monthId,
            'year_id'      => $yearId,
            'indicator_id' => $tpkId,
            'category_id'  => $nonBintangId,
            'numerator'    => 50,
            'denominator'  => 80,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        return compact('yearId', 'monthId', 'regencyId', 'tpkId', 'bintangId', 'nonBintangId');
    }

    public function test_it_returns_nested_response_structure(): void
    {
        $user = User::factory()->create();
        $data = $this->seedData();

        $response = $this->actingAs($user)
            ->getJson(route('indicator.data.index', ['month' => $data['monthId'], 'year' => $data['yearId']]));

        $response->assertOk();

        $response->assertJsonStructure([
            'total',
            'data' => [
                '*' => [
                    'regency' => ['id', 'name', 'long_code'],
                    'values',
                ],
            ],
        ]);

        $body = $response->json();
        $this->assertSame(1, $body['total']);
        $this->assertCount(1, $body['data']);

        $regency = $body['data'][0]['regency'];
        $this->assertSame($data['regencyId'], $regency['id']);
        $this->assertSame('Regency One', $regency['name']);
        $this->assertSame('32.01', $regency['long_code']);

        $values = $body['data'][0]['values'];

        $bintangKey = "{$data['tpkId']}_{$data['bintangId']}";
        $this->assertArrayHasKey($bintangKey, $values);
        $this->assertSame(60, $values[$bintangKey]['num']);
        $this->assertSame(100, $values[$bintangKey]['den']);

        $nonBintangKey = "{$data['tpkId']}_{$data['nonBintangId']}";
        $this->assertArrayHasKey($nonBintangKey, $values);
        $this->assertSame(50, $values[$nonBintangKey]['num']);
        $this->assertSame(80, $values[$nonBintangKey]['den']);
    }

    public function test_it_filters_by_month_and_year(): void
    {
        $user = User::factory()->create();
        $data = $this->seedData();

        $otherYearId = DB::table('years')->insertGetId(['code' => '2023', 'name' => 'Year 2023']);
        $otherMonthId = DB::table('months')->insertGetId(['code' => '02', 'name' => 'February']);

        $response = $this->actingAs($user)
            ->getJson(route('indicator.data.index', ['month' => $otherMonthId, 'year' => $otherYearId]));

        $response->assertOk();
        $this->assertSame(0, $response->json('total'));
        $this->assertCount(0, $response->json('data'));
    }

    public function test_it_handles_null_denominator_as_null_value(): void
    {
        $user = User::factory()->create();

        $yearId = DB::table('years')->insertGetId(['code' => '2024', 'name' => 'Year 2024']);
        $monthId = DB::table('months')->insertGetId(['code' => '01', 'name' => 'January']);
        $regencyId = DB::table('regencies')->insertGetId(['short_code' => 'KAB02', 'long_code' => '32.02', 'name' => 'Regency Two']);
        $tpkId = DB::table('indicators')->insertGetId(['name' => 'TPK', 'short_name' => 'TPK', 'code' => 'TPK', 'scale_factor' => 100]);
        $bintangId = DB::table('categories')->insertGetId(['name' => 'Bintang', 'short_name' => 'B', 'code' => '1']);

        DB::table('indicator_values')->insert([
            'id'           => (string) Str::uuid(),
            'regency_id'   => $regencyId,
            'month_id'     => $monthId,
            'year_id'      => $yearId,
            'indicator_id' => $tpkId,
            'category_id'  => $bintangId,
            'numerator'    => null,
            'denominator'  => null,
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        $response = $this->actingAs($user)
            ->getJson(route('indicator.data.index', ['month' => $monthId, 'year' => $yearId]));

        $response->assertOk();
        $key = "{$tpkId}_{$bintangId}";
        $values = $response->json('data.0.values');
        $this->assertArrayHasKey($key, $values);
        $this->assertNull($values[$key]['num']);
        $this->assertNull($values[$key]['den']);
    }
}
