<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CuacaService
{
    private const URL = 'https://api.open-meteo.com/v1/forecast';

    /**
     * Cuaca saat ini (Open-Meteo, tanpa API key).
     */
    public function ambil(float $lat, float $lon): ?array
    {
        $c = $this->mentah($lat, $lon)['current'] ?? null;

        if (! $c || ! isset($c['temperature_2m'])) {
            return null;
        }

        $derajat = isset($c['wind_direction_10m']) ? (float) $c['wind_direction_10m'] : null;
        $lembap = isset($c['relative_humidity_2m']) ? (int) round($c['relative_humidity_2m']) : null;

        return $this->susun(
            (int) round($c['temperature_2m']),
            isset($c['apparent_temperature']) ? (int) round($c['apparent_temperature']) : null,
            $lembap,
            isset($c['wind_speed_10m']) ? (int) round($c['wind_speed_10m']) : null,
            $derajat,
            isset($c['visibility']) ? round($c['visibility'] / 1000, 1) : null,
            $c['weather_code'] ?? null
        );
    }

    /**
     * Prakiraan harian: hari ini + 2 hari ke depan.
     * Tiap item: date, day_label, condition, temp_max, temp_min.
     */
    public function prakiraan(float $lat, float $lon): ?array
    {
        $d = $this->mentah($lat, $lon)['daily'] ?? null;

        if (! $d || empty($d['time'])) {
            return null;
        }

        $hasil = [];
        foreach ($d['time'] as $i => $tgl) {
            $hasil[] = [
                'date' => $tgl,
                'day_label' => Carbon::parse($tgl, 'Asia/Jakarta')->locale('id')->translatedFormat('l, j M'),
                'condition' => $this->kondisi($d['weather_code'][$i] ?? null),
                'temp_max' => isset($d['temperature_2m_max'][$i]) ? (int) round($d['temperature_2m_max'][$i]) : null,
                'temp_min' => isset($d['temperature_2m_min'][$i]) ? (int) round($d['temperature_2m_min'][$i]) : null,
            ];
        }

        return $hasil;
    }

    /**
     * Data contoh HANYA untuk saat API gagal dan belum ada data tersimpan (pasangkan dengan $offline).
     */
    public function contoh(): array
    {
        return $this->susun(33, 38, 64, 9, 45.0, 3.0, null);
    }

    // Satu panggilan API untuk cuaca saat ini + prakiraan harian, dengan cadangan data terakhir
    private function mentah(float $lat, float $lon): ?array
    {
        $kunci = 'cuaca_v2_'.round($lat, 2).'_'.round($lon, 2);

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
            $res = Http::timeout(10)->get(self::URL, [
                'latitude' => $lat,
                'longitude' => $lon,
                'current' => 'temperature_2m,apparent_temperature,relative_humidity_2m,weather_code,wind_speed_10m,wind_direction_10m,visibility',
                'daily' => 'weather_code,temperature_2m_max,temperature_2m_min',
                'forecast_days' => 3,
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

        $current = $res->json('current');
        if (! $current) {
            return null;
        }

        return ['current' => $current, 'daily' => $res->json('daily')];
    }

    private function susun(int $suhu, ?int $terasa, ?int $lembap, ?int $angin, ?float $derajat, ?float $jarakPandang, ?int $kode): array
    {
        return [
            'temperature' => $suhu,
            'feels_like' => $terasa,
            'humidity' => $lembap,
            'humidity_label' => $this->labelLembap($lembap),
            'wind_speed' => $angin,                 // km/jam
            'wind_direction' => $this->arah($derajat),  // arah ANGIN BERASAL, mis. "timur laut"
            'wind_degree' => $derajat !== null ? (int) round($derajat) : null,
            'visibility' => $jarakPandang,          // km
            'condition' => $this->kondisi($kode),
            'weather_code' => $kode,
        ];
    }

    private function labelLembap(?int $h): ?string
    {
        if ($h === null) {
            return null;
        }

        return match (true) {
            $h <= 45 => 'Kering',
            $h <= 70 => 'Cukup lembap',
            default => 'Sangat lembap',
        };
    }

    private function arah(?float $derajat): ?string
    {
        if ($derajat === null) {
            return null;
        }
        $nama = ['utara', 'timur laut', 'timur', 'tenggara', 'selatan', 'barat daya', 'barat', 'barat laut'];

        return $nama[(int) round($derajat / 45) % 8];
    }

    // Kode cuaca WMO → teks Indonesia
    private function kondisi(?int $kode): ?string
    {
        if ($kode === null) {
            return null;
        }

        return match (true) {
            $kode === 0 => 'Cerah',
            $kode === 1 => 'Cerah berawan',
            $kode === 2 => 'Berawan sebagian',
            $kode === 3 => 'Berawan',
            in_array($kode, [45, 48], true) => 'Berkabut',
            in_array($kode, [51, 53, 55, 56, 57], true) => 'Gerimis',
            in_array($kode, [61, 80], true) => 'Hujan ringan',
            in_array($kode, [63, 81], true) => 'Hujan sedang',
            in_array($kode, [65, 66, 67, 82], true) => 'Hujan lebat',
            in_array($kode, [95, 96, 99], true) => 'Badai petir',
            default => 'Tidak diketahui',
        };
    }
}
