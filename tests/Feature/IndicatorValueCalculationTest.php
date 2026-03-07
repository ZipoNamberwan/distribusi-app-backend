<?php

namespace Tests\Feature;

use App\Jobs\SyncDataJob;
use App\Models\IndicatorValue;
use App\Models\SyncStatus;
use App\Models\User;
use App\Services\GoogleSheetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class IndicatorValueCalculationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{yearId: int, monthId: int, regencyId: int, regencyShortCode: string}
     */
    private function seedLookups(): array
    {
        $yearId = DB::table('years')->insertGetId(['code' => '2024', 'name' => 'Year 2024']);
        $monthId = DB::table('months')->insertGetId(['code' => '01', 'name' => 'January']);
        $regencyId = DB::table('regencies')->insertGetId([
            'short_code' => 'KAB01',
            'long_code' => 'LONG-KAB01',
            'name' => 'Regency KAB01',
        ]);

        return [
            'yearId' => $yearId,
            'monthId' => $monthId,
            'regencyId' => $regencyId,
            'regencyShortCode' => 'KAB01',
        ];
    }

    private function seedIndicatorsAndCategories(): void
    {
        DB::table('indicators')->insert([
            ['name' => 'TPK', 'code' => 'TPK'],
            ['name' => 'RLMTA', 'code' => 'RLMTA'],
            ['name' => 'RLMTN', 'code' => 'RLMTN'],
            ['name' => 'GPR', 'code' => 'GPR'],
            ['name' => 'TPTT', 'code' => 'TPTT'],
        ]);

        DB::table('categories')->insert([
            ['name' => 'Bintang', 'code' => '1'],
            ['name' => 'Non Bintang', 'code' => '2'],
            ['name' => 'Total', 'code' => null],
        ]);
    }

    public function test_it_calculates_indicator_values_after_import(): void
    {
        $lookups = $this->seedLookups();
        $this->seedIndicatorsAndCategories();

        Storage::disk('local')->makeDirectory('uploads');

        // Row 1 (Bintang): mktj=6, mkts=10, mta=12, ta=10, mtnus=25, tnus=20, mtgab=37, bed=20
        // Row 2 (Non Bintang): mktj=5, mkts=8, mta=7, ta=6, mtnus=17, tnus=12, mtgab=24, bed=16
        $csv = implode("\n", [
            'kode_kab,jenis_akomodasi,room,bed,room_yesterday,room_in,room_out,wna_yesterday,wna_in,wna_out,wni_yesterday,wni_in,wni_out,nama_komersial,status_kunjungan',
            $lookups['regencyShortCode'].',1,10,20,5,3,2,5,10,3,10,20,5,Hotel Bintang,1',
            $lookups['regencyShortCode'].',2,8,16,4,2,1,3,6,2,8,12,3,Hotel Non Bintang,1',
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

        $status->refresh();
        $this->assertSame('success', $status->status);

        // 5 indicators × 3 categories = 15 records
        $this->assertSame(15, IndicatorValue::count());

        $indicators = DB::table('indicators')->pluck('id', 'code');
        $categories = DB::table('categories')->pluck('id', 'code');
        $totalCategoryId = DB::table('categories')->whereNull('code')->value('id');

        $regencyId = $lookups['regencyId'];
        $yearId = $lookups['yearId'];
        $monthId = $lookups['monthId'];

        // TPK Bintang = 100 * 6 / 10 = 60.00 (jenis=1)
        $tpkBintang = IndicatorValue::where('indicator_id', $indicators['TPK'])
            ->where('category_id', $categories['1'])
            ->where('regency_id', $regencyId)
            ->where('year_id', $yearId)
            ->where('month_id', $monthId)
            ->first();
        $this->assertNotNull($tpkBintang);
        $this->assertEqualsWithDelta(60.00, (float) $tpkBintang->value, 0.01);

        // TPK Non Bintang = 100 * 5 / 8 = 62.50 (jenis=2)
        $tpkNonBintang = IndicatorValue::where('indicator_id', $indicators['TPK'])
            ->where('category_id', $categories['2'])
            ->where('regency_id', $regencyId)
            ->where('year_id', $yearId)
            ->where('month_id', $monthId)
            ->first();
        $this->assertNotNull($tpkNonBintang);
        $this->assertEqualsWithDelta(62.50, (float) $tpkNonBintang->value, 0.01);

        // RLMTA Bintang = 12 / 10 = 1.20
        $rlmtaBintang = IndicatorValue::where('indicator_id', $indicators['RLMTA'])
            ->where('category_id', $categories['1'])
            ->where('regency_id', $regencyId)
            ->where('year_id', $yearId)
            ->where('month_id', $monthId)
            ->first();
        $this->assertNotNull($rlmtaBintang);
        $this->assertEqualsWithDelta(1.20, (float) $rlmtaBintang->value, 0.01);

        // RLMTA Non Bintang = 7 / 6 ≈ 1.17
        $rlmtaNonBintang = IndicatorValue::where('indicator_id', $indicators['RLMTA'])
            ->where('category_id', $categories['2'])
            ->where('regency_id', $regencyId)
            ->where('year_id', $yearId)
            ->where('month_id', $monthId)
            ->first();
        $this->assertNotNull($rlmtaNonBintang);
        $this->assertEqualsWithDelta(1.17, (float) $rlmtaNonBintang->value, 0.01);

        // RLMTN Bintang = 25 / 20 = 1.25
        $rlmtnBintang = IndicatorValue::where('indicator_id', $indicators['RLMTN'])
            ->where('category_id', $categories['1'])
            ->where('regency_id', $regencyId)
            ->where('year_id', $yearId)
            ->where('month_id', $monthId)
            ->first();
        $this->assertNotNull($rlmtnBintang);
        $this->assertEqualsWithDelta(1.25, (float) $rlmtnBintang->value, 0.01);

        // RLMTN Non Bintang = 17 / 12 ≈ 1.42
        $rlmtnNonBintang = IndicatorValue::where('indicator_id', $indicators['RLMTN'])
            ->where('category_id', $categories['2'])
            ->where('regency_id', $regencyId)
            ->where('year_id', $yearId)
            ->where('month_id', $monthId)
            ->first();
        $this->assertNotNull($rlmtnNonBintang);
        $this->assertEqualsWithDelta(1.42, (float) $rlmtnNonBintang->value, 0.01);

        // GPR Bintang = 37 / 6 ≈ 6.17
        $gprBintang = IndicatorValue::where('indicator_id', $indicators['GPR'])
            ->where('category_id', $categories['1'])
            ->where('regency_id', $regencyId)
            ->where('year_id', $yearId)
            ->where('month_id', $monthId)
            ->first();
        $this->assertNotNull($gprBintang);
        $this->assertEqualsWithDelta(6.17, (float) $gprBintang->value, 0.01);

        // GPR Non Bintang = 24 / 5 = 4.80
        $gprNonBintang = IndicatorValue::where('indicator_id', $indicators['GPR'])
            ->where('category_id', $categories['2'])
            ->where('regency_id', $regencyId)
            ->where('year_id', $yearId)
            ->where('month_id', $monthId)
            ->first();
        $this->assertNotNull($gprNonBintang);
        $this->assertEqualsWithDelta(4.80, (float) $gprNonBintang->value, 0.01);

        // TPTT Bintang = 100 * 37 / 20 = 185.00
        $tpttBintang = IndicatorValue::where('indicator_id', $indicators['TPTT'])
            ->where('category_id', $categories['1'])
            ->where('regency_id', $regencyId)
            ->where('year_id', $yearId)
            ->where('month_id', $monthId)
            ->first();
        $this->assertNotNull($tpttBintang);
        $this->assertEqualsWithDelta(185.00, (float) $tpttBintang->value, 0.01);

        // TPTT Non Bintang = 100 * 24 / 16 = 150.00
        $tpttNonBintang = IndicatorValue::where('indicator_id', $indicators['TPTT'])
            ->where('category_id', $categories['2'])
            ->where('regency_id', $regencyId)
            ->where('year_id', $yearId)
            ->where('month_id', $monthId)
            ->first();
        $this->assertNotNull($tpttNonBintang);
        $this->assertEqualsWithDelta(150.00, (float) $tpttNonBintang->value, 0.01);

        // Total values (all jenis combined): sum_mktj=11, sum_mkts=18, sum_mta=19, sum_ta=16,
        //   sum_mtnus=42, sum_tnus=32, sum_mtgab=61, sum_bed=36

        // TPK Total = 100 * 11 / 18 ≈ 61.11
        $tpkTotal = IndicatorValue::where('indicator_id', $indicators['TPK'])
            ->where('category_id', $totalCategoryId)
            ->where('regency_id', $regencyId)
            ->where('year_id', $yearId)
            ->where('month_id', $monthId)
            ->first();
        $this->assertNotNull($tpkTotal);
        $this->assertEqualsWithDelta(61.11, (float) $tpkTotal->value, 0.01);

        // RLMTA Total = 19 / 16 ≈ 1.19
        $rlmtaTotal = IndicatorValue::where('indicator_id', $indicators['RLMTA'])
            ->where('category_id', $totalCategoryId)
            ->where('regency_id', $regencyId)
            ->where('year_id', $yearId)
            ->where('month_id', $monthId)
            ->first();
        $this->assertNotNull($rlmtaTotal);
        $this->assertEqualsWithDelta(1.19, (float) $rlmtaTotal->value, 0.01);

        // RLMTN Total = 42 / 32 ≈ 1.31
        $rlmtnTotal = IndicatorValue::where('indicator_id', $indicators['RLMTN'])
            ->where('category_id', $totalCategoryId)
            ->where('regency_id', $regencyId)
            ->where('year_id', $yearId)
            ->where('month_id', $monthId)
            ->first();
        $this->assertNotNull($rlmtnTotal);
        $this->assertEqualsWithDelta(1.31, (float) $rlmtnTotal->value, 0.01);

        // GPR Total = 61 / 11 ≈ 5.55
        $gprTotal = IndicatorValue::where('indicator_id', $indicators['GPR'])
            ->where('category_id', $totalCategoryId)
            ->where('regency_id', $regencyId)
            ->where('year_id', $yearId)
            ->where('month_id', $monthId)
            ->first();
        $this->assertNotNull($gprTotal);
        $this->assertEqualsWithDelta(5.55, (float) $gprTotal->value, 0.01);

        // TPTT Total = 100 * 61 / 36 ≈ 169.44
        $tpttTotal = IndicatorValue::where('indicator_id', $indicators['TPTT'])
            ->where('category_id', $totalCategoryId)
            ->where('regency_id', $regencyId)
            ->where('year_id', $yearId)
            ->where('month_id', $monthId)
            ->first();
        $this->assertNotNull($tpttTotal);
        $this->assertEqualsWithDelta(169.44, (float) $tpttTotal->value, 0.01);
    }

    public function test_it_returns_null_for_indicator_when_denominator_is_zero(): void
    {
        $lookups = $this->seedLookups();
        $this->seedIndicatorsAndCategories();

        Storage::disk('local')->makeDirectory('uploads');

        // Row with jenis=1 but all room values zero → mkts=0, mktj=0 → TPK=null, GPR=null
        // wna_in=0, wni_in=0 → ta=0, tnus=0 → RLMTA=null, RLMTN=null; bed=0 → TPTT=null
        $csv = implode("\n", [
            'kode_kab,jenis_akomodasi,room,bed,room_yesterday,room_in,room_out,wna_yesterday,wna_in,wna_out,wni_yesterday,wni_in,wni_out,nama_komersial,status_kunjungan',
            $lookups['regencyShortCode'].',1,0,0,0,0,0,0,0,0,0,0,0,Hotel Bintang,1',
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

        $status->refresh();
        $this->assertSame('success', $status->status);

        $indicators = DB::table('indicators')->pluck('id', 'code');
        $categories = DB::table('categories')->pluck('id', 'code');

        $tpkBintang = IndicatorValue::where('indicator_id', $indicators['TPK'])
            ->where('category_id', $categories['1'])
            ->first();
        $this->assertNotNull($tpkBintang);
        $this->assertNull($tpkBintang->value);
    }

    public function test_it_does_not_fail_when_indicators_not_seeded_and_no_jenis_data(): void
    {
        $lookups = $this->seedLookups();
        // No indicators/categories seeded intentionally

        Storage::disk('local')->makeDirectory('uploads');

        // CSV with no jenis_akomodasi column → aggregate will be empty → calculation skips
        $csv = implode("\n", [
            'kode_kab,room,bed,status',
            $lookups['regencyShortCode'].',10,20,ok',
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

        $status->refresh();
        $this->assertSame('success', $status->status);
        $this->assertSame(0, IndicatorValue::count());
    }

    public function test_it_marks_failed_when_indicators_missing_but_jenis_data_present(): void
    {
        $lookups = $this->seedLookups();
        // No indicators/categories seeded — calculation should fail gracefully

        Storage::disk('local')->makeDirectory('uploads');

        $csv = implode("\n", [
            'kode_kab,jenis_akomodasi,room,bed,nama_komersial,status_kunjungan',
            $lookups['regencyShortCode'].',1,10,20,Hotel,1',
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
        $this->assertSame('Indicator calculation failed', $status->user_message);
    }
}
