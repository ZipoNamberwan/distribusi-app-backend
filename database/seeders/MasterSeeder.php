<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Error;
use App\Models\Indicator;
use App\Models\Month;
use App\Models\Regency;
use App\Models\Year;
use Illuminate\Database\Seeder;

class MasterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $months = [
            ['name' => 'Januari', 'code' => '01', 'day' => 31],
            ['name' => 'Februari', 'code' => '02', 'day' => 28],
            ['name' => 'Maret', 'code' => '03', 'day' => 31],
            ['name' => 'April', 'code' => '04', 'day' => 30],
            ['name' => 'Mei', 'code' => '05', 'day' => 31],
            ['name' => 'Juni', 'code' => '06', 'day' => 30],
            ['name' => 'Juli', 'code' => '07', 'day' => 31],
            ['name' => 'Agustus', 'code' => '08', 'day' => 31],
            ['name' => 'September', 'code' => '09', 'day' => 30],
            ['name' => 'Oktober', 'code' => '10', 'day' => 31],
            ['name' => 'November', 'code' => '11', 'day' => 30],
            ['name' => 'Desember', 'code' => '12', 'day' => 31],
        ];
        foreach ($months as $month) {
            Month::create($month);
        }

        // now do it for years from 2024-2028
        for ($year = 2024; $year <= 2028; $year++) {
            Year::create([
                'name' => (string) $year,
                'code' => (string) $year,
            ]);
        }

        Regency::create(['short_code' => '01', 'long_code' => '3501', 'id' => '3501', 'name' => 'PACITAN']);
        Regency::create(['short_code' => '02', 'long_code' => '3502', 'id' => '3502', 'name' => 'PONOROGO']);
        Regency::create(['short_code' => '03', 'long_code' => '3503', 'id' => '3503', 'name' => 'TRENGGALEK']);
        Regency::create(['short_code' => '04', 'long_code' => '3504', 'id' => '3504', 'name' => 'TULUNGAGUNG']);
        Regency::create(['short_code' => '05', 'long_code' => '3505', 'id' => '3505', 'name' => 'BLITAR']);
        Regency::create(['short_code' => '06', 'long_code' => '3506', 'id' => '3506', 'name' => 'KEDIRI']);
        Regency::create(['short_code' => '07', 'long_code' => '3507', 'id' => '3507', 'name' => 'MALANG']);
        Regency::create(['short_code' => '08', 'long_code' => '3508', 'id' => '3508', 'name' => 'LUMAJANG']);
        Regency::create(['short_code' => '09', 'long_code' => '3509', 'id' => '3509', 'name' => 'JEMBER']);
        Regency::create(['short_code' => '10', 'long_code' => '3510', 'id' => '3510', 'name' => 'BANYUWANGI']);
        Regency::create(['short_code' => '11', 'long_code' => '3511', 'id' => '3511', 'name' => 'BONDOWOSO']);
        Regency::create(['short_code' => '12', 'long_code' => '3512', 'id' => '3512', 'name' => 'SITUBONDO']);
        Regency::create(['short_code' => '13', 'long_code' => '3513', 'id' => '3513', 'name' => 'PROBOLINGGO']);
        Regency::create(['short_code' => '14', 'long_code' => '3514', 'id' => '3514', 'name' => 'PASURUAN']);
        Regency::create(['short_code' => '15', 'long_code' => '3515', 'id' => '3515', 'name' => 'SIDOARJO']);
        Regency::create(['short_code' => '16', 'long_code' => '3516', 'id' => '3516', 'name' => 'MOJOKERTO']);
        Regency::create(['short_code' => '17', 'long_code' => '3517', 'id' => '3517', 'name' => 'JOMBANG']);
        Regency::create(['short_code' => '18', 'long_code' => '3518', 'id' => '3518', 'name' => 'NGANJUK']);
        Regency::create(['short_code' => '19', 'long_code' => '3519', 'id' => '3519', 'name' => 'MADIUN']);
        Regency::create(['short_code' => '20', 'long_code' => '3520', 'id' => '3520', 'name' => 'MAGETAN']);
        Regency::create(['short_code' => '21', 'long_code' => '3521', 'id' => '3521', 'name' => 'NGAWI']);
        Regency::create(['short_code' => '22', 'long_code' => '3522', 'id' => '3522', 'name' => 'BOJONEGORO']);
        Regency::create(['short_code' => '23', 'long_code' => '3523', 'id' => '3523', 'name' => 'TUBAN']);
        Regency::create(['short_code' => '24', 'long_code' => '3524', 'id' => '3524', 'name' => 'LAMONGAN']);
        Regency::create(['short_code' => '25', 'long_code' => '3525', 'id' => '3525', 'name' => 'GRESIK']);
        Regency::create(['short_code' => '26', 'long_code' => '3526', 'id' => '3526', 'name' => 'BANGKALAN']);
        Regency::create(['short_code' => '27', 'long_code' => '3527', 'id' => '3527', 'name' => 'SAMPANG']);
        Regency::create(['short_code' => '28', 'long_code' => '3528', 'id' => '3528', 'name' => 'PAMEKASAN']);
        Regency::create(['short_code' => '29', 'long_code' => '3529', 'id' => '3529', 'name' => 'SUMENEP']);
        Regency::create(['short_code' => '71', 'long_code' => '3571', 'id' => '3571', 'name' => 'KEDIRI']);
        Regency::create(['short_code' => '72', 'long_code' => '3572', 'id' => '3572', 'name' => 'BLITAR']);
        Regency::create(['short_code' => '73', 'long_code' => '3573', 'id' => '3573', 'name' => 'MALANG']);
        Regency::create(['short_code' => '74', 'long_code' => '3574', 'id' => '3574', 'name' => 'PROBOLINGGO']);
        Regency::create(['short_code' => '75', 'long_code' => '3575', 'id' => '3575', 'name' => 'PASURUAN']);
        Regency::create(['short_code' => '76', 'long_code' => '3576', 'id' => '3576', 'name' => 'MOJOKERTO']);
        Regency::create(['short_code' => '77', 'long_code' => '3577', 'id' => '3577', 'name' => 'MADIUN']);
        Regency::create(['short_code' => '78', 'long_code' => '3578', 'id' => '3578', 'name' => 'SURABAYA']);
        Regency::create(['short_code' => '79', 'long_code' => '3579', 'id' => '3579', 'name' => 'BATU']);

        Category::create(['name' => 'Bintang', 'short_name' => 'B', 'code' => '1']);
        Category::create(['name' => 'Non Bintang', 'short_name' => 'NB', 'code' => '2']);

        Indicator::create(['name' => 'TPK', 'short_name' => 'TPK', 'code' => 'TPK', 'scale_factor' => 100]);
        Indicator::create(['name' => 'RLMTA', 'short_name' => 'RLMTA', 'code' => 'RLMTA', 'scale_factor' => 1]);
        Indicator::create(['name' => 'RLMTN', 'short_name' => 'RLMTN', 'code' => 'RLMTN', 'scale_factor' => 1]);
        Indicator::create(['name' => 'GPR', 'short_name' => 'GPR', 'code' => 'GPR', 'scale_factor' => 1]);
        Indicator::create(['name' => 'TPTT', 'short_name' => 'TPTT', 'code' => 'TPTT', 'scale_factor' => 100]);

        Error::create(['code' => 'Hotel', 'name' => 'Error Hotel']);
        Error::create(['code' => 'Indikator', 'name' => 'Error Indikator']);
    }
}
