<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RecommendationService
{
    /**
     * Saran singkat untuk warga.
     * Urutan: Gemini (kalau GEMINI_API_KEY diisi) → Claude (kalau ANTHROPIC_API_KEY diisi)
     * → saran berbasis aturan. Kalau AI gagal, otomatis jatuh ke aturan.
     */
    public function saran(string $kota, array $udara, ?array $cuaca, ?array $terdekat): string
    {
        $aqi = $udara['aqi'];
        $cadangan = $this->aturan($aqi, $terdekat, $cuaca);

        $gemini = config('services.gemini.key');
        $claude = config('services.anthropic.key');

        if (! $gemini && ! $claude) {
            return $cadangan;
        }

        $jarak = $terdekat ? min(3, intdiv((int) $terdekat['jarak_km'], 25)) : 'x';
        $kunci = "saran_{$kota}_".intdiv($aqi, 25)."_{$jarak}";

        if ($ada = Cache::get($kunci)) {
            return $ada;
        }

        $prompt = $this->buatPrompt($udara, $cuaca, $terdekat);
        $teks = $gemini ? $this->tanyaGemini($gemini, $prompt) : $this->tanyaClaude($claude, $prompt);

        if ($teks) {
            Cache::put($kunci, $teks, 1800);

            return $teks;
        }

        return $cadangan;
    }

    /**
     * Saran berbasis aturan (tanpa AI, tanpa biaya).
     */
    public function aturan(int $aqi, ?array $terdekat = null, ?array $cuaca = null): string
    {
        $teks = match (true) {
            $aqi <= 50 => 'Udara bersih hari ini. Aman untuk beraktivitas di luar rumah.',
            $aqi <= 100 => 'Kualitas udara cukup baik. Kelompok sensitif sebaiknya tidak terlalu lama di luar ruangan.',
            $aqi <= 150 => 'Anak-anak, lansia, dan penderita asma sebaiknya mengurangi aktivitas luar ruangan dan memakai masker.',
            $aqi <= 200 => 'Udara tidak sehat. Pakai masker KN95 jika keluar rumah, tutup jendela rapat, dan tunda olahraga di luar ruangan.',
            default => 'Udara sangat tidak sehat. Sebaiknya tetap di dalam ruangan, pakai masker KN95 bila terpaksa keluar, dan hubungi tenaga kesehatan jika sesak napas.',
        };

        if ($aqi > 100 && $cuaca && ($cuaca['jarak_pandang'] ?? 10) < 5) {
            $teks .= " Jarak pandang hanya sekitar {$cuaca['jarak_pandang']} km, jadi berkendaralah lebih hati-hati.";
        }

        if ($terdekat && $terdekat['jarak_km'] <= 50) {
            $teks .= " Ada titik panas sekitar {$terdekat['jarak_km']} km dari lokasi Anda, pantau terus perkembangannya.";
        }

        return $teks;
    }

    private function buatPrompt(array $udara, ?array $cuaca, ?array $terdekat): string
    {
        $data = "AQI (skala US): {$udara['aqi']} ({$udara['kategori']}); PM2.5: ".($udara['pm25'] ?? '-').' µg/m³. ';
        if ($cuaca) {
            $data .= "Angin {$cuaca['angin']} km/jam dari arah ".($cuaca['arah'] ?? '-')
                .'; jarak pandang '.($cuaca['jarak_pandang'] ?? '-').' km. ';
        }
        $data .= $terdekat
            ? "Titik panas terdekat {$terdekat['jarak_km']} km dari lokasi pengguna."
            : 'Tidak ada titik panas terdeteksi dalam 24 jam terakhir.';

        return 'Kamu asisten kualitas udara untuk warga Riau. Berdasarkan data berikut, tulis saran 2-3 kalimat '
            .'dalam bahasa Indonesia yang ramah dan mudah dipahami masyarakat umum. Bahas masker '
            .'(KN95 jika AQI di atas 100), jendela, dan aktivitas luar ruangan. Jangan menambah angka atau fakta '
            .'yang tidak ada di data, jangan menyimpulkan penyebab asap, jangan memberi diagnosis medis, '
            .'dan jangan memakai format markdown. Data: '.$data;
    }

    private function tanyaGemini(string $key, string $prompt): ?string
    {
        $model = config('services.gemini.model', 'gemini-3.5-flash-lite');

        try {
            $res = Http::withHeaders(['x-goog-api-key' => $key])
                ->timeout(15)
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
            ])->timeout(15)->post('https://api.anthropic.com/v1/messages', [
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
