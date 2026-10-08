<!DOCTYPE html>
<html lang="id">
@php
    /* Presentasi saja. Semua data dari HomeController (KONTRAK_DATA.md). Status AQI tidak dihitung ulang di sini. */
    $validKeys = ['baik','sedang','sensitif','tidak-sehat','sangat-tidak-sehat','berbahaya'];
    $lv = fn ($k) => in_array($k, $validKeys, true) ? $k : 'netral';
    $levelKey = $lv($udara['level_key'] ?? null);

    $deskripsiLevel = [
        'baik' => 'Udara bersih dan aman untuk beraktivitas di luar ruangan.',
        'sedang' => 'Udara dapat diterima, tetapi orang yang sangat sensitif perlu memperhatikan kondisinya.',
        'sensitif' => 'Kelompok sensitif sebaiknya mengurangi aktivitas berat di luar ruangan.',
        'tidak-sehat' => 'Udara saat ini tidak sehat untuk sebagian orang. Kurangi aktivitas di luar ruangan, terutama jika Anda sensitif terhadap asap.',
        'sangat-tidak-sehat' => 'Udara sangat tidak sehat. Batasi aktivitas di luar ruangan dan lindungi diri saat harus keluar.',
        'berbahaya' => 'Udara berbahaya bagi semua orang. Hindari aktivitas di luar ruangan.',
        'netral' => 'Lihat rekomendasi di bawah untuk panduan aktivitas Anda.',
    ][$levelKey];

    $judulRingkasan = [
        'membaik' => 'Kualitas udara cenderung membaik.',
        'memburuk' => 'Kualitas udara cenderung memburuk.',
        'stabil' => 'Kualitas udara relatif stabil.',
    ][$ringkasan['kecenderungan'] ?? ''] ?? 'Kecenderungan belum tersedia.';

    $jam = fn ($t) => str_replace(':', '.', (string) $t);
    $maxAqi = max(180, collect($tren ?? [])->max('aqi') ?? 0);
    $gagal = collect(['udara'=>'Udara','cuaca'=>'Cuaca','titik_panas'=>'Titik panas','tren'=>'Tren'])
        ->filter(fn ($l, $k) => empty($statusApi[$k]))->values();
    $na = '<span class="na">Tidak tersedia</span>';

    // Peta: proyeksi dihitung di server (tanpa JS), maksimal 150 titik
    $pts = array_slice($titik ?? [], 0, 150);
    $proj = fn ($lat, $lon) => [
        round(min(97, max(3, ($lon - 100.0) / 3.9 * 100)), 2),
        round(min(94, max(6, (2.7 - $lat) / 3.9 * 100)), 2),
    ];
    $cityPos = isset($infoKota['lat'], $infoKota['lon']) ? $proj((float) $infoKota['lat'], (float) $infoKota['lon']) : null;
    $petaData = [
        'kota' => $infoKota['nama'] ?? '',
        'lat'  => isset($infoKota['lat']) ? (float) $infoKota['lat'] : null,
        'lon'  => isset($infoKota['lon']) ? (float) $infoKota['lon'] : null,
        'pts'  => [],
    ];
    foreach ($pts as $p) {
        if (is_null($p['latitude'] ?? null) || is_null($p['longitude'] ?? null)) continue;
        $petaData['pts'][] = [
            'la' => (float) $p['latitude'],
            'lo' => (float) $p['longitude'],
            'd'  => $p['distance'] ?? '',
            'a'  => ucfirst($p['direction'] ?? ''),
            'l'  => $p['location'] ?? '',
            't'  => $p['time_label'] ?? '',
            'f'  => $p['frp'] ?? '-',
            'c'  => $p['confidence'] ?? '-',
        ];
    }

    $panduan = [
        'Anak-anak' => ['Kurangi bermain di luar saat udara sedang tidak sehat.','Gunakan masker saat harus keluar rumah.','Perhatikan jika muncul batuk atau napas terasa tidak nyaman.'],
        'Ibu hamil' => ['Kurangi aktivitas di luar ruangan.','Gunakan masker saat harus keluar.','Jika merasa tidak sehat, cari bantuan dari tenaga kesehatan.'],
        'Lansia' => ['Hindari aktivitas berat di luar rumah.','Pastikan kebutuhan obat rutin tetap tersedia.','Minta bantuan jika mengalami kesulitan bernapas.'],
        'Pengendara' => ['Gunakan masker dan pelindung mata saat berkendara.','Nyalakan lampu dan kurangi kecepatan saat jarak pandang menurun.','Pilih waktu berkendara ketika kualitas udara lebih baik.'],
        'Sensitif terhadap asap' => ['Batasi waktu di luar ruangan.','Tutup jendela saat asap masuk ke rumah.','Periksa kualitas udara sebelum beraktivitas.'],
    ];
