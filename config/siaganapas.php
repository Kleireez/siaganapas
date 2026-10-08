<?php

return [

    'default_city' => 'pekanbaru',

    // 12 kabupaten/kota Riau (tanpa database).
    // Koordinat = perkiraan pusat kota / ibu kota kabupaten. Verifikasi di Google Maps bila perlu.
    'cities' => [
        'pekanbaru' => ['nama' => 'Pekanbaru',         'jenis' => 'Kota', 'lat' => 0.5071,  'lon' => 101.4478],
        'dumai' => ['nama' => 'Dumai',             'jenis' => 'Kota', 'lat' => 1.6665,  'lon' => 101.4471],
        'bengkalis' => ['nama' => 'Bengkalis',         'jenis' => 'Kab.', 'lat' => 1.4750,  'lon' => 102.0950],
        'kampar' => ['nama' => 'Kampar',            'jenis' => 'Kab.', 'lat' => 0.3333,  'lon' => 101.0333],
        'indragiri-hilir' => ['nama' => 'Indragiri Hilir',   'jenis' => 'Kab.', 'lat' => -0.3167, 'lon' => 103.1500],
        'indragiri-hulu' => ['nama' => 'Indragiri Hulu',    'jenis' => 'Kab.', 'lat' => -0.3833, 'lon' => 102.5167],
        'kepulauan-meranti' => ['nama' => 'Kepulauan Meranti', 'jenis' => 'Kab.', 'lat' => 1.0500,  'lon' => 102.7167],
        'kuantan-singingi' => ['nama' => 'Kuantan Singingi',  'jenis' => 'Kab.', 'lat' => -0.5333, 'lon' => 101.5500],
        'pelalawan' => ['nama' => 'Pelalawan',         'jenis' => 'Kab.', 'lat' => 0.4000,  'lon' => 101.8300],
        'rokan-hilir' => ['nama' => 'Rokan Hilir',       'jenis' => 'Kab.', 'lat' => 2.1557,  'lon' => 100.8038],
        'rokan-hulu' => ['nama' => 'Rokan Hulu',        'jenis' => 'Kab.', 'lat' => 0.8667,  'lon' => 100.3667],
        'siak' => ['nama' => 'Siak',              'jenis' => 'Kab.', 'lat' => 0.7918,  'lon' => 102.0426],
    ],

];
