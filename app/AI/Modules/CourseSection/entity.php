<?php

use App\Models\CourseSection;
use App\Policies\CourseOfferingPolicy;

return [
    'key' => 'course_section',
    'name' => 'Kelompok Mata Kuliah',
    'model' => CourseSection::class,
    'description' => 'Kelompok/kelas spesifik dari satu mata kuliah. Ini unit yang dipilih mahasiswa saat menyusun KRS (via krs_plan_item). Setiap kelompok punya kode kelompok, periode waktu (Pagi/Malam), dan satu atau lebih slot jadwal (section_schedule). Bentrok jadwal dievaluasi antar kelompok yang dipilih.',
    'policy' => CourseOfferingPolicy::class,
    'permission_action' => 'list',
    'scope' => 'published_course',
    'searchable' => ['group_code'],
    'filterable' => ['id', 'course_id', 'group_code', 'time_period'],
    'sortable' => ['group_code', 'time_period', 'created_at'],
    'aggregates' => ['count'],
    'relations' => [
        'course' => 'course',
        'schedules' => 'section_schedule',
    ],
    'hidden' => [],
    'fields' => [
        'id' => [
            'type' => 'integer',
            'label' => 'ID',
            'description' => 'ID kelompok; dipakai saat create krs_plan_item.',
        ],
        'course_id' => [
            'type' => 'integer',
            'label' => 'Mata Kuliah',
            'description' => 'FK ke course induk.',
        ],
        'group_code' => [
            'type' => 'string',
            'label' => 'Kode Kelompok',
            'description' => 'Kode kelas/kelompok dalam mata kuliah.',
            'example' => 'A',
        ],
        'time_period' => [
            'type' => 'enum',
            'label' => 'Periode',
            'description' => 'P = Pagi, M = Malam, PM = Pagi/Malam.',
            'values' => [
                'P' => 'Pagi',
                'M' => 'Malam',
                'PM' => 'Pagi/Malam',
            ],
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
        'Hanya satu kelompok per mata kuliah (course_id) yang boleh ada dalam satu rencana KRS.',
        'Bentrok ditentukan oleh overlap jadwal (section_schedule) pada hari dan jam yang sama.',
        'Ganti kelompok = hapus krs_plan_item lama lalu buat baru, atau gunakan SyncPlanSectionsTool.',
    ],
    'query_hints' => [
        'Selalu eager-load schedules saat menganalisis jadwal: with=["course","schedules"].',
        'Filter course_id untuk melihat semua kelompok satu mata kuliah.',
        'Untuk saran kelompok tanpa bentrok, gunakan SuggestPlanSectionsTool rather than manual guess.',
    ],
    'computed' => [
        'course_code' => 'Tersedia via relasi course.code.',
        'course_name' => 'Tersedia via relasi course.name.',
        'course_sks' => 'Tersedia via relasi course.sks.',
    ],
];
