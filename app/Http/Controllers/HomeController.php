<?php

namespace App\Http\Controllers;

use App\Services\CuacaService;
use App\Services\RecommendationService;
use App\Services\TitikPanasService;
use App\Services\UdaraService;
use Illuminate\Http\Request;

class HomeController extends Controller
{
    public function __invoke(
        Request $request,
        UdaraService $udaraSvc,
        CuacaService $cuacaSvc,
        TitikPanasService $panasSvc,
        RecommendationService $ai
    ) {
        $daftar = config('siaganapas.cities');
        $kota = $request->query('kota', session('kota', config('siaganapas.default_city')));
        abort_unless(isset($daftar[$kota]), 404);
        session(['kota' => $kota]);

        $lat = $daftar[$kota]['lat'];
        $lon = $daftar[$kota]['lon'];

        $udara = $udaraSvc->ambil($lat, $lon);
        $cuaca = $cuacaSvc->ambil($lat, $lon);

        // Kalau API gagal dan belum ada data tersimpan, tampilkan data contoh + penanda
        $offline = ! $udara || ! $cuaca;
        $udara ??= $this->udaraContoh();
        $cuaca ??= $this->cuacaContoh();

        $titik = $panasSvc->dekatDari($lat, $lon); // null = gagal, [] = tidak ada titik
        $tren = $udaraSvc->tren($lat, $lon);
        $terdekat = $titik[0] ?? null;
        $perJam = $tren['per_jam'] ?? [];
        $jamIni = (int) now('Asia/Jakarta')->format('G');
        $terbaik = collect($perJam)->filter(fn ($j) => (int) $j['jam'] >= $jamIni)->sortBy('aqi')->first();
        $waktuTerbaik = ($terbaik && $terbaik['aqi'] <= $udara['aqi'] - 20) ? $terbaik : null;
        $sisa = collect($tren['per_jam'] ?? [])->filter(fn ($j) => (int) $j['jam'] >= $jamIni);
        $puncak = $sisa->max('aqi');

        $kecenderungan = match (true) {
            $waktuTerbaik !== null => 'membaik',
            $puncak !== null && $puncak >= $udara['aqi'] + 10 => 'memburuk',
            default => 'stabil',
        };

        return view('home', [
            'daftarKota' => $daftar,
            'kota' => $kota,
            'infoKota' => $daftar[$kota],
            'udara' => $udara,                          // aqi, pm25, pm10, kategori, waktu
            'level' => $udaraSvc->level($udara['aqi']), // [label, warna angka, warna lencana]
            'cuaca' => $cuaca,                          // suhu, terasa, lembap, angin, arah, jarak_pandang
            'titik' => $titik ?? [],                    // lat, lon, keyakinan, frp, jarak_km, jam_wib
            'jumlahTitik' => count($titik ?? []),
            'terdekat' => $terdekat,
            'prakiraan' => $tren['harian'] ?? [],           // ['2026-10-07' => 138, ...]
            'perJam' => $tren['per_jam'] ?? [],          // [['jam' => '08', 'aqi' => 152], ...]
            'waktuTerbaik' => $waktuTerbaik,
            'kecenderungan' => $kecenderungan,
            'saran' => $offline
                ? $ai->aturan($udara['aqi'], $terdekat)
                : $ai->saran($kota, $udara, $cuaca, $terdekat),
            'offline' => $offline,
        ]);
    }

    private function udaraContoh(): array
    {
        return ['aqi' => 152, 'pm25' => 58.0, 'pm10' => 70.0, 'kategori' => 'Tidak Sehat', 'waktu' => null];
    }

    private function cuacaContoh(): array
    {
        return ['suhu' => 33, 'terasa' => 38, 'lembap' => 64, 'angin' => 9, 'arah' => 'timur laut', 'jarak_pandang' => 3.0];
    }
}
