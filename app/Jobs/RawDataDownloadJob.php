<?php

namespace App\Jobs;

use App\Models\DownloadStatus;
use App\Models\Input;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use League\Csv\Writer;

class RawDataDownloadJob implements ShouldQueue
{
    use Queueable;

    protected DownloadStatus $downloadStatus;

    /**
     * Create a new job instance.
     */
    public function __construct(DownloadStatus $downloadStatus)
    {
        $this->downloadStatus = $downloadStatus;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->downloadStatus->update([
            'status' => 'loading',
        ]);

        try {
            if (! Storage::exists('raw_data_downloads')) {
                Storage::makeDirectory('raw_data_downloads');
            }

            $filename = $this->downloadStatus->filename;

            $csv = Writer::from(new \SplFileObject(Storage::path('raw_data_downloads/'.$filename), 'w+'));
            $csv->setDelimiter(',');
            $csv->setEnclosure('"');

            $csv->insertOne([
                'id',
                'id_fasih',
                'tanggal_update',
                'tarikan_ke',
                'idunik',
                'tahun',
                'bulan',
                'kode_prov',
                'kode_kab',
                'kode_kec',
                'kode_desa',
                'status_kunjungan',
                'jenis_akomodasi',
                'kelas_akomodasi',
                'nama_komersial',
                'alamat',
                'room',
                'bed',
                'room_yesterday',
                'room_in',
                'room_out',
                'wna_yesterday',
                'wni_yesterday',
                'wna_in',
                'wni_in',
                'wna_out',
                'wni_out',
                'status',
                'room_per_day',
                'bed_per_day',
                'day',
                'user_id',
                'sync_status_id',
                'mkts',
                'mktj',
                'tpk',
                'mta',
                'ta',
                'mtnus',
                'tnus',
                'rlmta',
                'rlmtnus',
                'mtgab',
                'tgab',
                'rlmtgab',
                'gpr',
                'tptt',
                'jumlah_hari',
                'error_tpk',
                'error_rlmta',
                'error_rlmtnus',
                'error_gpr',
                'error_tptt',
                'error_hari',
                'jumlah_error',
                'created_at',
                'updated_at',
            ]);

            $user = $this->downloadStatus->user;
            $isAdminProv = $user->hasRole('adminprov');

            Input::query()
                ->when($this->downloadStatus->year_id, fn ($q) => $q->where('tahun', $this->downloadStatus->year_id))
                ->when($this->downloadStatus->month_id, fn ($q) => $q->where('bulan', $this->downloadStatus->month_id))
                ->when(! $isAdminProv, fn ($q) => $q->where('kode_kab', $user->regency_id))
                ->chunk(1000, function ($inputs) use ($csv) {
                    foreach ($inputs as $row) {
                        $csv->insertOne([
                            $row->id,
                            $row->id_fasih,
                            $row->tanggal_update,
                            $row->tarikan_ke,
                            $row->idunik,
                            $row->tahun,
                            $row->bulan,
                            $row->kode_prov,
                            $row->kode_kab,
                            $row->kode_kec,
                            $row->kode_desa,
                            $row->status_kunjungan,
                            $row->jenis_akomodasi,
                            $row->kelas_akomodasi,
                            $row->nama_komersial,
                            $row->alamat,
                            $row->room,
                            $row->bed,
                            $row->room_yesterday,
                            $row->room_in,
                            $row->room_out,
                            $row->wna_yesterday,
                            $row->wni_yesterday,
                            $row->wna_in,
                            $row->wni_in,
                            $row->wna_out,
                            $row->wni_out,
                            $row->status,
                            $row->room_per_day,
                            $row->bed_per_day,
                            $row->day,
                            $row->user_id,
                            $row->sync_status_id,
                            $row->mkts,
                            $row->mktj,
                            $row->tpk,
                            $row->mta,
                            $row->ta,
                            $row->mtnus,
                            $row->tnus,
                            $row->rlmta,
                            $row->rlmtnus,
                            $row->mtgab,
                            $row->tgab,
                            $row->rlmtgab,
                            $row->gpr,
                            $row->tptt,
                            $row->jumlah_hari,
                            $row->error_tpk,
                            $row->error_rlmta,
                            $row->error_rlmtnus,
                            $row->error_gpr,
                            $row->error_tptt,
                            $row->error_hari,
                            $row->jumlah_error,
                            $row->created_at,
                            $row->updated_at,
                        ]);
                    }
                });

            $this->downloadStatus->update([
                'status' => 'success',
            ]);
        } catch (Exception $e) {
            $this->downloadStatus->update([
                'status' => 'failed',
                'system_message' => $e->getMessage(),
                'user_message' => $e->getMessage(),
            ]);
        }
    }
}
