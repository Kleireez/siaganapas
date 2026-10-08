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
     * Semua titik panas di kotak area Riau (2 hari UTC), belum dikaitkan ke kota tertentu.
     * Array kosong kalau tidak ada, null kalau pemanggilan API gagal.
     */
    public function ambil(): ?array
    {
        return Cache::remember('titik_panas_riau_v3', 900, function () {
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

                $lat = (float) $t['latitude'];
                $lon = (float) $t['longitude'];

                $jam = str_pad($t['acq_time'] ?? '0', 4, '0', STR_PAD_LEFT);
                $ts = Carbon::parse(
                    ($t['acq_date'] ?? '1970-01-01').' '.substr($jam, 0, 2).':'.substr($jam, 2, 2),
                    'UTC'
                )->timestamp;
                $wib = Carbon::createFromTimestamp($ts, 'Asia/Jakarta');

                $hasil[] = [
                    'latitude' => $lat,
                    'longitude' => $lon,
                    'confidence' => $this->keyakinan($t['confidence'] ?? ''),
                    'frp' => (float) ($t['frp'] ?? 0),
                    'location' => $this->wilayah($lat, $lon),
                    'timestamp' => $ts,
                    'datetime' => $wib->format('Y-m-d H:i:s'),
                    'time_label' => $wib->format('H.i').' WIB',
                ];
            }

            return $hasil;
        });
    }

    /**
     * Titik panas 24 jam terakhir, diurutkan dari yang terdekat ke koordinat kota terpilih.
     * Tiap titik ditambah distance (km) dan direction (arah dari kota ke titik).
     * null = API gagal, [] = tidak ada titik. $batas = 0 berarti tanpa batas.
     */
    public function dekatDari(float $lat, float $lon, int $radiusKm = 0, int $batas = 0): ?array
    {
        $semua = $this->ambil();
        if ($semua === null) {
            return null;
        }

        $batasWaktu = now()->subHours(24)->timestamp;

        return collect($semua)
            ->filter(fn ($t) => $t['timestamp'] >= $batasWaktu)
            ->map(fn ($t) => $t + [
                'distance' => round($this->jarak($lat, $lon, $t['latitude'], $t['longitude']), 1),
                'direction' => $this->arah($lat, $lon, $t['latitude'], $t['longitude']),
            ])
            ->when($radiusKm > 0, fn ($c) => $c->filter(fn ($t) => $t['distance'] <= $radiusKm))
            ->sortBy('distance')
            ->values()
            ->when($batas > 0, fn ($c) => $c->take($batas))
            ->all();
    }

    /**
     * Perkiraan wilayah: kabupaten/kota (dari config) yang pusatnya paling dekat dengan titik.
     * Ini PERKIRAAN, bukan batas administrasi resmi. Lebih dari 150 km dari semua pusat = "Perbatasan Riau".
     */
    private function wilayah(float $lat, float $lon): string
    {
        $terdekat = null;
        $min = INF;

        foreach (config('siaganapas.cities', []) as $kota) {
            $j = $this->jarak($lat, $lon, $kota['lat'], $kota['lon']);
            if ($j < $min) {
                $min = $j;
                $terdekat = $kota;
            }
        }

        if (! $terdekat || $min > 150) {
            return 'Perbatasan Riau';
        }

        return trim(($terdekat['jenis'] ?? '').' '.$terdekat['nama']);
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

    // Arah mata angin dari titik 1 ke titik 2
    private function arah(float $lat1, float $lon1, float $lat2, float $lon2): string
    {
        $p = M_PI / 180;
        $y = sin(($lon2 - $lon1) * $p) * cos($lat2 * $p);
        $x = cos($lat1 * $p) * sin($lat2 * $p) - sin($lat1 * $p) * cos($lat2 * $p) * cos(($lon2 - $lon1) * $p);
        $derajat = fmod(atan2($y, $x) / $p + 360, 360);

        $nama = ['utara', 'timur laut', 'timur', 'tenggara', 'selatan', 'barat daya', 'barat', 'barat laut'];

        return $nama[(int) round($derajat / 45) % 8];
    }
}
