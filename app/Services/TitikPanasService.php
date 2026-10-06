<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TitikPanasService
{
    // Kotak area: barat, selatan, timur, utara (perkiraan kasar sekitar Riau)
    private const AREA = '100.0,-1.2,104.6,2.6';

    // Sumber satelit
    private const SUMBER = 'VIIRS_SNPP_NRT';

    // Rentang hari dihitung per tanggal UTC. Nilai 2 = hari ini + kemarin (UTC),
    // supaya pagi hari WIB tetap dapat data. Jangan diubah ke 1.
    private const HARI = 2;

    /**
     * Semua titik panas di Riau (2 hari UTC).
     * Array kosong kalau tidak ada, null kalau pemanggilan API gagal.
     */
    public function ambil(): ?array
    {
        return Cache::remember('titik_panas_riau_v2', 900, function () {
            $key = config('services.firms.key');

            if (! $key) {
                Log::error('FIRMS_MAP_KEY belum diisi di .env');

                return null;
            }

            $url = 'https://firms.modaps.eosdis.nasa.gov/api/area/csv/'
                .$key.'/'.self::SUMBER.'/'.self::AREA.'/'.self::HARI;

            try {
                $res = Http::timeout(20)->get($url);
            } catch (\Throwable $e) {
                // Pesan error bisa memuat URL beserta key, jadi key disamarkan
                Log::error('FIRMS gagal: '.str_replace($key, '***', $e->getMessage()));

                return null;
            }

            if (! $res->successful()) {
                Log::error('FIRMS status '.$res->status());

                return null;
            }

            $baris = preg_split('/\r\n|\r|\n/', trim($res->body()));
            $header = str_getcsv(array_shift($baris));

            // Kalau bukan CSV yang benar (misalnya pesan "invalid key"), anggap gagal
            if (! in_array('latitude', $header, true) || ! in_array('longitude', $header, true)) {
                Log::error('FIRMS balasan tidak dikenal: '.substr($res->body(), 0, 100));

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

                $jam = str_pad($t['acq_time'] ?? '0', 4, '0', STR_PAD_LEFT);
                $ts = Carbon::parse(
                    ($t['acq_date'] ?? '1970-01-01').' '.substr($jam, 0, 2).':'.substr($jam, 2, 2),
                    'UTC'
                )->timestamp;

                $hasil[] = [
                    'lat' => (float) $t['latitude'],
                    'lon' => (float) $t['longitude'],
                    'tgl' => $t['acq_date'] ?? null,
                    'ts' => $ts, // waktu deteksi (UTC, epoch)
                    'keyakinan' => $this->keyakinan($t['confidence'] ?? ''),
                    'frp' => (float) ($t['frp'] ?? 0),
                ];
            }

            return $hasil;
        });
    }

    /**
     * Titik panas 24 jam terakhir, diurutkan dari yang terdekat ke lokasi pengguna.
     * Tiap titik ditambah jarak_km dan jam_wib. null = API gagal, [] = tidak ada titik.
     */
    public function dekatDari(float $lat, float $lon, int $radiusKm = 0, int $batas = 60): ?array
    {
        $semua = $this->ambil();
        if ($semua === null) {
            return null;
        }

        $batasWaktu = now()->subHours(24)->timestamp;

        return collect($semua)
            ->filter(fn ($t) => $t['ts'] >= $batasWaktu)
            ->map(fn ($t) => $t + [
                'jarak_km' => round($this->jarak($lat, $lon, $t['lat'], $t['lon']), 1),
                'jam_wib' => Carbon::createFromTimestamp($t['ts'], 'Asia/Jakarta')->format('H:i'),
            ])
            ->when($radiusKm > 0, fn ($c) => $c->filter(fn ($t) => $t['jarak_km'] <= $radiusKm))
            ->sortBy('jarak_km')
            ->values()
            ->take($batas)
            ->all();
    }

    // VIIRS memberi confidence l / n / h
    private function keyakinan(string $c): string
    {
        return match (strtolower($c)) {
            'h' => 'tinggi',
            'n' => 'sedang',
            default => 'rendah',
        };
    }

    // Jarak garis lurus (haversine), kilometer
    private function jarak(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $p = M_PI / 180;
        $a = sin(($lat2 - $lat1) * $p / 2) ** 2
            + cos($lat1 * $p) * cos($lat2 * $p) * sin(($lon2 - $lon1) * $p / 2) ** 2;

        return 12742 * asin(sqrt($a));
    }
}
