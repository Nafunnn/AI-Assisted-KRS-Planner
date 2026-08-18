<?php

use App\Models\SectionSchedule;
use App\Policies\CourseOfferingPolicy;

return [
    'key' => 'section_schedule',
    'name' => 'Jadwal Kelompok',
    'model' => SectionSchedule::class,
    'description' => 'Slot jadwal kuliah untuk satu kelompok (course_section). Satu kelompok bisa punya beberapa slot (mis. Senin 08-10 dan Rabu 13-15). Field day, starts_at, ends_at dipakai deteksi bentrok; raw menyimpan teks jadwal asli dari Excel.',
    'policy' => CourseOfferingPolicy::class,
    'permission_action' => 'list',
    'scope' => 'section_owner',
    'searchable' => ['raw'],
    'filterable' => ['id', 'course_section_id', 'slot_number', 'day', 'starts_at', 'ends_at'],
    'sortable' => ['slot_number', 'day', 'starts_at', 'ends_at'],
    'aggregates' => ['count'],
    'relations' => [
        'courseSection' => 'course_section',
    ],
    'hidden' => [],
    'fields' => [
        'id' => [
            'type' => 'integer',
            'label' => 'ID',
        ],
        'course_section_id' => [
            'type' => 'integer',
            'label' => 'Kelompok',
            'description' => 'FK ke course_section.',
        ],
        'slot_number' => [
            'type' => 'integer',
            'label' => 'Slot',
            'description' => 'Urutan slot jadwal dalam kelompok (1, 2, ...).',
            'example' => '1',
        ],
        'day' => [
            'type' => 'enum',
            'label' => 'Hari',
            'description' => 'Nama hari dalam bahasa Inggris lowercase.',
            'values' => [
                'monday' => 'Senin',
                'tuesday' => 'Selasa',
                'wednesday' => 'Rabu',
                'thursday' => 'Kamis',
                'friday' => 'Jumat',
                'saturday' => 'Sabtu',
                'sunday' => 'Minggu',
            ],
        ],
        'starts_at' => [
            'type' => 'time',
            'label' => 'Mulai',
            'description' => 'Jam mulai (format H:i:s).',
            'example' => '08:00:00',
        ],
        'ends_at' => [
            'type' => 'time',
            'label' => 'Selesai',
            'description' => 'Jam selesai (format H:i:s).',
            'example' => '10:00:00',
        ],
        'raw' => [
            'type' => 'string',
            'label' => 'Teks Asli',
            'description' => 'Representasi jadwal mentah dari impor Excel.',
            'example' => 'Senin 08:00-10:00',
        ],
        'created_at' => [
            'type' => 'datetime',
            'label' => 'Dibuat',
        ],
        'updated_at' => [
            'type' => 'datetime',
            'label' => 'Diperbarui',
        ],
    ],
    'business_rules' => [
        'Bentrok terjadi jika dua kelompok berbeda punya overlap waktu pada hari yang sama.',
        'Overlap dihitung server-side oleh ScheduleConflictDetector, bukan AI.',
        'Satu kelompok dengan beberapa slot: semua slot ikut dievaluasi saat deteksi bentrok.',
    ],
    'query_hints' => [
        'Filter course_section_id untuk melihat jadwal satu kelompok.',
        'Filter day=monday (dst.) untuk analisis per hari.',
        'Lebih praktis query course_section with schedules daripada query section_schedule langsung.',
    ],
];
