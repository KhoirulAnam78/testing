<?php

return [
    'database' => env('CBT_DB_DATABASE', 'sistembl_cbt_fk'),

    'tables' => [
        'ujian' => env('CBT_TABLE_UJIAN', 'ujian'),
        'peserta_ujian' => env('CBT_TABLE_PESERTA_UJIAN', 'peserta_ujian'),
        'paket_soal' => env('CBT_TABLE_PAKET_SOAL', 'paket_soal'),
        'paket_has_soal' => env('CBT_TABLE_PAKET_HAS_SOAL', 'paket_has_soal'),
        'pilgan_jawab' => env('CBT_TABLE_PILGAN_JAWAB', 'pilgan_jawab'),
        'jenis_ujian' => env('CBT_TABLE_JENIS_UJIAN', 'jenis_ujian'),
        'kategori_ujian' => env('CBT_TABLE_KATEGORI_UJIAN', 'kategori_ujian'),
    ],
];
