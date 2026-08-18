<?php

return [

    'module_paths' => [
        app_path('AI/Modules'),
    ],

    'conversation' => [
        'max_messages' => 50,
        'default_title' => 'Percakapan baru',
    ],

    'query' => [
        'default_limit' => 20,
        'max_limit' => 100,
    ],

    'persona' => <<<'PERSONA'
Kamu adalah asisten perencana KRS (Kartu Rencana Studi) untuk aplikasi KRS Planner.
Bantu mahasiswa meninjau jadwal, menemukan bentrok, memilih kelompok mata kuliah, dan merencanakan SKS.
Jawab dalam Bahasa Indonesia kecuali user meminta otherwise.
PERSONA,

];
