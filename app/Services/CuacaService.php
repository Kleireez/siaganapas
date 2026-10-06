<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CuacaService
{
    /**
     * Cuaca saat ini dari Open-Meteo (tanpa API key).
     * Hasil: suhu, terasa, lembap, angin (km/jam), arah, jarak_pandang (km, bisa null).
     */
    public function ambil(float $lat, float $lon): ?array
    {
        $kunci = 'cuaca_'.round($lat, 2).'_'.round($lon, 2);

        return Cache::remember($kunci, 600, function () use ($lat, $lon, $kunci) {
            $data = $this->panggil($lat, $lon);

            if ($data) {
                Cache::forever($kunci.'_terakhir', $data);

                return $data;
            }

            return Cache::get($kunci.'_terakhir');
        });
    }

    private function panggil(float $lat, float $lon): ?array
    {
        try {
            $res = Http::timeout(10)->get('https://api.open-meteo.com/v1/forecast', [
                'latitude' => $lat,
                'longitude' => $lon,
                'current' => 'temperature_2m,apparent_temperature,relative_humidity_2m,wind_speed_10m,wind_direction_10m,visibility',
                'timezone' => 'Asia/Jakarta',
            ]);
        } catch (\Throwable $e) {
            Log::error('Open-Meteo cuaca gagal: '.$e->getMessage());

            return null;
        }

        if (! $res->successful()) {
            Log::error('Open-Meteo cuaca status '.$res->status().': '.$res->body());

            return null;
        }

        $c = $res->json('current');
        if (! $c || ! isset($c['temperature_2m'])) {
            return null;
        }

        return [
            'suhu' => (int) round($c['temperature_2m']),
            'terasa' => (int) round($c['apparent_temperature'] ?? $c['temperature_2m']),
            'lembap' => (int) round($c['relative_humidity_2m'] ?? 0),
            'angin' => (int) round($c['wind_speed_10m'] ?? 0),
            'arah' => $this->arah($c['wind_direction_10m'] ?? null),
            'jarak_pandang' => isset($c['visibility']) ? round($c['visibility'] / 1000, 1) : null,
        ];
    }

    // Arah ANGIN BERASAL (derajat → mata angin)
    private function arah(?float $derajat): ?string
    {
        if ($derajat === null) {
            return null;
        }
        $nama = ['utara', 'timur laut', 'timur', 'tenggara', 'selatan', 'barat daya', 'barat', 'barat laut'];

        return $nama[(int) round($derajat / 45) % 8];
    }
}
