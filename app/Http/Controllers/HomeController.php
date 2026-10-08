<?php

namespace App\Http\Controllers;

use App\Services\CuacaService;
use App\Services\RecommendationService;
use App\Services\TitikPanasService;
use App\Services\UdaraService;
use Carbon\Carbon;
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

        $infoKota = $daftar[$kota] + ['key' => $kota];
        $lat = $infoKota['lat'];
        $lon = $infoKota['lon'];

        // 1) Ambil data dari service (null = API gagal dan belum ada data tersimpan)
        $udara = $udaraSvc->ambil($lat, $lon);
        $cuaca = $cuacaSvc->ambil($lat, $lon);
        $prakiraanCuaca = $cuacaSvc->prakiraan($lat, $lon);
        $titik = $panasSvc->dekatDari($lat, $lon);
        $trenData = $udaraSvc->tren($lat, $lon);

        $statusApi = [
            'udara' => $udara !== null,
            'cuaca' => $cuaca !== null,
            'titik_panas' => $titik !== null,
            'tren' => $trenData !== null,
        ];

        // 2) Data utama (udara/cuaca) gagal total -> data contoh + penanda offline
        $offline = ! $statusApi['udara'] || ! $statusApi['cuaca'];
        $udara ??= $udaraSvc->contoh();
        $cuaca ??= $cuacaSvc->contoh();
        $titik ??= [];

        $tren = $trenData['per_jam'] ?? [];
        $ringkasan = $udaraSvc->ringkasan($tren, $udara['aqi']);
        $terdekat = $titik[0] ?? null;

        return view('home', [
            'kota' => $kota,
            'infoKota' => $infoKota,       // nama, jenis, lat, lon, key
            'daftarKota' => $daftar,
            'tanggalHariIni' => now('Asia/Jakarta')->locale('id')->translatedFormat('l, j F Y'),
            'updatedAt' => $this->label($udara['updated_at']),
            'offline' => $offline,
            'statusApi' => $statusApi,

            'udara' => $udara,
            'cuaca' => $cuaca,

            'titik' => $titik,
            'jumlahTitik' => count($titik),
            'terdekat' => $terdekat,
            'titikTerbaru' => $titik ? $this->label(max(array_column($titik, 'datetime'))) : null,

            'tren' => $tren,           // [['time' => '08:00', 'aqi' => 152, 'is_now' => true], ...]
            'ringkasan' => $ringkasan,      // terburuk, terbaik, waktu_terbaik, kecenderungan
            'prakiraan' => $this->gabungPrakiraan($prakiraanCuaca ?? [], $trenData['harian'] ?? [], $udaraSvc),

            'saran' => $offline
                ? $ai->aturan($udara['aqi'], $terdekat, $cuaca, $ringkasan)
                : $ai->saran($kota, $udara, $cuaca, $terdekat, $ringkasan),
            'judulSaran' => $ai->judul($udara['aqi']),
            'alasanSaran' => $ai->alasan($udara, $ringkasan),
        ]);
    }

    // "08 Oktober 2026 · 07.45 WIB"
    private function label(?string $waktu): ?string
    {
        if (! $waktu) {
            return null;
        }
        $c = Carbon::parse($waktu, 'Asia/Jakarta')->locale('id');

        return $c->translatedFormat('d F Y').' · '.$c->format('H.i').' WIB';
    }

    // Gabungkan prakiraan cuaca (suhu, kondisi) dengan rata-rata AQI harian
    private function gabungPrakiraan(array $cuacaHarian, array $aqiHarian, UdaraService $udaraSvc): array
    {
        $peta = [];
        foreach ($cuacaHarian as $c) {
            $peta[$c['date']] = $c;
        }

        $tanggal = $peta ? array_keys($peta) : array_keys($aqiHarian);
        $hasil = [];

        foreach ($tanggal as $tgl) {
            $aqi = $aqiHarian[$tgl] ?? null;
            $hasil[] = [
                'date' => $tgl,
                'day_label' => $peta[$tgl]['day_label'] ?? Carbon::parse($tgl, 'Asia/Jakarta')->locale('id')->translatedFormat('l, j M'),
                'condition' => $peta[$tgl]['condition'] ?? null,
                'temp_max' => $peta[$tgl]['temp_max'] ?? null,
                'temp_min' => $peta[$tgl]['temp_min'] ?? null,
                'aqi' => $aqi,
                'aqi_level' => $udaraSvc->levelAqi($aqi),
                'aqi_level_key' => $udaraSvc->levelKey($aqi),
            ];
        }

        return $hasil;
    }
}
