<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class UdaraService
{
    /**
     * Ambil data udara dari Open-Meteo, dirapikan sesuai kontrak.
     * Mengembalikan null kalau API gagal.
     */
    public function ambil(float $lat, float $lon): ?array
    {
        $kunci = 'udara_' . round($lat, 2) . '_' . round($lon, 2);

        return Cache::remember($kunci, 600, function () use ($lat, $lon) {
            try {
                $res = Http::timeout(10)->get('https://air-quality-api.open-meteo.com/v1/air-quality', [
                    'latitude'  => $lat,
                    'longitude' => $lon,
                    'current'   => 'us_aqi,pm2_5,pm10',
                    'timezone'  => 'Asia/Jakarta',
                ]);
          } catch (\Throwable $e) {
    Log::error('Open-Meteo gagal: ' . $e->getMessage());
    return null;
}

if (!$res->successful()) {
    Log::error('Open-Meteo status ' . $res->status() . ': ' . $res->body());
    return null;
}

            $c = $res->json('current');
            if (!$c || !isset($c['us_aqi'])) {
                return null;
            }

            $aqi = (int) round($c['us_aqi']);

            return [
                'aqi'      => $aqi,
                'pm25'     => $c['pm2_5'] ?? null,
                'pm10'     => $c['pm10'] ?? null,
                'kategori' => $this->kategori($aqi),
                'waktu'    => $c['time'] ?? null,
            ];
        });
    }

    /**
     * Kategori berdasarkan skala US AQI.
     */
    public function kategori(int $aqi): string
    {
        return match (true) {
            $aqi <= 50  => 'Baik',
            $aqi <= 100 => 'Sedang',
            $aqi <= 150 => 'Tidak Sehat bagi Kelompok Sensitif',
            $aqi <= 200 => 'Tidak Sehat',
            $aqi <= 300 => 'Sangat Tidak Sehat',
            default     => 'Berbahaya',
        };
    }
}
