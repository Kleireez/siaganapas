<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TitikPanasService
{
    // Kotak area: barat, selatan, timur, utara (perkiraan kasar sekitar Riau)
    private const AREA = '100.0,-1.2,104.6,2.6';

    // Sumber satelit dan rentang hari (1 = 24 jam terakhir)
    private const SUMBER = 'VIIRS_SNPP_NRT';
    private const HARI = 2;

    /**
     * Mengembalikan array titik panas, array kosong kalau tidak ada,
     * atau null kalau pemanggilan API gagal.
     */
    public function ambil(): ?array
    {
        return Cache::remember('titik_panas_riau', 900, function () {
            $key = config('services.firms.key');

            if (!$key) {
                Log::error('FIRMS_MAP_KEY belum diisi di .env');
                return null;
            }

            $url = 'https://firms.modaps.eosdis.nasa.gov/api/area/csv/'
                . $key . '/' . self::SUMBER . '/' . self::AREA . '/' . self::HARI;

            try {
                $res = Http::timeout(20)->get($url);
            } catch (\Throwable $e) {
                // Pesan error bisa memuat URL beserta key, jadi key disamarkan
                Log::error('FIRMS gagal: ' . str_replace($key, '***', $e->getMessage()));
                return null;
            }

            if (!$res->successful()) {
                Log::error('FIRMS status ' . $res->status());
                return null;
            }

            $baris = preg_split('/\r\n|\r|\n/', trim($res->body()));
            $header = str_getcsv(array_shift($baris));

            // Kalau bukan CSV yang benar (misalnya pesan "invalid key"), anggap gagal
            if (!in_array('latitude', $header, true) || !in_array('longitude', $header, true)) {
                Log::error('FIRMS balasan tidak dikenal: ' . substr($res->body(), 0, 100));
                return null;
            }

            $hasil = [];
            foreach ($baris as $b) {
                if ($b === '') {
                    continue;
                }
                $nilai = str_getcsv($b);
                if (count($nilai) !== count($header)) {
                    continue;
                }
                $t = array_combine($header, $nilai);
                $hasil[] = [
                    'lat' => (float) $t['latitude'],
                    'lon' => (float) $t['longitude'],
                    'tgl' => $t['acq_date'] ?? null,
                ];
            }

            return $hasil;
        });
    }
}
