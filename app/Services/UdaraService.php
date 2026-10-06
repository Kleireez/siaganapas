<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class UdaraService
{
    private const URL = 'https://air-quality-api.open-meteo.com/v1/air-quality';

    /**
     * AQI saat ini dari Open-Meteo. Kalau API gagal, dipakai data sukses terakhir.
     * Mengembalikan null hanya kalau belum pernah berhasil sama sekali.
     */
    public function ambil(float $lat, float $lon): ?array
    {
        $kunci = 'udara_'.round($lat, 2).'_'.round($lon, 2);

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
     * Tren AQI: per 2 jam untuk hari ini, dan rata-rata harian 3 hari ke depan.
     * Hasil: ['per_jam' => [['jam' => '08', 'aqi' => 152], ...], 'harian' => ['2026-10-07' => 138, ...]]
     */
    public function tren(float $lat, float $lon): ?array
    {
        $kunci = 'tren_'.round($lat, 2).'_'.round($lon, 2);

        return Cache::remember($kunci, 1800, function () use ($lat, $lon, $kunci) {
            $data = $this->panggilTren($lat, $lon);

            if ($data) {
                Cache::forever($kunci.'_terakhir', $data);

                return $data;
            }

            return Cache::get($kunci.'_terakhir');
        });
    }

    private function panggilSaatIni(float $lat, float $lon): ?array
    {
        try {
            $res = Http::timeout(10)->get(self::URL, [
                'latitude' => $lat,
                'longitude' => $lon,
                'current' => 'us_aqi,pm2_5,pm10',
                'timezone' => 'Asia/Jakarta',
            ]);
        } catch (\Throwable $e) {
            Log::error('Open-Meteo gagal: '.$e->getMessage());

            return null;
        }

        if (! $res->successful()) {
            Log::error('Open-Meteo status '.$res->status().': '.$res->body());

            return null;
        }

        $c = $res->json('current');
        if (! $c || ! isset($c['us_aqi'])) {
            return null;
        }

        $aqi = (int) round($c['us_aqi']);

        return [
            'aqi' => $aqi,
            'pm25' => $c['pm2_5'] ?? null,
            'pm10' => $c['pm10'] ?? null,
            'kategori' => $this->kategori($aqi),
            'waktu' => $c['time'] ?? null,
        ];
    }

    private function panggilTren(float $lat, float $lon): ?array
    {
        try {
            $res = Http::timeout(10)->get(self::URL, [
                'latitude' => $lat,
                'longitude' => $lon,
                'hourly' => 'us_aqi',
                'forecast_days' => 4,
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
                $perJam[] = ['jam' => sprintf('%02d', $jam), 'aqi' => (int) round($aqi[$i])];
            }
        }

        $harian = [];
        foreach ($perHari as $tgl => $nilai) {
            if ($tgl > $hariIni) {
                $harian[$tgl] = (int) round(array_sum($nilai) / count($nilai));
            }
        }

        return ['per_jam' => $perJam, 'harian' => array_slice($harian, 0, 3, true)];
    }

    /**
     * Kategori berdasarkan skala US AQI.
     */
    public function kategori(int $aqi): string
    {
        return match (true) {
            $aqi <= 50 => 'Baik',
            $aqi <= 100 => 'Sedang',
            $aqi <= 150 => 'Tidak Sehat bagi Kelompok Sensitif',
            $aqi <= 200 => 'Tidak Sehat',
            $aqi <= 300 => 'Sangat Tidak Sehat',
            default => 'Berbahaya',
        };
    }

    /**
     * Label + kelas warna Tailwind untuk tampilan: [label, warna angka, warna lencana].
     */
    public function level(int $aqi): array
    {
        $label = $this->kategori($aqi);

        return match (true) {
            $aqi <= 50 => [$label, 'text-teal-600',  'bg-teal-100 text-teal-700'],
            $aqi <= 100 => [$label, 'text-amber-500', 'bg-amber-100 text-amber-700'],
            $aqi <= 150 => [$label, 'text-orange-400', 'bg-orange-100 text-orange-700'],
            $aqi <= 200 => [$label, 'text-orange-500', 'bg-orange-100 text-orange-700'],
            $aqi <= 300 => [$label, 'text-rose-600',  'bg-rose-100 text-rose-700'],
            default => [$label, 'text-rose-800',  'bg-rose-200 text-rose-900'],
        };
    }
}
