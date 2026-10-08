<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class UdaraService
{
    private const URL = 'https://air-quality-api.open-meteo.com/v1/air-quality';

    /**
     * Kualitas udara saat ini (Open-Meteo, skala US AQI).
     * Field yang tidak diberikan API diisi null (tidak dikarang).
     * AQI sendiri wajib ada; kalau tidak ada, dianggap gagal (null).
     * Kalau API gagal, dipakai data sukses terakhir (kalau pernah ada).
     */
    public function ambil(float $lat, float $lon): ?array
    {
        $kunci = 'udara_v2_'.round($lat, 2).'_'.round($lon, 2);

        return Cache::remember($kunci, 600, function () use ($lat, $lon, $kunci) {
            $data = $this->panggilSaatIni($lat, $lon);

            if ($data) {
                Cache::forever($kunci.'_terakhir', $data);

                return $data;
            }

            return Cache::get($kunci.'_terakhir');
        });
    }

    /**
     * Tren AQI hari ini (per 2 jam) + rata-rata harian 3 hari (hari ini dan 2 hari ke depan).
     * Hasil: ['per_jam' => [['time' => '08:00', 'aqi' => 152, 'is_now' => true], ...],
     *         'harian' => ['2026-10-08' => 152, ...]]
     */
    public function tren(float $lat, float $lon): ?array
    {
        $kunci = 'tren_v2_'.round($lat, 2).'_'.round($lon, 2);

        $data = Cache::remember($kunci, 1800, function () use ($lat, $lon, $kunci) {
            $hasil = $this->panggilTren($lat, $lon);

            if ($hasil) {
                Cache::forever($kunci.'_terakhir', $hasil);

                return $hasil;
            }

            return Cache::get($kunci.'_terakhir');
        });

        if (! $data) {
            return null;
        }

        // Penanda jam sekarang dihitung di luar cache supaya tidak basi
        $jamGenap = intdiv((int) now('Asia/Jakarta')->format('G'), 2) * 2;
        $data['per_jam'] = array_map(
            fn ($j) => $j + ['is_now' => (int) substr($j['time'], 0, 2) === $jamGenap],
            $data['per_jam']
        );

        return $data;
    }

    /**
     * Ringkasan dari tren: jam terburuk/terbaik hari ini, waktu terbaik yang tersisa,
     * dan kecenderungan ('membaik' | 'memburuk' | 'stabil' | 'tidak-tersedia').
     */
    public function ringkasan(array $perJam, ?int $aqiSekarang): array
    {
        $kosong = ['terburuk' => null, 'terbaik' => null, 'waktu_terbaik' => null, 'kecenderungan' => 'tidak-tersedia'];

        if (! $perJam || $aqiSekarang === null) {
            return $kosong;
        }

        $semua = collect($perJam);
        $jamIni = (int) now('Asia/Jakarta')->format('G');
        $sisa = $semua->filter(fn ($j) => (int) substr($j['time'], 0, 2) >= $jamIni);

        $kandidat = $sisa->sortBy('aqi')->first();
        // Hanya dianggap "waktu terbaik" kalau lebih baik minimal 20 poin dari sekarang
        $waktuTerbaik = ($kandidat && $kandidat['aqi'] <= $aqiSekarang - 20) ? $kandidat : null;
        $puncak = $sisa->max('aqi');

        return [
            'terburuk' => $semua->sortByDesc('aqi')->first(),
            'terbaik' => $semua->sortBy('aqi')->first(),
            'waktu_terbaik' => $waktuTerbaik,
            'kecenderungan' => match (true) {
                $waktuTerbaik !== null => 'membaik',
                $puncak !== null && $puncak >= $aqiSekarang + 10 => 'memburuk',
                default => 'stabil',
            },
        ];
    }

    public function levelAqi(?float $aqi): string
    {
        if ($aqi === null) {
            return 'Tidak tersedia';
        }

        return match (true) {
            $aqi <= 50 => 'Baik',
            $aqi <= 100 => 'Sedang',
            $aqi <= 150 => 'Tidak Sehat untuk Kelompok Sensitif',
            $aqi <= 200 => 'Tidak Sehat',
            $aqi <= 300 => 'Sangat Tidak Sehat',
            default => 'Berbahaya',
        };
    }

    // Kunci tetap untuk pemetaan warna di frontend (tanpa kelas CSS di PHP)
    public function levelKey(?float $aqi): string
    {
        if ($aqi === null) {
            return 'tidak-tersedia';
        }

        return match (true) {
            $aqi <= 50 => 'baik',
            $aqi <= 100 => 'sedang',
            $aqi <= 150 => 'sensitif',
            $aqi <= 200 => 'tidak-sehat',
            $aqi <= 300 => 'sangat-tidak-sehat',
            default => 'berbahaya',
        };
    }

    // Label PM2.5 (batas mengikuti kategori US AQI)
    public function levelPm25(?float $pm25): string
    {
        if ($pm25 === null) {
            return 'Tidak tersedia';
        }

        return match (true) {
            $pm25 <= 9.0 => 'Rendah',
            $pm25 <= 35.4 => 'Sedang',
            $pm25 <= 55.4 => 'Cukup tinggi',
            $pm25 <= 125.4 => 'Tinggi',
            $pm25 <= 225.4 => 'Sangat tinggi',
            default => 'Berbahaya',
        };
    }

    /**
     * Data contoh HANYA untuk saat API gagal dan belum ada data tersimpan.
     * Selalu dipasangkan dengan $offline = true di controller.
     */
    public function contoh(): array
    {
        return $this->susun(152, 58.0, 70.0, null, null, null, null);
    }

    private function panggilSaatIni(float $lat, float $lon): ?array
    {
        try {
            $res = Http::timeout(10)->get(self::URL, [
                'latitude' => $lat,
                'longitude' => $lon,
                'current' => 'us_aqi,pm2_5,pm10,carbon_monoxide,nitrogen_dioxide,sulphur_dioxide',
                'timezone' => 'Asia/Jakarta',
            ]);
        } catch (\Throwable $e) {
            Log::error('Open-Meteo udara gagal: '.$e->getMessage());

            return null;
        }

        if (! $res->successful()) {
            Log::error('Open-Meteo udara status '.$res->status().': '.$res->body());

            return null;
        }

        $c = $res->json('current');
        if (! $c || ! isset($c['us_aqi'])) {
            Log::error('Open-Meteo udara: us_aqi tidak ada di respons');

            return null;
        }

        $angka = fn (string $k) => isset($c[$k]) ? round((float) $c[$k], 1) : null;
        $waktu = isset($c['time']) ? Carbon::parse($c['time'], 'Asia/Jakarta')->format('Y-m-d H:i:s') : null;

        return $this->susun(
            (int) round($c['us_aqi']),
            $angka('pm2_5'), $angka('pm10'),
            $angka('carbon_monoxide'), $angka('nitrogen_dioxide'), $angka('sulphur_dioxide'),
            $waktu
        );
    }

    private function susun(int $aqi, ?float $pm25, ?float $pm10, ?float $co, ?float $no2, ?float $so2, ?string $waktu): array
    {
        return [
            'aqi' => $aqi,
            'level' => $this->levelAqi($aqi),
            'level_key' => $this->levelKey($aqi),
            'scale_percent' => (int) round(min($aqi, 300) / 300 * 100), // posisi penanda di skala 0-300
            'pm25' => $pm25,
            'pm25_level' => $this->levelPm25($pm25),
            'pm10' => $pm10,
            'co' => $co,   // µg/m³
            'no2' => $no2,  // µg/m³
            'so2' => $so2,  // µg/m³
            'updated_at' => $waktu,
        ];
    }

    private function panggilTren(float $lat, float $lon): ?array
    {
        try {
            $res = Http::timeout(10)->get(self::URL, [
                'latitude' => $lat,
                'longitude' => $lon,
                'hourly' => 'us_aqi',
                'forecast_days' => 3,
                'timezone' => 'Asia/Jakarta',
            ]);
        } catch (\Throwable $e) {
            Log::error('Open-Meteo tren gagal: '.$e->getMessage());

            return null;
        }

        if (! $res->successful()) {
            Log::error('Open-Meteo tren status '.$res->status());

            return null;
        }

        $waktu = $res->json('hourly.time') ?? [];
        $aqi = $res->json('hourly.us_aqi') ?? [];
        if (! $waktu || ! $aqi) {
            return null;
        }

        $hariIni = now('Asia/Jakarta')->toDateString();
        $perJam = [];
        $perHari = [];

        foreach ($waktu as $i => $w) {
            if (! isset($aqi[$i])) {
                continue; // nilai kosong di ujung prakiraan
            }
            $tgl = substr($w, 0, 10);
            $jam = (int) substr($w, 11, 2);

            $perHari[$tgl][] = $aqi[$i];

            if ($tgl === $hariIni && $jam % 2 === 0) {
                $perJam[] = ['time' => sprintf('%02d:00', $jam), 'aqi' => (int) round($aqi[$i])];
            }
        }

        $harian = [];
        foreach ($perHari as $tgl => $nilai) {
            if ($tgl >= $hariIni) {
                $harian[$tgl] = (int) round(array_sum($nilai) / count($nilai));
            }
        }
        ksort($harian);

        return ['per_jam' => $perJam, 'harian' => array_slice($harian, 0, 3, true)];
    }
}