@endphp
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="description" content="SiagaNapas: pantau kualitas udara, cuaca, dan titik panas di Riau.">
<meta name="theme-color" content="#075985">
<link rel="icon" href="{{ asset('favicon.ico') }}" sizes="48x48">
<link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
<link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
<title>SiagaNapas – Pantau Udara & Titik Panas · {{ $infoKota['nama'] }}</title>
{{-- Font tidak memblokir render; sebelum termuat dipakai font sistem --}}
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;600;700&family=Manrope:wght@800&display=swap" onload="this.onload=null;this.rel='stylesheet'">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
<noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;600;700&family=Manrope:wght@800&display=swap"></noscript>
<style>
/* CSS tulis tangan (~7 KB): tanpa Tailwind CDN, tanpa kompilasi di browser */
:root{--bg:#F3F7FA;--fg:#172B3A;--mut:#64748b;--mut2:#94a3b8;--line:#D7E3EA;--riau:#075985;--riau6:#087EA4;--riau5:#EAF6FB;--riau1:#D8F0F7;
--sans:'DM Sans',system-ui,-apple-system,'Segoe UI',Roboto,sans-serif;--disp:'Manrope','DM Sans',system-ui,-apple-system,'Segoe UI',sans-serif}
*{box-sizing:border-box;margin:0}
html{scroll-behavior:smooth;scroll-padding-top:5rem;-webkit-text-size-adjust:100%}
body{font-family:var(--sans);background:var(--bg);color:var(--fg);line-height:1.5;padding-bottom:5rem;overflow-x:hidden}
a{color:inherit;text-decoration:none}button{font:inherit;color:inherit;cursor:pointer;background:none;border:0}
h1,h2,h3,.disp{font-family:var(--disp);font-weight:800}
:focus-visible{outline:3px solid rgba(8,126,164,.35);outline-offset:2px}
::selection{background:var(--riau1);color:#0C4A6E}
.wrap{max-width:72rem;margin:0 auto;padding:0 1rem}
.panel{background:#fff;border:1px solid var(--line);border-radius:12px;box-shadow:0 1px 2px rgba(20,50,70,.03)}
.soft{background:#F8FBFD;border:1px solid #DCE8EF;border-radius:12px}
.eyebrow{letter-spacing:.11em;text-transform:uppercase;font-size:.68rem;font-weight:700;color:var(--riau)}
.eyebrow.g{color:var(--mut)}
.mut{color:var(--mut)}.sm{font-size:.875rem}.xs{font-size:.75rem}.b{font-weight:700}.na{font-size:.875rem;font-weight:500;color:var(--mut2)}
.mt1{margin-top:.25rem}.mt2{margin-top:.5rem}.mt3{margin-top:.75rem}.mt5{margin-top:1.25rem}.mt6{margin-top:1.5rem}
.min0{min-width:0}.rowb{display:flex;align-items:center;justify-content:space-between;gap:.75rem}
h2{font-size:1.125rem;line-height:1.3}
/* header */
.top{position:sticky;top:0;z-index:40;background:#fff;border-bottom:1px solid #e2e8f0}
.top .wrap{height:4rem;display:flex;align-items:center;justify-content:space-between;gap:.75rem}
.brand{display:flex;align-items:center;gap:.75rem;min-width:0}
.logo{width:2.25rem;height:2.25rem;flex:none;border-radius:8px;background:var(--riau);color:#fff;display:grid;place-items:center}
.logo svg,.ic{width:1.25rem;height:1.25rem}
.brand b{display:block;font-family:var(--disp);font-weight:800;letter-spacing:-.01em;line-height:1.25}.brand b i{font-style:normal;color:var(--riau)}
.brand small{display:none;font-size:10px;color:var(--mut);font-weight:500;letter-spacing:.03em}
.nav{display:none;gap:.25rem;font-size:.875rem;font-weight:600;color:#475569}
.nav a{padding:.5rem .75rem;border-radius:8px}.nav a:hover{background:#f1f5f9}.nav a.on{background:var(--riau5);color:var(--riau)}
.city{position:relative;flex:none}
.cbtn{display:flex;align-items:center;gap:.5rem;border:1px solid #e2e8f0;background:#fff;padding:.5rem .75rem;border-radius:8px;font-size:.875rem;font-weight:600}
.cbtn:hover{background:#f8fafc}.cbtn span{max-width:8.5rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.cbtn svg{width:1rem;height:1rem;flex:none}
.cmenu{display:none;position:absolute;right:0;top:calc(100% + .5rem);width:14rem;max-height:70vh;overflow-y:auto;padding:.375rem;z-index:50;list-style:none;padding-left:.375rem;font-size:.875rem}
.cmenu.open{display:block}
.cmenu a{display:flex;justify-content:space-between;gap:.5rem;padding:.625rem .75rem;border-radius:8px;font-weight:500}
.cmenu a:hover,.cmenu a.cur{background:var(--riau5)}.cmenu a.cur{color:var(--riau)}.cmenu em{font-style:normal;font-size:11px;font-weight:600;color:var(--mut2)}
.warn{background:#fffbeb;border-bottom:1px solid #fde68a;color:#92400e;font-size:.8rem;padding:.5rem 0}
/* layout */
main{padding:1.75rem 0;display:grid;gap:2rem}
.grid{display:grid;gap:1.25rem;min-width:0}
.defer{content-visibility:auto;contain-intrinsic-size:auto 420px}
/* status warna: dipilih lewat level_key dari backend */
.lv-netral{--ac:#64748b;--sf:#f8fafc;--tx:#475569;--ln:#e2e8f0}
.lv-baik{--ac:#10b981;--sf:#ecfdf5;--tx:#047857;--ln:#a7f3d0}
.lv-sedang{--ac:#eab308;--sf:#fefce8;--tx:#a16207;--ln:#fde68a}
.lv-sensitif{--ac:#f59e0b;--sf:#fff8eb;--tx:#b45309;--ln:#fde1a8}
.lv-tidak-sehat{--ac:#f97316;--sf:#fff4ed;--tx:#c2410c;--ln:#fed7aa}
.lv-sangat-tidak-sehat{--ac:#dc2626;--sf:#fef2f2;--tx:#b91c1c;--ln:#fecaca}
.lv-berbahaya{--ac:#7e22ce;--sf:#faf5ff;--tx:#6b21a8;--ln:#e9d5ff}
.tx{color:var(--tx)}
/* hero */
.air{padding:1.5rem;border-left:4px solid var(--ac);background:linear-gradient(135deg,var(--sf) 0%,#fff 58%);height:100%}
.air h1{font-size:1.5rem;line-height:1.2;letter-spacing:-.02em;margin-top:.5rem}
.wrapx{display:flex;flex-wrap:wrap;align-items:flex-start;justify-content:space-between;gap:.75rem}
.badge{display:inline-flex;align-items:center;gap:.5rem;font-size:.75rem;font-weight:600;border-radius:999px;padding:.25rem .625rem;border:1px solid}
.badge i{width:.5rem;height:.5rem;border-radius:50%}
.ok{color:#047857;background:#ecfdf5;border-color:#d1fae5}.ok i{background:#10b981}
.demo{color:#b45309;background:#fffbeb;border-color:#fde68a}.demo i{background:#f59e0b}
.aqirow{display:flex;align-items:flex-end;gap:1rem;margin-top:1.75rem}
.aqi{font-family:var(--disp);font-weight:800;font-size:4.5rem;line-height:1;letter-spacing:-.045em;color:#0f172a}
.pill{display:inline-flex;align-items:center;gap:.5rem;margin-top:.25rem;padding:.375rem .75rem;border-radius:6px;font-size:.875rem;font-weight:700;background:var(--sf);color:var(--tx);border:1px solid var(--ln)}
.pill i{width:.5rem;height:.5rem;border-radius:50%;background:var(--ac)}
.lead{margin-top:1.25rem;max-width:42rem;font-size:.95rem;line-height:1.75;color:#475569}
.scalebox{margin-top:1.5rem;max-width:36rem}
.scale{position:relative}.scale div{height:.75rem;border-radius:999px;background:linear-gradient(90deg,#10b981 0%,#facc15 38%,#f97316 62%,#dc2626 82%,#7e22ce 100%)}
.knob{position:absolute;top:-6px;width:1.5rem;height:1.5rem;border-radius:50%;background:#fff;border:3px solid #0f172a;box-shadow:0 1px 2px rgba(0,0,0,.2)}
.scalel{display:grid;grid-template-columns:repeat(4,1fr);margin-top:.5rem;font-size:11px;font-weight:600;color:var(--mut)}
.scalel span:nth-child(2),.scalel span:nth-child(3){text-align:center}.scalel span:last-child{text-align:right}
.side{padding:1.5rem;display:flex;flex-direction:column;justify-content:space-between;gap:1.5rem}
.kv{display:flex;align-items:center;justify-content:space-between;gap:.75rem;padding-bottom:.75rem;border-bottom:1px solid #f1f5f9}.kv:last-child{border:0;padding:0}
.kv span:first-child{font-size:.875rem;color:var(--mut)}.kv span:last-child{font-size:.875rem;font-weight:700;text-align:right}
.tip{background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:.75rem 1rem}
/* banner (tanpa foto) */
/* banner: foto + gradasi biru Riau supaya menyatu dan teks tetap terbaca */
.banner{position:relative;isolation:isolate;overflow:hidden;border-radius:16px;min-height:240px;padding:1.75rem 1.5rem;display:flex;align-items:flex-end;color:#fff;background:#0C4A6E}
.banner::before{content:"";position:absolute;inset:0;z-index:-2;background:url('{{ asset('images/banner-pekanbaru.jpg') }}') center/cover no-repeat}
.banner::after{content:"";position:absolute;inset:0;z-index:-1;background:radial-gradient(120% 140% at 85% 0%,rgba(22,160,133,.30),transparent 55%),linear-gradient(0deg,#075985 0%,rgba(7,89,133,.88) 40%,rgba(12,74,110,.35) 100%)}
.banner .tag{display:inline-flex;align-items:center;gap:.5rem;border-radius:999px;background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.25);padding:.25rem .75rem;font-size:11px;font-weight:700}
.banner .tag i{width:6px;height:6px;border-radius:50%;background:#6ee7b7}
.banner h2{font-size:1.5rem;letter-spacing:-.02em;margin-top:.75rem}.banner p{margin-top:.5rem;max-width:36rem;font-size:.875rem;line-height:1.6;color:rgba(255,255,255,.85)}
/* rekomendasi */
.rec{padding:1.25rem;border-left:4px solid var(--riau6)}
.rec .top2{display:flex;flex-direction:column;gap:1rem}
.rec .ico{width:2.5rem;height:2.5rem;flex:none;border-radius:12px;background:var(--riau5);color:var(--riau);display:grid;place-items:center}
.rec .flex{display:flex;gap:.75rem;align-items:flex-start}
.chip{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;color:var(--mut);border:1px solid #e2e8f0;border-radius:999px;padding:.125rem .5rem}
.lnk{font-size:.875rem;font-weight:700;color:var(--riau);text-align:left;white-space:nowrap}
.why{display:none;margin-top:1.25rem;padding-top:1.25rem;border-top:1px solid #f1f5f9;gap:.75rem;grid-template-columns:1fr}.why.open{display:grid}
.why .soft{padding:1rem}
/* angka */
.stats{display:grid;grid-template-columns:1fr}
.stats>div{padding:1rem 0;border-top:1px solid #e2e8f0}.stats>div:first-child{border-top:0}
.stats .n{font-family:var(--disp);font-weight:800;font-size:1.5rem;margin-top:.25rem}.stats .n small{font-family:var(--sans);font-size:.875rem;font-weight:600;color:var(--mut2)}
.sub{font-size:.75rem;margin-top:.25rem;color:var(--mut)}
.tiles{display:grid;grid-template-columns:1fr 1fr;gap:.75rem;margin-top:1rem;padding-top:1rem;border-top:1px solid #e2e8f0}
.tiles .soft,.tl .soft{padding:1rem}
.tiles b,.tl b{display:block;margin-top:.25rem;font-family:var(--disp);font-weight:800}
.tl{display:grid;grid-template-columns:1fr 1fr;gap:.75rem}.tl .wide{grid-column:span 2}
.dot{width:.625rem;height:.625rem;border-radius:50%;background:var(--ac);flex:none;margin-top:.4rem}
/* peta */
/* peta */
.map{position:relative;z-index:0;isolation:isolate;overflow:hidden;border-radius:12px;border:1px solid #e2e8f0;height:420px;background:#e5e7eb}
.map .leaflet-container{width:100%;height:100%;font-family:inherit}
.mkc{background:transparent;border:0}
.mkc span{display:block;border-radius:50%;border:2px solid #fff;box-shadow:0 1px 4px rgba(0,0,0,.4)}
.mkc.t span{background:#dc2626;width:16px;height:16px}.mkc.s span{background:#f97316;width:14px;height:14px}.mkc.r span{background:#eab308;width:12px;height:12px}
.mkc.you span{background:#7c3aed;width:16px;height:16px;box-shadow:0 0 0 7px rgba(124,58,237,.25),0 1px 4px rgba(0,0,0,.4)}
.locbtn{display:inline-flex;align-items:center;gap:.4rem;padding:.5rem .875rem;border-radius:8px;border:1px solid var(--riau);background:#fff;color:var(--riau);font-size:.875rem;font-weight:600;cursor:pointer}.locbtn:hover{background:var(--riau5)}.locbtn[disabled]{opacity:.6;cursor:wait}
.mkc.me span{background:#0284c7;width:16px;height:16px;box-shadow:0 0 0 7px rgba(56,189,248,.3),0 1px 4px rgba(0,0,0,.4)}
.mi{margin-top:.75rem;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:.625rem .75rem;font-size:.8125rem}
.leg{display:flex;flex-wrap:wrap;gap:.5rem 1.25rem;padding-top:1rem;font-size:.75rem;font-weight:500;color:var(--mut)}
.leg span{display:flex;align-items:center;gap:.5rem}.leg i{width:.625rem;height:.625rem;border-radius:50%}
dl.dg{display:grid;grid-template-columns:1fr 1fr;gap:.75rem 1rem;font-size:.875rem}dl.dg dt{font-size:.75rem;color:var(--mut)}dl.dg dd{margin:0;font-weight:700}dl.dg .w{grid-column:span 2}
/* grafik */
.bars{height:12rem;margin-top:1.75rem;display:flex;align-items:flex-end;gap:4px}
.bc{flex:1;min-width:0;height:100%;display:flex;flex-direction:column;align-items:center;justify-content:flex-end;gap:4px;font-size:9px;color:var(--mut2)}
.bc u{display:block;width:100%;max-width:2.25rem;border-radius:2px 2px 0 0;background:var(--ac);opacity:.4;text-decoration:none}
.bc.now{color:#0f172a;font-weight:700}.bc.now u{opacity:1}
.empty{margin-top:1.25rem;border:1px dashed #cbd5e1;background:#f8fafc;border-radius:12px;display:grid;place-items:center;text-align:center;padding:2rem 1.5rem}
.fr{display:flex;align-items:center;justify-content:space-between;gap:1rem;padding:1rem 0;border-top:1px solid #e2e8f0}.fr:first-of-type{border-top:0}
.fr .big{font-family:var(--disp);font-weight:800;font-size:1.5rem;line-height:1.2;color:var(--tx);text-align:right}.fr small{display:block;font-size:11px;font-weight:600;color:var(--tx);text-align:right}
/* panduan, tentang */
.tabs{display:flex;flex-wrap:wrap;gap:.5rem;margin-top:1.25rem}
.tab{padding:.5rem .875rem;border-radius:8px;font-size:.875rem;font-weight:600;border:1px solid #e2e8f0;background:#fff;color:#475569}.tab:hover{background:var(--riau5)}
.tab.on{background:var(--riau);border-color:var(--riau);color:#fff}
.tips{display:none;margin-top:1.25rem;gap:.75rem;grid-template-columns:1fr;list-style:none;padding:0}.tips.on{display:grid}
.tips li{display:flex;gap:.75rem;padding:1rem;font-size:.875rem;line-height:1.5rem;color:#334155}
.tips li span:first-child{width:1.5rem;height:1.5rem;flex:none;border-radius:50%;background:var(--riau5);color:var(--riau);display:grid;place-items:center;font-size:.75rem;font-weight:700}
.step{display:flex;align-items:center;gap:.5rem;font-size:.875rem;font-weight:700}.step span{width:1.75rem;height:1.75rem;border-radius:50%;background:#fff;border:1px solid #e2e8f0;display:grid;place-items:center;font-size:.75rem}
.step.last{color:var(--riau)}.step.last span{background:var(--riau5);border-color:var(--riau1)}.link{margin-left:.875rem;height:1rem;border-left:1px solid #cbd5e1}
.ibox{width:2.5rem;height:2.5rem;flex:none;border-radius:8px;display:grid;place-items:center}
details.panel{padding:1rem}summary{font-weight:600;cursor:pointer}details p{margin-top:.5rem;font-size:.875rem;line-height:1.5rem;color:#475569}
.src span{border:1px solid #e2e8f0;background:#fff;border-radius:8px;padding:.5rem .75rem;font-size:.875rem}
footer{border-top:1px solid #e2e8f0;background:#fff;font-size:.75rem;color:var(--mut)}footer .wrap{padding-top:1.75rem;padding-bottom:1.75rem;display:flex;flex-direction:column;gap:.5rem}
.bnav{position:fixed;left:0;right:0;bottom:0;z-index:50;background:#fff;border-top:1px solid #e2e8f0;padding-bottom:calc(env(safe-area-inset-bottom,0px) + .45rem)}
.bnav ul{display:grid;grid-template-columns:repeat(4,1fr);max-width:28rem;margin:0 auto;padding:0;list-style:none;font-size:11px;font-weight:600;color:var(--mut)}
.bnav a{display:flex;flex-direction:column;align-items:center;gap:.25rem;padding:.5rem 0}.bnav a.on{color:var(--riau)}
@media(min-width:640px){
 .wrap{padding:0 1.5rem}.brand small{display:block}.air{padding:2rem}.air h1{font-size:1.875rem}.aqi{font-size:6rem}.lead{font-size:1rem}
 .side,.rec{padding:1.75rem}.banner{padding:2rem}.banner h2{font-size:1.875rem}.map{height:480px}
 .banner{min-height:280px}
 .banner::after{background:radial-gradient(120% 140% at 85% 0%,rgba(22,160,133,.30),transparent 55%),linear-gradient(90deg,#075985 0%,rgba(7,89,133,.94) 32%,rgba(12,74,110,.55) 62%,rgba(12,74,110,.15) 100%)}
 .stats{grid-template-columns:1fr 1fr;gap:0 1rem}.stats>div:nth-child(2){border-top:0}
 .tiles{grid-template-columns:repeat(3,1fr)}.tl{grid-template-columns:repeat(3,1fr)}.tl .wide{grid-column:auto}
 .why{grid-template-columns:repeat(3,1fr)}.tips{}.rec .top2{flex-direction:row;justify-content:space-between}
}
@media(min-width:768px){
 body{padding-bottom:0}.bnav{display:none}main{padding:2.5rem 0}
 .g-sum{grid-template-columns:1.2fr .8fr}.g-eq{grid-template-columns:1fr 1fr}.tips.on{grid-template-columns:repeat(3,1fr)}
 footer .wrap{flex-direction:row;justify-content:space-between;align-items:center}.src-row{flex-direction:row!important;align-items:center!important;justify-content:space-between}
}
@media(min-width:1024px){
 .nav{display:flex}.g-main{grid-template-columns:1.35fr .65fr}.g-learn{grid-template-columns:.85fr 1.15fr}
 .stats{grid-template-columns:repeat(4,1fr);gap:0}.stats>div{border-top:0;padding:.5rem 1.25rem;border-left:1px solid #e2e8f0}.stats>div:first-child{border-left:0;padding-left:0}
}
@media(prefers-reduced-motion:reduce){html{scroll-behavior:auto}}
</style>
</head>
<body>
<header class="top">
  <div class="wrap">
    <a href="{{ route('home', ['kota' => $kota]) }}" class="brand">
      <span class="logo"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 18h18"/><path d="M5 15c1.5-2.7 3.5-4 6-4 2.1 0 3.7 1 4.8 2.8C17 11.8 18.4 10.5 21 10"/><path d="M7 7.5c1.5-1.4 3.2-2 5-2 1.8 0 3.4.6 4.7 1.8"/></svg></span>
      <span class="min0"><b>Siaga<i>Napas</i></b><small>Pantau udara & titik panas Riau</small></span>
    </a>
    <nav class="nav"><a href="#" class="on">Beranda</a><a href="#udara">Kualitas Udara</a><a href="#peta">Titik Panas</a><a href="#panduan">Panduan</a><a href="#data">Tentang Data</a></nav>
    <div class="city">
      <button id="cityBtn" type="button" class="cbtn" aria-expanded="false" aria-haspopup="true">
        <svg viewBox="0 0 24 24" fill="none" stroke="#075985" stroke-width="2"><path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/></svg><span>{{ $infoKota['nama'] }}</span><svg viewBox="0 0 24 24" fill="none" stroke="#94a3b8" stroke-width="2"><path d="m6 9 6 6 6-6"/></svg>
      </button>
      <ul id="cityMenu" class="cmenu panel">
        @foreach ($daftarKota as $k => $c)
          <li><a href="{{ route('home', ['kota' => $k]) }}" class="{{ $k === $kota ? 'cur' : '' }}"><span>{{ $c['nama'] }}</span><em>{{ $c['jenis'] }}</em></a></li>
        @endforeach
      </ul>
    </div>
  </div>
</header>

@if ($gagal->isNotEmpty() && !$offline)
  <div class="warn"><div class="wrap">Sebagian data belum dapat dimuat: {{ $gagal->implode(', ') }}. Bagian terkait mungkin kosong.</div></div>
@endif

<main class="wrap">
  {{-- HERO --}}
  <section id="udara" class="grid g-main">
    <div class="panel min0 lv-{{ $levelKey }}" style="overflow:hidden">
      <div class="air">
        <div class="wrapx">
          <div class="min0">
            <p class="eyebrow">Kondisi udara · {{ $infoKota['nama'] }}</p>
            <h1>Kualitas udara hari ini {{ mb_strtolower($udara['level'] ?? 'belum tersedia') }}</h1>
            <p class="sm mut mt1">{{ $tanggalHariIni }}@if (!is_null($updatedAt)) · Diperbarui {{ $updatedAt }}@endif</p>
          </div>
          @if ($offline)<span class="badge demo"><i></i>Data contoh</span>@else<span class="badge ok"><i></i>Data API aktif</span>@endif
        </div>
        <div class="aqirow">
          <span class="aqi">{{ $udara['aqi'] }}</span>
          <div style="padding-bottom:.25rem"><span class="sm mut" style="display:block">Indeks kualitas udara</span><span class="pill"><i></i>{{ $udara['level'] }}</span></div>
        </div>
        <p class="lead">{{ $deskripsiLevel }}</p>
        <div class="scalebox">
          <div class="scale"><div></div><span class="knob" style="left:calc({{ (int) ($udara['scale_percent'] ?? 0) }}% - 12px)" role="img" aria-label="AQI {{ $udara['aqi'] }}"></span></div>
          <div class="scalel"><span>Baik</span><span>Sedang</span><span>Tidak sehat</span><span>Berbahaya</span></div>
        </div>
      </div>
    </div>

    <div class="panel side min0">
      <div>
        <div class="rowb"><p class="eyebrow">Ringkasan untuk Anda</p><span class="badge ok" style="padding:.25rem .625rem">Hari ini</span></div>
        <h2 class="mt2" style="font-size:1.25rem">Apa artinya untuk aktivitas Anda?</h2>
        <p class="sm mut mt2" style="line-height:1.5rem">{{ $judulRingkasan }}</p>
      </div>
      <div style="display:grid;gap:.75rem">
        <div class="kv"><span>Terburuk</span><span>@if ($ringkasan['terburuk']){{ $jam($ringkasan['terburuk']['time']) }} · AQI {{ $ringkasan['terburuk']['aqi'] }}@else{!! $na !!}@endif</span></div>
        <div class="kv"><span>Terbaik</span><span>@if ($ringkasan['terbaik']){{ $jam($ringkasan['terbaik']['time']) }} · AQI {{ $ringkasan['terbaik']['aqi'] }}@else{!! $na !!}@endif</span></div>
        <div class="kv"><span>Titik panas terdekat</span><span>@if ($terdekat){{ $terdekat['distance'] }} km @elseif (empty($statusApi['titik_panas'])){!! $na !!}@else<span style="font-weight:500;color:var(--mut)">Tidak ada</span>@endif</span></div>
      </div>
      <div class="tip"><p class="xs b mut">Saran singkat</p><p class="sm b mt1" style="color:#1e293b">{{ $judulSaran }}</p></div>
    </div>
  </section>

  <section class="banner">
    <div style="max-width:38rem"><span class="tag"><i></i> Lingkungan Riau dalam satu tampilan</span>
      <h2>Pantau kondisi Riau dengan lebih mudah.</h2>
      <p>Kualitas udara, cuaca, dan titik panas dirangkum dari data API agar informasi lingkungan lebih mudah dipahami masyarakat.</p></div>
  </section>

  {{-- REKOMENDASI --}}
  <section class="panel rec">
    <div class="top2">
      <div class="flex min0">
        <div class="ico" aria-hidden="true"><svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3v2M12 19v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M3 12h2M19 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/><circle cx="12" cy="12" r="4"/></svg></div>
        <div class="min0">
          <div style="display:flex;flex-wrap:wrap;gap:.5rem;align-items:center"><p class="eyebrow">Rekomendasi untuk Anda</p><span class="chip">Berdasarkan kondisi saat ini</span></div>
          <h2 class="mt1">{{ $judulSaran }}</h2>
          <p class="sm mut mt2" style="line-height:1.5rem;max-width:48rem">{{ $saran }}</p>
        </div>
      </div>
      @if (!empty($alasanSaran))<button id="whyBtn" type="button" class="lnk" aria-expanded="false" aria-controls="why">Lihat alasan →</button>@endif
    </div>
    @if (!empty($alasanSaran))
      <div id="why" class="why">
        @foreach ($alasanSaran as $a)<div class="soft"><p class="xs mut">{{ $a['label'] }}</p><p class="sm b mt1">{{ $a['nilai'] }}</p></div>@endforeach
      </div>
    @endif
  </section>

  {{-- RINGKASAN --}}
  <section class="grid g-sum">
    <div class="panel lv-{{ $levelKey }}" style="padding:1.25rem 1.5rem">
      <p class="eyebrow">Ringkasan hari ini</p>
      <div style="display:flex;gap:.75rem" class="mt2"><span class="dot"></span><div>
        <h2 style="font-size:1.25rem">{{ $judulRingkasan }}</h2>
        <p class="sm mt2" style="line-height:1.5rem;color:#475569">
          @if ($ringkasan['terburuk'])Kondisi paling kurang baik tercatat sekitar pukul {{ $jam($ringkasan['terburuk']['time']) }} (AQI {{ $ringkasan['terburuk']['aqi'] }}).@else Data tren hari ini belum tersedia.@endif
          @if ($ringkasan['waktu_terbaik']) Kondisi lebih baik diperkirakan sekitar pukul {{ $jam($ringkasan['waktu_terbaik']['time']) }} (AQI {{ $ringkasan['waktu_terbaik']['aqi'] }}).@endif
        </p></div></div>
    </div>
    <div class="tl">
      <div class="soft"><p class="xs mut">Terburuk</p>@if ($ringkasan['terburuk'])<b>{{ $jam($ringkasan['terburuk']['time']) }}</b><p class="xs b" style="color:#c2410c">AQI {{ $ringkasan['terburuk']['aqi'] }}</p>@else<p class="mt1">{!! $na !!}</p>@endif</div>
      <div class="soft"><p class="xs mut">Terbaik</p>@if ($ringkasan['terbaik'])<b>{{ $jam($ringkasan['terbaik']['time']) }}</b><p class="xs b" style="color:#047857">AQI {{ $ringkasan['terbaik']['aqi'] }}</p>@else<p class="mt1">{!! $na !!}</p>@endif</div>
      <div class="soft wide"><p class="xs mut">Titik panas</p>@if (!empty($statusApi['titik_panas']))<b>{{ $jumlahTitik }} titik</b><p class="xs b mut">24 jam terakhir · Riau</p>@else<p class="mt1">{!! $na !!}</p>@endif</div>
    </div>
  </section>

  {{-- KONDISI SAAT INI --}}
  <section class="panel defer" style="padding:1.25rem 1.5rem">
    <p class="eyebrow g">Ringkasan</p><h2 class="mt1">Kondisi saat ini</h2><p class="sm mut mt1" style="margin-bottom:1rem">Angka penting untuk memahami keadaan udara di {{ $infoKota['nama'] }}.</p>
    <div class="stats">
      <div><p class="sm b mut">PM2.5</p>@if (!is_null($udara['pm25']))<p class="n">{{ $udara['pm25'] }} <small>µg/m³</small></p><p class="sub b lv-{{ $levelKey }} tx">{{ $udara['pm25_level'] ?? '' }}</p>@else<p class="mt1">{!! $na !!}</p>@endif</div>
      <div><p class="sm b mut">Suhu</p>@if (!is_null($cuaca['temperature'] ?? null))<p class="n">{{ $cuaca['temperature'] }}<small>°C</small></p><p class="sub">{{ $cuaca['condition'] ?? '' }}@if (!is_null($cuaca['feels_like'] ?? null)) · Terasa {{ $cuaca['feels_like'] }}°C @endif</p>@else<p class="mt1">{!! $na !!}</p>@endif</div>
      <div><p class="sm b mut">Kelembapan</p>@if (!is_null($cuaca['humidity'] ?? null))<p class="n">{{ $cuaca['humidity'] }}<small>%</small></p>@if (!is_null($cuaca['humidity_label'] ?? null))<p class="sub">{{ $cuaca['humidity_label'] }}</p>@endif @else<p class="mt1">{!! $na !!}</p>@endif</div>
      <div><p class="sm b mut">Angin</p>@if (!is_null($cuaca['wind_speed'] ?? null))<p class="n">{{ $cuaca['wind_speed'] }} <small>km/j</small></p>@if (!is_null($cuaca['wind_direction'] ?? null))<p class="sub">Dari {{ $cuaca['wind_direction'] }}@if (!is_null($cuaca['wind_degree'] ?? null)) ({{ $cuaca['wind_degree'] }}°)@endif</p>@endif @else<p class="mt1">{!! $na !!}</p>@endif</div>
    </div>
    <div class="tiles" style="grid-template-columns:repeat(auto-fit,minmax(8.5rem,1fr))">
      @foreach ([['PM10','pm10'],['CO','co'],['NO₂','no2'],['SO₂','so2']] as [$lbl,$key])
        <div class="soft"><p class="xs mut">{{ $lbl }}</p><p class="sm b mt1">@if (!is_null($udara[$key] ?? null)){{ $udara[$key] }} <span class="xs mut">µg/m³</span>@else{!! $na !!}@endif</p></div>
      @endforeach
      <div class="soft"><p class="xs mut">Jarak pandang</p><p class="sm b mt1">@if (!is_null($cuaca['visibility'] ?? null)){{ $cuaca['visibility'] }} <span class="xs mut">km</span>@else{!! $na !!}@endif</p></div>
    </div>
  </section>

  {{-- TITIK PANAS --}}
  <section id="peta" class="grid g-main defer">
    <div class="panel min0" style="padding:1rem 1.25rem">
      <div style="padding:0 .25rem 1rem">
        <h2>Titik panas di sekitar {{ $infoKota['nama'] }}</h2>
        <p class="sm mut mt1">@if (!empty($statusApi['titik_panas'])){{ $jumlahTitik }} titik panas terdeteksi dalam 24 jam terakhir di seluruh Riau @else Data titik panas belum dapat dimuat @endif</p>
      </div>
      <div style="padding:0 .25rem .75rem;display:flex;flex-wrap:wrap;align-items:center;gap:.5rem .875rem">
        <button type="button" class="locbtn" id="locBtn"><svg class="ic" style="width:1rem;height:1rem" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3"/></svg>Lokasi saya</button>
        <span class="xs mut" id="locMsg" aria-live="polite">Lokasimu hanya diproses di perangkatmu, tidak dikirim ke server.</span>
      </div>
      <div class="map" id="hsMap" role="region" aria-label="Peta titik panas"></div>
        <div class="mi" aria-live="polite">
          @if ($terdekat)
            <p id="m1" class="b">Titik terdekat: {{ $terdekat['distance'] }} km</p>
            <p id="m2" class="mut" style="margin-top:.125rem">{{ ucfirst($terdekat['direction']) }} · {{ $terdekat['location'] }}</p>
            <p id="m3" style="margin-top:.125rem;color:var(--mut2)">{{ $terdekat['time_label'] }} · FRP {{ $terdekat['frp'] }} MW</p>
          @else
            <p class="b">{{ empty($statusApi['titik_panas']) ? 'Data titik panas tidak tersedia' : 'Tidak ada titik panas terdekat' }}</p>
          @endif
        </div>
      <div class="leg"><span><i style="background:#dc2626"></i>Kepercayaan tinggi</span><span><i style="background:#f97316"></i>Kepercayaan sedang</span><span><i style="background:#eab308"></i>Kepercayaan rendah</span><span><i style="background:#0284c7"></i>Pusat {{ $infoKota['nama'] }}</span><span id="legYou" hidden><i style="background:#7c3aed"></i>Lokasi kamu</span></div>
      <p class="xs" style="padding-top:.75rem;color:var(--mut2)">Peta menampilkan hingga 150 titik terdekat. Ketuk titik untuk melihat detail. © kontributor OpenStreetMap.</p>
    </div>
    <div class="panel min0" style="padding:1.5rem;display:flex;flex-direction:column;justify-content:center">
      <p class="eyebrow g">Perlu diketahui</p>
      <h2 class="mt2" style="font-size:1.25rem">Titik panas bukan berarti pasti kebakaran.</h2>
      <p class="sm mt3" style="line-height:1.5rem;color:#475569">Satelit mendeteksi area yang bersuhu tinggi. Informasi ini membantu menunjukkan lokasi yang perlu diperhatikan.</p>
      @if ($terdekat)
        <dl class="dg mt5" style="padding-top:1.25rem;border-top:1px solid #e2e8f0">
          <div><dt>Jarak</dt><dd>{{ $terdekat['distance'] }} km</dd></div>
          <div><dt>Arah</dt><dd>{{ ucfirst($terdekat['direction']) }}</dd></div>
          <div class="w"><dt>Lokasi (perkiraan)</dt><dd>{{ $terdekat['location'] }}</dd></div>
          <div><dt>Kepercayaan</dt><dd>{{ ucfirst($terdekat['confidence']) }}</dd></div>
          <div><dt>FRP</dt><dd>{{ $terdekat['frp'] }} MW</dd></div>
          <div class="w"><dt>Waktu deteksi</dt><dd>{{ $terdekat['time_label'] }}</dd></div>
        </dl>
      @endif
      <div class="mt5" style="padding-top:1.25rem;border-top:1px solid #e2e8f0">
        <p class="xs mut">Deteksi terbaru</p>
        <p class="b mt1">@if (!is_null($titikTerbaru)){{ $titikTerbaru }}@else{!! $na !!}@endif</p>
        <p class="mt3" style="font-size:11px;line-height:1.25rem;color:var(--mut2)">Nama kabupaten/kota adalah perkiraan berdasarkan pusat wilayah terdekat, bukan batas administrasi resmi.</p>
      </div>
    </div>
  </section>

  {{-- TREN + PRAKIRAAN --}}
  <section class="grid g-main defer">
    <div class="panel min0" style="padding:1.5rem 1.75rem">
      <h2>Tren kualitas udara hari ini</h2>
      <p class="sm mut mt1">@if ($ringkasan['waktu_terbaik'])Waktu dengan kualitas udara lebih baik: <strong style="color:#334155">sekitar {{ $jam($ringkasan['waktu_terbaik']['time']) }} WIB</strong> (AQI {{ $ringkasan['waktu_terbaik']['aqi'] }})@else Per 2 jam, waktu setempat (WIB)@endif</p>
      @if (!empty($tren))
        <div class="bars lv-{{ $levelKey }}" role="img" aria-label="Grafik tren AQI per 2 jam hari ini">
          @foreach ($tren as $t)
            <div class="bc {{ !empty($t['is_now']) ? 'now' : '' }}"><span>{{ $t['aqi'] }}</span><u style="height:{{ max(2, round($t['aqi'] / $maxAqi * 78)) }}%"></u><span>{{ explode(':', $t['time'])[0] }}</span></div>
          @endforeach
        </div>
        <p class="mt3" style="font-size:11px;color:var(--mut2)">Batang terang menandai jam saat ini.</p>
      @else
        <div class="empty" style="height:12rem"><div><p class="b" style="color:#475569">Data tren belum tersedia</p><p class="sm mt1" style="color:var(--mut2)">Grafik akan muncul saat data tren berhasil dimuat.</p></div></div>
      @endif
    </div>
    <div class="panel min0" style="padding:1.5rem">
      <h2>Prakiraan 3 hari</h2>
      @if (!empty($prakiraan))
        <div class="mt3">
          @foreach ($prakiraan as $p)
            <div class="fr lv-{{ $lv($p['aqi_level_key'] ?? null) }}">
              <div class="min0"><p class="sm b" style="font-weight:600">{{ $p['day_label'] }}</p><p class="sm mut mt1">@if (!empty($p['condition'])){{ $p['condition'] }} · @endif @if (!is_null($p['temp_min'] ?? null) && !is_null($p['temp_max'] ?? null)){{ $p['temp_min'] }}–{{ $p['temp_max'] }}°C @endif</p></div>
              <div style="flex:none">@if (!is_null($p['aqi'] ?? null))<p class="big">{{ $p['aqi'] }}</p><small>{{ $p['aqi_level'] ?? '' }}</small>@else{!! $na !!}@endif</div>
            </div>
          @endforeach
        </div>
        <p style="font-size:11px;color:var(--mut2)">AQI adalah rata-rata harian.</p>
      @else
        <div class="empty"><div><p class="b" style="color:#475569">Prakiraan belum tersedia</p><p class="sm mt1" style="color:var(--mut2)">Data prakiraan gagal dimuat.</p></div></div>
      @endif
    </div>
  </section>

  {{-- PANDUAN --}}
  <section id="panduan" class="panel defer" style="padding:1.5rem 1.75rem">
    <h2 style="font-size:1.25rem">Apa yang sebaiknya saya lakukan?</h2><p class="sm mut mt1">Pilih kelompok yang paling sesuai dengan Anda.</p>
    <div class="tabs" id="tabs" role="tablist">
      @foreach (array_keys($panduan) as $i => $nama)<button type="button" role="tab" class="tab {{ $i === 0 ? 'on' : '' }}" data-i="{{ $i }}">{{ $nama }}</button>@endforeach
    </div>
    @foreach (array_values($panduan) as $i => $tips)
      <ul class="tips {{ $i === 0 ? 'on' : '' }}" data-tips="{{ $i }}">
        @foreach ($tips as $n => $t)<li class="soft"><span>{{ $n + 1 }}</span><span style="display:block;width:auto;height:auto;background:none;color:inherit;font-size:inherit;font-weight:400;border-radius:0">{{ $t }}</span></li>@endforeach
      </ul>
    @endforeach
  </section>

  {{-- TENTANG DATA + CARA KERJA --}}
  <section id="data" class="grid g-eq defer">
    <div class="panel min0" style="padding:1.5rem 1.75rem">
      <div style="display:flex;gap:.75rem">
        <span class="ibox" style="background:var(--riau5);color:var(--riau)"><svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 5h16v14H4z"/><path d="M8 9h8M8 13h5"/></svg></span>
        <div><p class="eyebrow">Data terbuka</p><h2 class="mt1">Informasi diperoleh dari API</h2>
        <p class="sm mut mt1" style="line-height:1.5rem">SiagaNapas tidak menyimpan data lingkungan secara manual. Data diambil dari sumber terbuka, diproses oleh Laravel, lalu ditampilkan agar lebih mudah dipahami warga.</p></div>
      </div>
      <div class="mt5" style="display:flex;flex-wrap:wrap;align-items:center;gap:.5rem;font-size:.75rem;font-weight:600;color:var(--mut)"><span class="soft" style="padding:.25rem .625rem">Data API</span>→<span class="soft" style="padding:.25rem .625rem">Laravel memproses</span>→<span class="soft" style="padding:.25rem .625rem;color:var(--riau)">SiagaNapas menampilkan</span></div>
      <div class="mt6" style="display:grid;gap:.75rem">
        @foreach ([['udara','Kualitas udara'],['cuaca','Cuaca & prakiraan'],['titik_panas','Titik panas'],['tren','Tren AQI']] as [$k, $label])
          <div class="kv"><span>{{ $label }}</span>@if (!empty($statusApi[$k]))<span style="color:#047857">✓ Tersambung</span>@else<span style="color:#b45309">✕ Gagal dimuat</span>@endif</div>
        @endforeach
      </div>
      <p class="xs mt3" style="color:var(--mut2)">Sumber: Open-Meteo (cuaca, prakiraan, kualitas udara) dan NASA FIRMS (titik panas).</p>
    </div>
    <div class="panel min0" style="padding:1.5rem 1.75rem">
      <div style="display:flex;gap:.75rem">
        <span class="ibox" style="background:#fffbeb;color:#b45309"><svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 8v4l2.5 2.5"/></svg></span>
        <div><p class="eyebrow" style="color:#b45309">Kesegaran data</p><h2 class="mt1">Seberapa baru data ini?</h2>
        <p class="sm mut mt1" style="line-height:1.5rem">Setiap sumber punya jadwal pembaruan sendiri. Waktu di bawah ini adalah data terakhir yang kami terima.</p></div>
      </div>
      <div class="mt6" style="display:grid;gap:.75rem">
        <div class="soft" style="padding:.875rem 1rem"><div style="display:flex;justify-content:space-between;gap:1rem;flex-wrap:wrap"><b style="font-size:.875rem">Kualitas udara</b><span class="xs mut">Open-Meteo · tiap jam</span></div><p class="sm mt1" style="color:#475569">{{ $updatedAt ?? 'Waktu pembaruan belum tersedia' }}</p></div>
        <div class="soft" style="padding:.875rem 1rem"><div style="display:flex;justify-content:space-between;gap:1rem;flex-wrap:wrap"><b style="font-size:.875rem">Cuaca &amp; prakiraan</b><span class="xs mut">Open-Meteo · tiap jam</span></div><p class="sm mt1" style="color:#475569">Diperbarui setiap kali halaman dimuat</p></div>
        <div class="soft" style="padding:.875rem 1rem"><div style="display:flex;justify-content:space-between;gap:1rem;flex-wrap:wrap"><b style="font-size:.875rem">Titik panas</b><span class="xs mut">NASA FIRMS · satelit</span></div><p class="sm mt1" style="color:#475569">{{ $titikTerbaru ?? 'Waktu deteksi belum tersedia' }}</p></div>
      </div>
      <p class="xs mt3" style="color:var(--mut2);line-height:1.25rem">Data satelit bisa terlambat beberapa jam dan tidak selalu menangkap setiap kebakaran. Jika Anda melihat asap atau api, hubungi pihak berwenang setempat.</p>
    </div>
  </section>

  <section class="grid g-learn defer"><div><p class="eyebrow">Pelajari singkat</p><h2 class="mt2" style="font-size:1.25rem">Kenali kualitas udara</h2><p class="sm mut mt2" style="line-height:1.5rem">Penjelasan singkat agar angka di halaman ini lebih mudah dipahami.</p></div>
    <div style="display:grid;gap:.5rem">
      <details class="panel"><summary>Apa itu AQI?</summary><p>Angka yang merangkum kondisi kualitas udara. Makin tinggi angkanya, makin perlu berhati-hati.</p></details>
      <details class="panel"><summary>Apa itu PM2.5?</summary><p>Partikel udara berukuran sangat kecil yang dapat masuk jauh ke saluran pernapasan.</p></details>
      <details class="panel"><summary>Apa arti titik panas?</summary><p>Sinyal dari satelit yang menunjukkan area dengan suhu tinggi. Titik panas perlu diperiksa lebih lanjut dan tidak selalu berarti kebakaran.</p></details>
      <details class="panel"><summary>Mengapa kualitas udara bisa memburuk?</summary><p>Asap, arah angin, cuaca, dan kondisi udara dapat memengaruhi kualitas udara di suatu wilayah.</p></details>
    </div></section>

  <section class="defer" style="border-top:1px solid #e2e8f0;padding-top:1.75rem"><div class="src src-row" style="display:flex;flex-direction:column;gap:1rem"><div><p class="eyebrow g">Transparansi</p><h2 class="mt1" style="font-size:1rem">Sumber data</h2><p class="sm mut mt1">Data diperbarui secara berkala.</p></div><div style="display:flex;flex-wrap:wrap;gap:.5rem"><span><strong>Open-Meteo</strong> · udara, cuaca & prakiraan</span><span><strong>NASA FIRMS</strong> · titik panas</span></div></div></section>
</main>

<footer><div class="wrap"><p>SiagaNapas · Informasi lingkungan untuk warga Riau</p><p>© {{ date('Y') }} SiagaNapas</p></div></footer>

<nav class="bnav" aria-label="Navigasi bawah"><ul>
  <li><a href="#" class="on"><svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m3 11 9-8 9 8v9a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1v-9Z"/></svg>Beranda</a></li>
  <li><a href="#peta"><svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s7-6.2 7-12a7 7 0 1 0-14 0c0 5.8 7 12 7 12Z"/><circle cx="12" cy="10" r="2.5"/></svg>Peta</a></li>
  <li><a href="#panduan"><svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 5a2 2 0 0 1 2-2h12v18H6a2 2 0 0 1-2-2V5Z"/><path d="M8 7h6"/></svg>Panduan</a></li>
  <li><a href="#data"><svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 5h16v14H4z"/><path d="M8 9h8M8 13h5"/></svg>Data</a></li>
</ul></nav>

<script>
(()=>{
  const $=s=>document.querySelector(s),$$=s=>document.querySelectorAll(s);
  const btn=$('#cityBtn'),menu=$('#cityMenu');
  const close=()=>{menu.classList.remove('open');btn.setAttribute('aria-expanded','false')};
  btn.addEventListener('click',e=>{e.stopPropagation();btn.setAttribute('aria-expanded',String(menu.classList.toggle('open')))});
  document.addEventListener('click',close);document.addEventListener('keydown',e=>{if(e.key==='Escape')close()});
  const wb=$('#whyBtn'),w=$('#why');
  if(wb&&w)wb.addEventListener('click',()=>{const o=w.classList.toggle('open');wb.setAttribute('aria-expanded',String(o));wb.textContent=o?'Sembunyikan alasan ↑':'Lihat alasan →'});
  $('#tabs').addEventListener('click',e=>{const b=e.target.closest('.tab');if(!b)return;
    $$('#tabs .tab').forEach(x=>x.classList.toggle('on',x===b));$$('[data-tips]').forEach(l=>l.classList.toggle('on',l.dataset.tips===b.dataset.i))});
  const m1=$('#m1'),m2=$('#m2'),m3=$('#m3');
  const D=@json($petaData);
  const el=$('#hsMap');
  let MAP=null,loading=false,waiters=[],youLayer=null;
  const initMap=()=>{
    if(!window.L||!el)return;
    const map=L.map(el,{scrollWheelZoom:false,zoomControl:true,dragging:!L.Browser.mobile}).setView([D.lat??0.5,D.lon??101.5],9);
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:18,attribution:'&copy; OpenStreetMap'}).addTo(map);
    const ic=c=>L.divIcon({className:'mkc '+c,html:'<span></span>',iconSize:[16,16],iconAnchor:[8,8]});
    window._mkIcon=ic;
    const pts=[];
    if(D.lat!=null&&D.lon!=null){L.marker([D.lat,D.lon],{icon:ic('me'),zIndexOffset:1000}).addTo(map).bindTooltip(D.kota,{permanent:true,direction:'right',offset:[12,0]});pts.push([D.lat,D.lon])}
    D.pts.forEach((p,i)=>{
      const c={tinggi:'t',sedang:'s'}[p.c]||'r';
      const mk=L.marker([p.la,p.lo],{icon:ic(c)}).addTo(map);
      mk.on('click',()=>{if(!m1)return;m1.textContent=(i===0?'Titik terdekat: ':'Titik panas: ')+p.d+' km';m2.textContent=p.a+' · '+p.l;m3.textContent=p.t+' · FRP '+p.f+' MW · kepercayaan '+p.c});
      if(i<40)pts.push([p.la,p.lo]);
    });
    if(pts.length>1)map.fitBounds(pts,{padding:[30,30],maxZoom:11});
    el.addEventListener('click',()=>map.scrollWheelZoom.enable(),{once:true});
    setTimeout(()=>map.invalidateSize(),300);
    MAP=map;waiters.forEach(f=>f(map));waiters=[];
  };
  const loadLeaflet=()=>{
    if(loading)return;loading=true;
    const ls=document.createElement('script');ls.src='https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';ls.integrity='sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=';ls.crossOrigin='';ls.onload=initMap;
    ls.onerror=()=>{loading=false};document.head.appendChild(ls);
  };
  const whenMap=()=>new Promise(res=>{if(MAP)return res(MAP);waiters.push(res);loadLeaflet()});
  if('IntersectionObserver' in window&&el){const io=new IntersectionObserver(en=>{if(en[0].isIntersecting){io.disconnect();loadLeaflet()}},{rootMargin:'400px'});io.observe(el)}else loadLeaflet();

  /* Lokasi saya: dihitung di browser, koordinat tidak dikirim ke server */
  const lb=$('#locBtn'),lm=$('#locMsg');
  const rad=x=>x*Math.PI/180;
  const dist=(a,b,c,d)=>{const R=6371,dl=rad(c-a),dn=rad(d-b),h=Math.sin(dl/2)**2+Math.cos(rad(a))*Math.cos(rad(c))*Math.sin(dn/2)**2;return 2*R*Math.asin(Math.sqrt(h))};
  const arah=(a,b,c,d)=>{const y=Math.sin(rad(d-b))*Math.cos(rad(c)),x=Math.cos(rad(a))*Math.sin(rad(c))-Math.sin(rad(a))*Math.cos(rad(c))*Math.cos(rad(d-b));const br=(Math.atan2(y,x)*180/Math.PI+360)%360;return ['utara','timur laut','timur','tenggara','selatan','barat daya','barat','barat laut'][Math.round(br/45)%8]};
  const inRiau=(la,lo)=>la>-1.3&&la<2.7&&lo>99.9&&lo<104.7;
  const say=t=>{if(lm)lm.textContent=t};
  const reset=()=>{lb.disabled=false;lb.lastChild.textContent='Lokasi saya'};
  if(lb)lb.addEventListener('click',()=>{
    if(!navigator.geolocation){say('Browser kamu tidak mendukung fitur lokasi.');return}
    lb.disabled=true;lb.lastChild.textContent='Mencari lokasi…';say('Menunggu izin lokasi…');
    navigator.geolocation.getCurrentPosition(async pos=>{
      const la=pos.coords.latitude,lo=pos.coords.longitude;
      if(!inRiau(la,lo)){say('Lokasimu berada di luar wilayah Riau, jadi tidak ditampilkan di peta ini.');reset();return}
      const map=await whenMap();
      if(youLayer)map.removeLayer(youLayer);
      youLayer=L.marker([la,lo],{icon:window._mkIcon('you'),zIndexOffset:2000}).addTo(map).bindTooltip('Lokasi kamu',{permanent:true,direction:'right',offset:[12,0]});
      const lg=$('#legYou');if(lg)lg.hidden=false;
      if(!D.pts.length){say('Lokasimu ditandai. Tidak ada titik panas yang bisa dibandingkan.');map.setView([la,lo],11);reset();return}
      let best=null,bd=Infinity;
      D.pts.forEach(p=>{const d=dist(la,lo,p.la,p.lo);if(d<bd){bd=d;best=p}});
      const km=bd<10?bd.toFixed(1):Math.round(bd);
      if(m1){m1.textContent='Titik terdekat dari lokasimu: '+km+' km';m2.textContent=arah(la,lo,best.la,best.lo).replace(/^./,c=>c.toUpperCase())+' · '+best.l;m3.textContent=best.t+' · FRP '+best.f+' MW · kepercayaan '+best.c}
      L.polyline([[la,lo],[best.la,best.lo]],{color:'#7c3aed',weight:2,dashArray:'6 6'}).addTo(map);
      map.fitBounds([[la,lo],[best.la,best.lo]],{padding:[40,40],maxZoom:12});
      say('Dihitung dari titik panas yang ditampilkan di peta (maks. 150 titik terdekat dari '+D.kota+').');
      reset();
    },err=>{
      say(err.code===1?'Izin lokasi ditolak. Peta tetap memakai pusat '+D.kota+'. Kamu bisa mengaktifkannya lewat pengaturan situs di browser.':err.code===3?'Pencarian lokasi terlalu lama. Coba lagi.':'Lokasi tidak dapat ditemukan saat ini.');
      reset();
    },{enableHighAccuracy:false,timeout:10000,maximumAge:60000});
  });
})();
</script>
</body>
</html>