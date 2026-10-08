<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RecommendationService
{
    /**
     * Saran singkat (paragraf) untuk warga.
     * Urutan: Gemini (kalau GEMINI_API_KEY diisi) → Claude (kalau ANTHROPIC_API_KEY diisi)
     * → saran berbasis aturan. Kalau AI gagal, otomatis jatuh ke aturan. Tanpa database.
     */
    public function saran(string $kota, array $udara, ?array $cuaca, ?array $terdekat, ?array $ringkasan = null): string
    {
        $aqi = $udara['aqi'];
        $cadangan = $this->aturan($aqi, $terdekat, $cuaca, $ringkasan);

        $gemini = config('services.gemini.key');
        $claude = config('services.anthropic.key');

        if (! $gemini && ! $claude) {
            return $cadangan;
        }

        $jarak = $terdekat ? min(3, intdiv((int) $terdekat['distance'], 25)) : 'x';
        $kec = $ringkasan['kecenderungan'] ?? 'x';
        $kunci = "saran_v2_{$kota}_".intdiv($aqi, 25)."_{$jarak}_{$kec}";

        if ($ada = Cache::get($kunci)) {
            return $ada;
        }

        // AI baru saja gagal: langsung pakai saran aturan, jangan menunggu timeout lagi (jeda 5 menit)
        if (Cache::has('saran_ai_gagal')) {
            return $cadangan;
        }

        $prompt = $this->buatPrompt($udara, $cuaca, $terdekat, $ringkasan);
        $teks = $gemini ? $this->tanyaGemini($gemini, $prompt) : $this->tanyaClaude($claude, $prompt);

        if ($teks) {
            Cache::put($kunci, $teks, 1800);

            return $teks;
        }

        Cache::put('saran_ai_gagal', true, 300);

        return $cadangan;
    }

    /**
     * Judul singkat untuk kartu rekomendasi (selalu berbasis aturan, deterministik).
     */
    public function judul(?int $aqi): string
    {
        return match (true) {
            $aqi === null => 'Data kualitas udara belum tersedia.',
            $aqi <= 50 => 'Udara bersih, aman untuk beraktivitas.',
            $aqi <= 100 => 'Udara cukup baik, tetap perhatikan jika Anda sensitif.',
            $aqi <= 150 => 'Kelompok sensitif sebaiknya kurangi aktivitas di luar.',
            $aqi <= 200 => 'Sebaiknya kurangi aktivitas di luar ruangan.',
            default => 'Sebaiknya tetap di dalam ruangan.',
        };
    }

    /**
     * Tiga poin ringkas untuk bagian "Lihat alasan". Semua dari data aktual, bukan dari AI.
     */
    public function alasan(array $udara, ?array $ringkasan = null): array
    {
        $wt = $ringkasan['waktu_terbaik'] ?? null;

        return [
            ['label' => 'Kondisi udara', 'nilai' => "AQI {$udara['aqi']} · {$udara['level']}"],
            ['label' => 'Waktu yang lebih baik', 'nilai' => $wt
                ? 'Sekitar pukul '.substr($wt['time'], 0, 2).'.00 WIB'
                : 'Belum ada perbaikan berarti hari ini'],
            ['label' => 'Untuk keluar rumah', 'nilai' => $udara['aqi'] > 100
                ? 'Kurangi durasi dan gunakan masker'
                : 'Aman, tetap perhatikan kondisi'],
        ];
    }

    /**
     * Saran berbasis aturan (tanpa AI, tanpa biaya). Dipakai juga sebagai cadangan.
     */
    public function aturan(?int $aqi, ?array $terdekat = null, ?array $cuaca = null, ?array $ringkasan = null): string
    {
        if ($aqi === null) {
            return 'Data kualitas udara belum tersedia. Coba lagi beberapa saat lagi.';
        }

        $teks = match (true) {
            $aqi <= 50 => 'Udara bersih hari ini. Aman untuk beraktivitas di luar rumah.',
            $aqi <= 100 => 'Kualitas udara cukup baik. Kelompok sensitif sebaiknya tidak terlalu lama di luar ruangan.',
            $aqi <= 150 => 'Anak-anak, lansia, dan penderita asma sebaiknya mengurangi aktivitas luar ruangan dan memakai masker.',
            $aqi <= 200 => 'Kurangi aktivitas di luar ruangan karena kualitas udara saat ini tidak sehat. Pakai masker KN95 jika keluar rumah dan tutup jendela rapat.',
            default => 'Udara sangat tidak sehat. Sebaiknya tetap di dalam ruangan, pakai masker KN95 bila terpaksa keluar, dan hubungi tenaga kesehatan jika sesak napas.',
        };

        $visibility = $cuaca['visibility'] ?? null;
        if ($aqi > 100 && $visibility !== null && $visibility < 5) {
            $teks .= " Jarak pandang hanya sekitar {$visibility} km, jadi berkendaralah lebih hati-hati.";
        }

        if ($terdekat && $terdekat['distance'] <= 50) {
            $teks .= " Ada titik panas sekitar {$terdekat['distance']} km ke arah {$terdekat['direction']}, pantau terus perkembangannya.";
        }

        $kec = $ringkasan['kecenderungan'] ?? null;
        $wt = $ringkasan['waktu_terbaik'] ?? null;
        if ($kec === 'membaik' && $wt) {
            $teks .= ' Udara diperkirakan lebih baik sekitar pukul '.substr($wt['time'], 0, 2).'.00 WIB.';
        } elseif ($kec === 'memburuk') {
            $teks .= ' Kondisi diperkirakan memburuk dalam beberapa jam ke depan, jadi selesaikan urusan di luar lebih awal.';
        }

        return $teks;
    }

    private function buatPrompt(array $udara, ?array $cuaca, ?array $terdekat, ?array $ringkasan): string
    {
        $data = "AQI (skala US) kategori {$udara['level']}; PM2.5: ".($udara['pm25'] ?? '-').' µg/m³. ';

        if ($cuaca) {
            $data .= 'Kondisi cuaca '.($cuaca['condition'] ?? '-')
                ."; angin {$cuaca['wind_speed']} km/jam dari arah ".($cuaca['wind_direction'] ?? '-')
                .'; jarak pandang '.($cuaca['visibility'] ?? '-').' km. ';
        }

        $data .= $terdekat
            ? "Titik panas terdekat {$terdekat['distance']} km ke arah {$terdekat['direction']} dari pusat kota. "
            : 'Tidak ada titik panas terdeteksi dalam 24 jam terakhir. ';

        $kec = $ringkasan['kecenderungan'] ?? null;
        if ($kec && $kec !== 'tidak-tersedia') {
            $data .= "Kecenderungan AQI hari ini: {$kec}. ";
            if (! empty($ringkasan['waktu_terbaik'])) {
                $data .= 'Waktu dengan udara lebih baik sekitar pukul '.substr($ringkasan['waktu_terbaik']['time'], 0, 2).'.00 WIB. ';
            }
        }

        return 'Kamu asisten kualitas udara untuk warga Riau. Berdasarkan data berikut, tulis saran 2-3 kalimat '
            .'dalam bahasa Indonesia yang ramah dan mudah dipahami masyarakat umum. Bahas masker '
            .'(KN95 jika kategori tidak sehat), jendela, dan aktivitas luar ruangan. Jangan menambah angka atau fakta '
            .'yang tidak ada di data, jangan menyebut angka AQI atau PM2.5 secara persis (cukup sebut kategorinya), '
            .'jangan menyimpulkan penyebab asap, jangan memberi diagnosis medis, dan jangan memakai format markdown. '
            .'Data: '.$data;
    }

    private function tanyaGemini(string $key, string $prompt): ?string
    {
        $model = config('services.gemini.model', 'gemini-2.5-flash-lite');

        try {
            $res = Http::withHeaders(['x-goog-api-key' => $key])
                ->timeout(7)->connectTimeout(3)
                ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent", [
                    'contents' => [['parts' => [['text' => $prompt]]]],
                    'generationConfig' => ['maxOutputTokens' => 800, 'temperature' => 0.4],
                ])->throw();

            return trim((string) $res->json('candidates.0.content.parts.0.text')) ?: null;
        } catch (\Throwable $e) {
            Log::error('Gemini gagal: '.str_replace($key, '***', $e->getMessage()));

            return null;
        }
    }

    private function tanyaClaude(string $key, string $prompt): ?string
    {
        try {
            $res = Http::withHeaders([
                'x-api-key' => $key,
                'anthropic-version' => '2023-06-01',
            ])->timeout(7)->connectTimeout(3)->post('https://api.anthropic.com/v1/messages', [
                'model' => 'claude-haiku-4-5-20251001',
                'max_tokens' => 300,
                'messages' => [['role' => 'user', 'content' => $prompt]],
            ])->throw();

            return trim((string) $res->json('content.0.text')) ?: null;
        } catch (\Throwable $e) {
            Log::error('Claude gagal: '.str_replace($key, '***', $e->getMessage()));

            return null;
        }
    }
}
