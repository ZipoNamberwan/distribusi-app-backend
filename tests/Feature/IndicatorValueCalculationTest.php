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
            ['name' => 'TPK',   'short_name' => 'TPK',   'code' => 'TPK',   'scale_factor' => 100],
            ['name' => 'RLMTA', 'short_name' => 'RLMTA', 'code' => 'RLMTA', 'scale_factor' => 1],
            ['name' => 'RLMTN', 'short_name' => 'RLMTN', 'code' => 'RLMTN', 'scale_factor' => 1],
            ['name' => 'GPR',   'short_name' => 'GPR',   'code' => 'GPR',   'scale_factor' => 1],
            ['name' => 'TPTT',  'short_name' => 'TPTT',  'code' => 'TPTT',  'scale_factor' => 100],
        ]);

        DB::table('categories')->insert([
            ['name' => 'Bintang',     'short_name' => 'B',  'code' => '1'],
            ['name' => 'Non Bintang', 'short_name' => 'NB', 'code' => '2'],
            ['name' => 'Total',       'short_name' => 'T',  'code' => null],
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

        // 5 indicators × 2 categories (Bintang + Non Bintang) = 10 records
        $this->assertSame(10, IndicatorValue::count());

        $indicators = DB::table('indicators')->pluck('id', 'code');
        $categories = DB::table('categories')->pluck('id', 'code');

        $regencyId = $lookups['regencyId'];
        $yearId = $lookups['yearId'];
        $monthId = $lookups['monthId'];

        // TPK Bintang: mktj=6, mkts=10
        $tpkBintang = IndicatorValue::where('indicator_id', $indicators['TPK'])
            ->where('category_id', $categories['1'])
            ->where('regency_id', $regencyId)
            ->where('year_id', $yearId)
            ->where('month_id', $monthId)
            ->first();
        $this->assertNotNull($tpkBintang);
        $this->assertSame(6, $tpkBintang->numerator);
        $this->assertSame(10, $tpkBintang->denominator);

        // TPK Non Bintang: mktj=5, mkts=8
        $tpkNonBintang = IndicatorValue::where('indicator_id', $indicators['TPK'])
            ->where('category_id', $categories['2'])
            ->where('regency_id', $regencyId)
            ->where('year_id', $yearId)
            ->where('month_id', $monthId)
            ->first();
        $this->assertNotNull($tpkNonBintang);
        $this->assertSame(5, $tpkNonBintang->numerator);
        $this->assertSame(8, $tpkNonBintang->denominator);

        // RLMTA Bintang: mta=12, ta=10
        $rlmtaBintang = IndicatorValue::where('indicator_id', $indicators['RLMTA'])
            ->where('category_id', $categories['1'])
            ->where('regency_id', $regencyId)
            ->where('year_id', $yearId)
            ->where('month_id', $monthId)
            ->first();
        $this->assertNotNull($rlmtaBintang);
        $this->assertSame(12, $rlmtaBintang->numerator);
        $this->assertSame(10, $rlmtaBintang->denominator);

        // RLMTA Non Bintang: mta=7, ta=6
        $rlmtaNonBintang = IndicatorValue::where('indicator_id', $indicators['RLMTA'])
            ->where('category_id', $categories['2'])
            ->where('regency_id', $regencyId)
            ->where('year_id', $yearId)
            ->where('month_id', $monthId)
            ->first();
        $this->assertNotNull($rlmtaNonBintang);
        $this->assertSame(7, $rlmtaNonBintang->numerator);
        $this->assertSame(6, $rlmtaNonBintang->denominator);

        // RLMTN Bintang: mtnus=25, tnus=20
        $rlmtnBintang = IndicatorValue::where('indicator_id', $indicators['RLMTN'])
            ->where('category_id', $categories['1'])
            ->where('regency_id', $regencyId)
            ->where('year_id', $yearId)
            ->where('month_id', $monthId)
            ->first();
        $this->assertNotNull($rlmtnBintang);
        $this->assertSame(25, $rlmtnBintang->numerator);
        $this->assertSame(20, $rlmtnBintang->denominator);

        // RLMTN Non Bintang: mtnus=17, tnus=12
        $rlmtnNonBintang = IndicatorValue::where('indicator_id', $indicators['RLMTN'])
            ->where('category_id', $categories['2'])
            ->where('regency_id', $regencyId)
            ->where('year_id', $yearId)
            ->where('month_id', $monthId)
            ->first();
        $this->assertNotNull($rlmtnNonBintang);
        $this->assertSame(17, $rlmtnNonBintang->numerator);
        $this->assertSame(12, $rlmtnNonBintang->denominator);

        // GPR Bintang: mtgab=37, mktj=6
        $gprBintang = IndicatorValue::where('indicator_id', $indicators['GPR'])
            ->where('category_id', $categories['1'])
            ->where('regency_id', $regencyId)
            ->where('year_id', $yearId)
            ->where('month_id', $monthId)
            ->first();
        $this->assertNotNull($gprBintang);
        $this->assertSame(37, $gprBintang->numerator);
        $this->assertSame(6, $gprBintang->denominator);

        // GPR Non Bintang: mtgab=24, mktj=5
        $gprNonBintang = IndicatorValue::where('indicator_id', $indicators['GPR'])
            ->where('category_id', $categories['2'])
            ->where('regency_id', $regencyId)
            ->where('year_id', $yearId)
            ->where('month_id', $monthId)
            ->first();
        $this->assertNotNull($gprNonBintang);
        $this->assertSame(24, $gprNonBintang->numerator);
        $this->assertSame(5, $gprNonBintang->denominator);

        // TPTT Bintang: mtgab=37, bed=20
        $tpttBintang = IndicatorValue::where('indicator_id', $indicators['TPTT'])
            ->where('category_id', $categories['1'])
            ->where('regency_id', $regencyId)
            ->where('year_id', $yearId)
            ->where('month_id', $monthId)
            ->first();
        $this->assertNotNull($tpttBintang);
        $this->assertSame(37, $tpttBintang->numerator);
        $this->assertSame(20, $tpttBintang->denominator);

        // TPTT Non Bintang: mtgab=24, bed=16
        $tpttNonBintang = IndicatorValue::where('indicator_id', $indicators['TPTT'])
            ->where('category_id', $categories['2'])
            ->where('regency_id', $regencyId)
            ->where('year_id', $yearId)
            ->where('month_id', $monthId)
            ->first();
        $this->assertNotNull($tpttNonBintang);
        $this->assertSame(24, $tpttNonBintang->numerator);
        $this->assertSame(16, $tpttNonBintang->denominator);
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
        $this->assertNull($tpkBintang->numerator);
        $this->assertNull($tpkBintang->denominator);
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
