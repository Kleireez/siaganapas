# SiagaNapas

**Pantau udara & titik panas Riau dalam satu tampilan.**

SiagaNapas adalah aplikasi web yang merangkum kualitas udara, cuaca, dan titik panas di Riau, lalu menerjemahkannya menjadi saran yang mudah dipahami dan langsung bisa ditindaklanjuti warga.

🔗 **Website:** https://www.siaganapas.web.id

> Dibuat untuk **Web Development Competition FESTRA 2026**
> Tema: _Building Intelligent Web Solutions for Future Society_
> Subtema: _Sustainable Environment & Green Technology_

---

## Latar belakang

Setiap musim kemarau, kabut asap dari kebakaran hutan dan lahan menyelimuti Riau. Warga membutuhkan jawaban cepat: seberapa parah udara hari ini, aman keluar rumah atau tidak, dan dari mana asapnya. Informasinya ada, tetapi tersebar dan sering memakai istilah teknis. SiagaNapas mengumpulkannya dalam satu halaman dengan bahasa sehari-hari.

## Fitur utama

- **Indeks kualitas udara (AQI) terkini** dengan skala visual dan warna yang menyesuaikan tingkat bahaya
- **Data per kabupaten/kota** di Riau
- **Rekomendasi aktivitas** lengkap dengan alasannya
- **Tren AQI per 2 jam** dan **prakiraan 3 hari**, termasuk waktu dengan udara terbaik
- **Kondisi saat ini**: PM2.5, PM10, CO, NO₂, SO₂, suhu, kelembapan, angin, jarak pandang
- **Peta titik panas interaktif** (hingga 150 titik), dibedakan warna berdasarkan tingkat kepercayaan, dengan detail jarak, arah, dan FRP
- **Lokasi saya**: menghitung titik panas terdekat dari posisi pengguna. Perhitungan dilakukan **sepenuhnya di browser**, lokasi **tidak dikirim ke server**
- **Panduan per kelompok**: anak-anak, ibu hamil, lansia, pengendara, dan orang sensitif terhadap asap
- **Transparansi data**: status koneksi dan kesegaran tiap sumber ditampilkan, serta catatan bahwa titik panas belum tentu berarti kebakaran
- **Responsif** dan nyaman dibuka dari ponsel

## Teknologi

| Bagian     | Teknologi                                        |
| ---------- | ------------------------------------------------ |
| Backend    | Laravel (PHP), Blade                             |
| Frontend   | HTML, CSS, JavaScript                            |
| Peta       | Leaflet + OpenStreetMap (dimuat saat dibutuhkan) |
| Deployment | Railway                                          |

## Sumber data

- [Open-Meteo](https://open-meteo.com/): kualitas udara, cuaca, dan prakiraan
- [NASA FIRMS](https://firms.modaps.eosdis.nasa.gov/): titik panas satelit

Tidak ada data yang diisi manual. Jika salah satu sumber gagal dimuat, halaman menampilkan keterangan dan tetap berfungsi.

## Menjalankan di komputer lokal

Prasyarat: PHP, Composer, dan koneksi internet.

```bash
# 1. Clone repository
git clone https://github.com/Kleireez/siaganapas.git
cd siaganapas

# 2. Pasang dependensi
composer install

# 3. Siapkan konfigurasi
cp .env.example .env
php artisan key:generate

# 4. Isi API key di file .env (lihat bagian di bawah)

# 5. Jalankan
php artisan serve
```

Buka http://127.0.0.1:8000

### Konfigurasi `.env`

Isi kunci API yang diperlukan, misalnya untuk NASA FIRMS:

```env
FIRMS_MAP_KEY=isi_dengan_key_kamu
```

> File `.env` tidak disertakan di repository. Jangan pernah mengunggah API key ke GitHub.

## Catatan penting

- Data satelit dapat terlambat beberapa jam dan tidak selalu menangkap setiap kebakaran.
- Titik panas **belum tentu berarti kebakaran**; ini hanya penanda area bersuhu tinggi yang perlu diperhatikan.
- SiagaNapas **melengkapi**, bukan menggantikan, informasi dan keputusan resmi pemerintah.

## Tim

- [Nama Ketua] - [Perguruan Tinggi]
- [Nama Anggota 1]
- [Nama Anggota 2]

## Lisensi

Hak cipta karya tetap milik tim pembuat.
