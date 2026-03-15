<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Error;
use App\Models\ErrorType;
use App\Models\Indicator;
use App\Models\Month;
use App\Models\Regency;
use App\Models\Role;
use App\Models\User;
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

        $parent = Regency::create(['short_code' => '00', 'long_code' => '3500', 'id' => '3500', 'name' => 'JAWA TIMUR']);
        Regency::create(['short_code' => '01', 'long_code' => '3501', 'id' => '3501', 'name' => 'PACITAN', 'parent_id' => $parent->id]);
        Regency::create(['short_code' => '02', 'long_code' => '3502', 'id' => '3502', 'name' => 'PONOROGO', 'parent_id' => $parent->id]);
        Regency::create(['short_code' => '03', 'long_code' => '3503', 'id' => '3503', 'name' => 'TRENGGALEK', 'parent_id' => $parent->id]);
        Regency::create(['short_code' => '04', 'long_code' => '3504', 'id' => '3504', 'name' => 'TULUNGAGUNG', 'parent_id' => $parent->id]);
        Regency::create(['short_code' => '05', 'long_code' => '3505', 'id' => '3505', 'name' => 'BLITAR', 'parent_id' => $parent->id]);
        Regency::create(['short_code' => '06', 'long_code' => '3506', 'id' => '3506', 'name' => 'KEDIRI', 'parent_id' => $parent->id]);
        Regency::create(['short_code' => '07', 'long_code' => '3507', 'id' => '3507', 'name' => 'MALANG', 'parent_id' => $parent->id]);
        Regency::create(['short_code' => '08', 'long_code' => '3508', 'id' => '3508', 'name' => 'LUMAJANG', 'parent_id' => $parent->id]);
        Regency::create(['short_code' => '09', 'long_code' => '3509', 'id' => '3509', 'name' => 'JEMBER', 'parent_id' => $parent->id]);
        Regency::create(['short_code' => '10', 'long_code' => '3510', 'id' => '3510', 'name' => 'BANYUWANGI', 'parent_id' => $parent->id]);
        Regency::create(['short_code' => '11', 'long_code' => '3511', 'id' => '3511', 'name' => 'BONDOWOSO', 'parent_id' => $parent->id]);
        Regency::create(['short_code' => '12', 'long_code' => '3512', 'id' => '3512', 'name' => 'SITUBONDO', 'parent_id' => $parent->id]);
        Regency::create(['short_code' => '13', 'long_code' => '3513', 'id' => '3513', 'name' => 'PROBOLINGGO', 'parent_id' => $parent->id]);
        Regency::create(['short_code' => '14', 'long_code' => '3514', 'id' => '3514', 'name' => 'PASURUAN', 'parent_id' => $parent->id]);
        Regency::create(['short_code' => '15', 'long_code' => '3515', 'id' => '3515', 'name' => 'SIDOARJO', 'parent_id' => $parent->id]);
        Regency::create(['short_code' => '16', 'long_code' => '3516', 'id' => '3516', 'name' => 'MOJOKERTO', 'parent_id' => $parent->id]);
        Regency::create(['short_code' => '17', 'long_code' => '3517', 'id' => '3517', 'name' => 'JOMBANG', 'parent_id' => $parent->id]);
        Regency::create(['short_code' => '18', 'long_code' => '3518', 'id' => '3518', 'name' => 'NGANJUK', 'parent_id' => $parent->id]);
        Regency::create(['short_code' => '19', 'long_code' => '3519', 'id' => '3519', 'name' => 'MADIUN', 'parent_id' => $parent->id]);
        Regency::create(['short_code' => '20', 'long_code' => '3520', 'id' => '3520', 'name' => 'MAGETAN', 'parent_id' => $parent->id]);
        Regency::create(['short_code' => '21', 'long_code' => '3521', 'id' => '3521', 'name' => 'NGAWI', 'parent_id' => $parent->id]);
        Regency::create(['short_code' => '22', 'long_code' => '3522', 'id' => '3522', 'name' => 'BOJONEGORO', 'parent_id' => $parent->id]);
        Regency::create(['short_code' => '23', 'long_code' => '3523', 'id' => '3523', 'name' => 'TUBAN', 'parent_id' => $parent->id]);
        Regency::create(['short_code' => '24', 'long_code' => '3524', 'id' => '3524', 'name' => 'LAMONGAN', 'parent_id' => $parent->id]);
        Regency::create(['short_code' => '25', 'long_code' => '3525', 'id' => '3525', 'name' => 'GRESIK', 'parent_id' => $parent->id]);
        Regency::create(['short_code' => '26', 'long_code' => '3526', 'id' => '3526', 'name' => 'BANGKALAN', 'parent_id' => $parent->id]);
        Regency::create(['short_code' => '27', 'long_code' => '3527', 'id' => '3527', 'name' => 'SAMPANG', 'parent_id' => $parent->id]);
        Regency::create(['short_code' => '28', 'long_code' => '3528', 'id' => '3528', 'name' => 'PAMEKASAN', 'parent_id' => $parent->id]);
        Regency::create(['short_code' => '29', 'long_code' => '3529', 'id' => '3529', 'name' => 'SUMENEP', 'parent_id' => $parent->id]);
        Regency::create(['short_code' => '71', 'long_code' => '3571', 'id' => '3571', 'name' => 'KEDIRI', 'parent_id' => $parent->id]);
        Regency::create(['short_code' => '72', 'long_code' => '3572', 'id' => '3572', 'name' => 'BLITAR', 'parent_id' => $parent->id]);
        Regency::create(['short_code' => '73', 'long_code' => '3573', 'id' => '3573', 'name' => 'MALANG', 'parent_id' => $parent->id]);
        Regency::create(['short_code' => '74', 'long_code' => '3574', 'id' => '3574', 'name' => 'PROBOLINGGO', 'parent_id' => $parent->id]);
        Regency::create(['short_code' => '75', 'long_code' => '3575', 'id' => '3575', 'name' => 'PASURUAN', 'parent_id' => $parent->id]);
        Regency::create(['short_code' => '76', 'long_code' => '3576', 'id' => '3576', 'name' => 'MOJOKERTO', 'parent_id' => $parent->id]);
        Regency::create(['short_code' => '77', 'long_code' => '3577', 'id' => '3577', 'name' => 'MADIUN', 'parent_id' => $parent->id]);
        Regency::create(['short_code' => '78', 'long_code' => '3578', 'id' => '3578', 'name' => 'SURABAYA', 'parent_id' => $parent->id]);
        Regency::create(['short_code' => '79', 'long_code' => '3579', 'id' => '3579', 'name' => 'BATU', 'parent_id' => $parent->id]);

        Category::create(['name' => 'Bintang', 'short_name' => 'B', 'code' => '1']);
        Category::create(['name' => 'Non Bintang', 'short_name' => 'NB', 'code' => '2']);

        Indicator::create(['name' => 'TPK', 'short_name' => 'TPK', 'code' => 'TPK', 'scale_factor' => 100]);
        Indicator::create(['name' => 'RLMTA', 'short_name' => 'RLMTA', 'code' => 'RLMTA', 'scale_factor' => 1]);
        Indicator::create(['name' => 'RLMTN', 'short_name' => 'RLMTN', 'code' => 'RLMTN', 'scale_factor' => 1]);
        Indicator::create(['name' => 'GPR', 'short_name' => 'GPR', 'code' => 'GPR', 'scale_factor' => 1]);
        Indicator::create(['name' => 'TPTT', 'short_name' => 'TPTT', 'code' => 'TPTT', 'scale_factor' => 100]);

        Error::create(['code' => 'Hotel', 'name' => 'Error Hotel']);
        Error::create(['code' => 'Indikator', 'name' => 'Error Indikator']);

        ErrorType::create(['code' => 'error_tpk', 'column_name' => 'error_tpk', 'color' => 'red']);
        ErrorType::create(['code' => 'error_rlmta', 'column_name' => 'error_rlmta', 'color' => 'green']);
        ErrorType::create(['code' => 'error_rlmtnus', 'column_name' => 'error_rlmtnus', 'color' => 'orange']);
        ErrorType::create(['code' => 'error_gpr', 'column_name' => 'error_gpr', 'color' => 'purple']);
        ErrorType::create(['code' => 'error_tptt', 'column_name' => 'error_tptt', 'color' => 'cyan']);
        ErrorType::create(['code' => 'error_hari', 'column_name' => 'error_hari', 'color' => 'blue']);        

        $adminprov = Role::create(['name' => 'adminprov']);
        $adminkab = Role::create(['name' => 'adminkab']);

        $user = User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => bcrypt('123456'),
            'regency_id' => '3500',
        ]);
        $user->assignRole($adminprov);

        $user = User::create(['name' => 'ksatrio.jati@bps.go.id', 'email' => 'ksatrio.jati@bps.go.id', 'password' => bcrypt('123456'), 'regency_id' => '3500',]);
        $user->assignRole($adminprov);
        $user = User::create(['name' => 'nur.jannati@bps.go.id', 'email' => 'nur.jannati@bps.go.id', 'password' => bcrypt('123456'), 'regency_id' => '3500',]);
        $user->assignRole($adminprov);
        $user = User::create(['name' => 'aldizah@bps.go.id', 'email' => 'aldizah@bps.go.id', 'password' => bcrypt('123456'), 'regency_id' => '3500',]);
        $user->assignRole($adminprov);
        $user = User::create(['name' => 'ridnuw@bps.go.id', 'email' => 'ridnuw@bps.go.id', 'password' => bcrypt('123456'), 'regency_id' => '3526',]);
        $user->assignRole($adminkab);
        $user = User::create(['name' => 'dwi.handayani@bps.go.id', 'email' => 'dwi.handayani@bps.go.id', 'password' => bcrypt('123456'), 'regency_id' => '3573',]);
        $user->assignRole($adminkab);
        $user = User::create(['name' => 'muhammad.syadad@bps.go.id', 'email' => 'muhammad.syadad@bps.go.id', 'password' => bcrypt('123456'), 'regency_id' => '3525',]);
        $user->assignRole($adminkab);
        $user = User::create(['name' => 'dian.musvitasari@bps.go.id', 'email' => 'dian.musvitasari@bps.go.id', 'password' => bcrypt('123456'), 'regency_id' => '3505',]);
        $user->assignRole($adminkab);
        $user = User::create(['name' => 'emyvida@bps.go.id', 'email' => 'emyvida@bps.go.id', 'password' => bcrypt('123456'), 'regency_id' => '3577',]);
        $user->assignRole($adminkab);
        $user = User::create(['name' => 'merivita@bps.go.id', 'email' => 'merivita@bps.go.id', 'password' => bcrypt('123456'), 'regency_id' => '3509',]);
        $user->assignRole($adminkab);
        $user = User::create(['name' => 'nur.kholis@bps.go.id', 'email' => 'nur.kholis@bps.go.id', 'password' => bcrypt('123456'), 'regency_id' => '3527',]);
        $user->assignRole($adminkab);
        $user = User::create(['name' => 'dwi.irnawati@bps.go.id', 'email' => 'dwi.irnawati@bps.go.id', 'password' => bcrypt('123456'), 'regency_id' => '3512',]);
        $user->assignRole($adminkab);
        $user = User::create(['name' => 'sayu.widiari@bps.go.id', 'email' => 'sayu.widiari@bps.go.id', 'password' => bcrypt('123456'), 'regency_id' => '3579',]);
        $user->assignRole($adminkab);
        $user = User::create(['name' => 'jamin@bps.go.id', 'email' => 'jamin@bps.go.id', 'password' => bcrypt('123456'), 'regency_id' => '3524',]);
        $user->assignRole($adminkab);
        $user = User::create(['name' => 'jokokn@bps.go.id', 'email' => 'jokokn@bps.go.id', 'password' => bcrypt('123456'), 'regency_id' => '3571',]);
        $user->assignRole($adminkab);
        $user = User::create(['name' => 'devon@bps.go.id', 'email' => 'devon@bps.go.id', 'password' => bcrypt('123456'), 'regency_id' => '3517',]);
        $user->assignRole($adminkab);
        $user = User::create(['name' => 'apridiansulistiana@bps.go.id', 'email' => 'apridiansulistiana@bps.go.id', 'password' => bcrypt('123456'), 'regency_id' => '3521',]);
        $user->assignRole($adminkab);
        $user = User::create(['name' => 'rindyanita@bps.go.id', 'email' => 'rindyanita@bps.go.id', 'password' => bcrypt('123456'), 'regency_id' => '3516',]);
        $user->assignRole($adminkab);
        $user = User::create(['name' => 'yuanita@bps.go.id', 'email' => 'yuanita@bps.go.id', 'password' => bcrypt('123456'), 'regency_id' => '3504',]);
        $user->assignRole($adminkab);
        $user = User::create(['name' => 'raden.rara@bps.go.id', 'email' => 'raden.rara@bps.go.id', 'password' => bcrypt('123456'), 'regency_id' => '3578',]);
        $user->assignRole($adminkab);
        $user = User::create(['name' => 'ekwan@bps.go.id', 'email' => 'ekwan@bps.go.id', 'password' => bcrypt('123456'), 'regency_id' => '3503',]);
        $user->assignRole($adminkab);
        $user = User::create(['name' => 'debita.tejo@bps.go.id', 'email' => 'debita.tejo@bps.go.id', 'password' => bcrypt('123456'), 'regency_id' => '3514',]);
        $user->assignRole($adminkab);
        $user = User::create(['name' => 'rendra@bps.go.id', 'email' => 'rendra@bps.go.id', 'password' => bcrypt('123456'), 'regency_id' => '3573',]);
        $user->assignRole($adminkab);
        $user = User::create(['name' => 'fentielektriana@bps.go.id', 'email' => 'fentielektriana@bps.go.id', 'password' => bcrypt('123456'), 'regency_id' => '3507',]);
        $user->assignRole($adminkab);
        $user = User::create(['name' => 'fajar.c.anwar@bps.go.id', 'email' => 'fajar.c.anwar@bps.go.id', 'password' => bcrypt('123456'), 'regency_id' => '3503',]);
        $user->assignRole($adminkab);
        $user = User::create(['name' => 'saiful.hadi@bps.go.id', 'email' => 'saiful.hadi@bps.go.id', 'password' => bcrypt('123456'), 'regency_id' => '3513',]);
        $user->assignRole($adminkab);
        $user = User::create(['name' => 'dadanghermawan@bps.go.id', 'email' => 'dadanghermawan@bps.go.id', 'password' => bcrypt('123456'), 'regency_id' => '3529',]);
        $user->assignRole($adminkab);
        $user = User::create(['name' => 'ali.imron@bps.go.id', 'email' => 'ali.imron@bps.go.id', 'password' => bcrypt('123456'), 'regency_id' => '3506',]);
        $user->assignRole($adminkab);
        $user = User::create(['name' => 'wahyu.jatmiko@bps.go.id', 'email' => 'wahyu.jatmiko@bps.go.id', 'password' => bcrypt('123456'), 'regency_id' => '3504',]);
        $user->assignRole($adminkab);
        $user = User::create(['name' => 'meiliya@bps.go.id', 'email' => 'meiliya@bps.go.id', 'password' => bcrypt('123456'), 'regency_id' => '3574',]);
        $user->assignRole($adminkab);
        $user = User::create(['name' => 'dwiyanti@bps.go.id', 'email' => 'dwiyanti@bps.go.id', 'password' => bcrypt('123456'), 'regency_id' => '3516',]);
        $user->assignRole($adminkab);
        $user = User::create(['name' => 'akhmad.hidayat@bps.go.id', 'email' => 'akhmad.hidayat@bps.go.id', 'password' => bcrypt('123456'), 'regency_id' => '3510',]);
        $user->assignRole($adminkab);
        $user = User::create(['name' => 'zaenal.arifin@bps.go.id', 'email' => 'zaenal.arifin@bps.go.id', 'password' => bcrypt('123456'), 'regency_id' => '3508',]);
        $user->assignRole($adminkab);
        $user = User::create(['name' => 'ikarahma@bps.go.id', 'email' => 'ikarahma@bps.go.id', 'password' => bcrypt('123456'), 'regency_id' => '3523',]);
        $user->assignRole($adminkab);
        $user = User::create(['name' => 'binti@bps.go.id', 'email' => 'binti@bps.go.id', 'password' => bcrypt('123456'), 'regency_id' => '3520',]);
        $user->assignRole($adminkab);
        $user = User::create(['name' => 'pamuji2@bps.go.id', 'email' => 'pamuji2@bps.go.id', 'password' => bcrypt('123456'), 'regency_id' => '3506',]);
        $user->assignRole($adminkab);
        $user = User::create(['name' => 'ivar@bps.go.id', 'email' => 'ivar@bps.go.id', 'password' => bcrypt('123456'), 'regency_id' => '3574',]);
        $user->assignRole($adminkab);
        $user = User::create(['name' => 'ganesdeatama@bps.go.id', 'email' => 'ganesdeatama@bps.go.id', 'password' => bcrypt('123456'), 'regency_id' => '3577',]);
        $user->assignRole($adminkab);
        $user = User::create(['name' => 'handoyo@bps.go.id', 'email' => 'handoyo@bps.go.id', 'password' => bcrypt('123456'), 'regency_id' => '3528',]);
        $user->assignRole($adminkab);
        $user = User::create(['name' => 'dian.sari@bps.go.id', 'email' => 'dian.sari@bps.go.id', 'password' => bcrypt('123456'), 'regency_id' => '3511',]);
        $user->assignRole($adminkab);
        $user = User::create(['name' => '3578.robyn@dummy.sobat.id', 'email' => '3578.robyn@dummy.sobat.id', 'password' => bcrypt('123456'), 'regency_id' => '3578',]);
        $user->assignRole($adminkab);
        $user = User::create(['name' => 'rafiqa.zein@bps.go.id', 'email' => 'rafiqa.zein@bps.go.id', 'password' => bcrypt('123456'), 'regency_id' => '3518',]);
        $user->assignRole($adminkab);
        $user = User::create(['name' => 'navy@bps.go.id', 'email' => 'navy@bps.go.id', 'password' => bcrypt('123456'), 'regency_id' => '3575',]);
        $user->assignRole($adminkab);
        $user = User::create(['name' => 'ison.fatawi@bps.go.id', 'email' => 'ison.fatawi@bps.go.id', 'password' => bcrypt('123456'), 'regency_id' => '3502',]);
        $user->assignRole($adminkab);
        $user = User::create(['name' => 'yuliatin@bps.go.id', 'email' => 'yuliatin@bps.go.id', 'password' => bcrypt('123456'), 'regency_id' => '3522',]);
        $user->assignRole($adminkab);
        $user = User::create(['name' => 'diana.fatmawati@bps.go.id', 'email' => 'diana.fatmawati@bps.go.id', 'password' => bcrypt('123456'), 'regency_id' => '3572',]);
        $user->assignRole($adminkab);
        $user = User::create(['name' => 'alamsyah@bps.go.id', 'email' => 'alamsyah@bps.go.id', 'password' => bcrypt('123456'), 'regency_id' => '3525',]);
        $user->assignRole($adminkab);
        $user = User::create(['name' => 'sudarmono@bps.go.id', 'email' => 'sudarmono@bps.go.id', 'password' => bcrypt('123456'), 'regency_id' => '3519',]);
        $user->assignRole($adminkab);
        $user = User::create(['name' => 'suwarno4@bps.go.id', 'email' => 'suwarno4@bps.go.id', 'password' => bcrypt('123456'), 'regency_id' => '3576',]);
        $user->assignRole($adminkab);
        $user = User::create(['name' => 'apriyanto.nugroho@bps.go.id', 'email' => 'apriyanto.nugroho@bps.go.id', 'password' => bcrypt('123456'), 'regency_id' => '3501',]);
        $user->assignRole($adminkab);
        $user = User::create(['name' => 'indrajati@bps.go.id', 'email' => 'indrajati@bps.go.id', 'password' => bcrypt('123456'), 'regency_id' => '3515',]);
        $user->assignRole($adminkab);
        $user = User::create(['name' => 'ekacahyani@bps.go.id', 'email' => 'ekacahyani@bps.go.id', 'password' => bcrypt('123456'), 'regency_id' => '3579',]);
        $user->assignRole($adminkab);
    }
}
