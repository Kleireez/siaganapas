# SiagaNapas

**Pantau udara dan titik panas di sekitar Anda, lalu ketahui apa yang sebaiknya dilakukan.**

SiagaNapas adalah aplikasi web informasi lingkungan untuk warga Riau. Data kualitas udara, cuaca, dan titik panas satelit diambil dari sumber terbuka, diolah, lalu diterjemahkan menjadi informasi yang mudah dipahami dan saran tindakan yang jelas.

- **Web (live):** [ISI: https://alamat-web-kamu]
- **Subtema lomba:** Sustainable Environment & Green Technology
- **Lomba:** Web Development Competition FESTRA 2026
- **Tim:** [ISI: nama ketua, anggota, kampus]

## Masalah yang diselesaikan

Data kualitas udara dan titik panas sudah tersedia secara terbuka, tetapi bentuknya angka, koordinat, dan istilah teknis yang sulit dipahami warga. Akibatnya orang sulit menjawab pertanyaan sederhana: _"Apakah aman keluar rumah hari ini, dan seberapa dekat titik panas dari tempat saya?"_

SiagaNapas menjawabnya dengan satu halaman: kondisi udara, tren hari ini, peta titik panas, dan saran tindakan.

## Fitur

- **Kondisi udara terkini**: AQI, kategori, PM2.5, PM10, dan gas lain, lengkap dengan skala warna.
- **Tren AQI hari ini** per 2 jam, serta jam terbaik dan terburuk.
- **Prakiraan 3 hari**: cuaca dan rata-rata AQI harian.
- **Peta titik panas** (OpenStreetMap) dengan tingkat kepercayaan: tinggi, sedang, rendah.
- **Lokasi saya**: menghitung jarak dan arah titik panas terdekat dari posisi pengguna. Perhitungan terjadi di browser, koordinat tidak dikirim ke server.
- **Saran tindakan** per kondisi udara. Saran dibuat AI (Gemini atau Claude) jika tersedia, dan otomatis memakai aturan baku jika AI gagal atau tidak dikonfigurasi.
- **Panduan per kelompok** (anak-anak, lansia, dan lainnya).
- **12 kota/kabupaten di Riau** lewat dropdown.
- **Transparansi data**: halaman menampilkan sumber dan waktu pembaruan tiap data.
- **Mobile-friendly** dan tetap tampil dengan data terakhir atau data contoh saat API gagal (ditandai jelas sebagai "Data contoh").

## Sumber data

| Data                 | Sumber                                                                   | Pembaruan                                        |
| -------------------- | ------------------------------------------------------------------------ | ------------------------------------------------ |
| Kualitas udara, tren | [Open-Meteo Air Quality](https://open-meteo.com/en/docs/air-quality-api) | tiap jam                                         |
| Cuaca, prakiraan     | [Open-Meteo Forecast](https://open-meteo.com/en/docs)                    | tiap jam                                         |
| Titik panas          | [NASA FIRMS](https://firms.modaps.eosdis.nasa.gov/) (VIIRS SNPP NRT)     | beberapa kali sehari, mengikuti lintasan satelit |
| Peta                 | OpenStreetMap + Leaflet                                                  | -                                                |

## Catatan batas data

Aplikasi ini sengaja berhati-hati dalam menyampaikan informasi:

- **Titik panas bukan berarti pasti kebakaran.** Satelit mendeteksi area bersuhu tinggi.
- Titik panas **tidak disebut sebagai penyebab asap**.
- Nama kabupaten/kota pada titik panas adalah **perkiraan** berdasarkan pusat wilayah terdekat, bukan batas administrasi resmi.
- Data satelit bisa terlambat beberapa jam dan tidak selalu menangkap setiap kejadian.
- Jarak dihitung dari pusat kota terpilih, atau dari lokasi pengguna jika fitur Lokasi saya dipakai (hanya dari titik yang ditampilkan di peta).
- Nilai yang tidak disediakan API ditampilkan sebagai "tidak tersedia", tidak dikarang.

## Teknologi

- Laravel 12, PHP 8.2+
- Blade, CSS dan JavaScript tanpa framework front-end
- Leaflet 1.9 dan OpenStreetMap
- Tanpa database. Data diambil dari API lalu disimpan sementara di cache (udara dan cuaca 10 menit, tren 30 menit, titik panas 15 menit, saran AI 30 menit), dengan cadangan data sukses terakhir jika API gagal.

## Menjalankan di komputer lokal

Prasyarat: PHP 8.2+, Composer.

```bash
git clone [ISI: URL repo GitHub kamu]
cd [ISI: nama folder repo]

composer install
cp .env.example .env
php artisan key:generate
```

Isi variabel di `.env` (lihat tabel di bawah), lalu:

```bash
php artisan serve
```

Buka http://127.0.0.1:8000. Untuk memilih kota: `http://127.0.0.1:8000/?kota=kampar`.

## Variabel lingkungan

| Variabel            | Wajib             | Keterangan                                                                  |
| ------------------- | ----------------- | --------------------------------------------------------------------------- |
| `APP_KEY`           | ya                | dibuat lewat `php artisan key:generate`                                     |
| `FIRMS_MAP_KEY`     | untuk titik panas | key gratis dari NASA FIRMS (https://firms.modaps.eosdis.nasa.gov/api/area/) |
| `GEMINI_API_KEY`    | tidak             | jika kosong dan `ANTHROPIC_API_KEY` juga kosong, saran memakai aturan baku  |
| `ANTHROPIC_API_KEY` | tidak             | cadangan jika Gemini tidak diisi                                            |

Open-Meteo tidak memerlukan API key.

Pengaturan yang disarankan untuk produksi:

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://alamat-web-kamu
SESSION_DRIVER=cookie
CACHE_STORE=file
QUEUE_CONNECTION=sync
LOG_CHANNEL=stderr
```

## Struktur singkat

```
app/Http/Controllers/HomeController.php    mengumpulkan data dan mengirim ke view
app/Services/UdaraService.php              kualitas udara, tren, ringkasan
app/Services/CuacaService.php              cuaca dan prakiraan
app/Services/TitikPanasService.php         titik panas NASA FIRMS, jarak, arah
app/Services/RecommendationService.php     saran (AI atau aturan)
config/siaganapas.php                      daftar kota/kabupaten
resources/views/home.blade.php             tampilan
```

## Penggunaan AI

AI digunakan sebagai alat bantu pengembangan dan, secara opsional, untuk menyusun kalimat saran dari data aktual. Prompt membatasi AI agar tidak menambah angka atau fakta di luar data, tidak menyimpulkan penyebab asap, dan tidak memberi diagnosis medis. Seluruh tanggung jawab karya berada pada tim.
