<?php

use App\Models\CourseOffering;
use App\Policies\CourseOfferingPolicy;

return [
    'key' => 'course_offering',
    'name' => 'Penawaran Mata Kuliah',
    'model' => CourseOffering::class,
    'description' => 'Katalog semester mata kuliah yang diimpor user dari file Excel. Satu penawaran berisi banyak mata kuliah (course), masing-masing punya beberapa kelompok (course_section) beserta jadwal. Semua rencana KRS (krs_plan) terikat ke satu penawaran.',
    'policy' => CourseOfferingPolicy::class,
    'permission_action' => 'list',
    'scope' => 'owner',
    'scope_field' => 'user_id',
    'searchable' => ['title', 'source_filename'],
    'filterable' => ['id', 'user_id', 'title', 'source_filename'],
    'sortable' => ['title', 'imported_at', 'created_at', 'updated_at'],
    'aggregates' => ['count'],
    'relations' => [
        'user' => 'user',
        'courses' => 'course',
        'krsPlans' => 'krs_plan',
        'latestPlan' => 'krs_plan',
    ],
    'hidden' => [],
    'fields' => [
        'id' => [
            'type' => 'integer',
            'label' => 'ID',
            'description' => 'Primary key penawaran.',
        ],
        'user_id' => [
            'type' => 'integer',
            'label' => 'Pemilik',
            'description' => 'User yang mengimpor penawaran ini.',
        ],
        'title' => [
            'type' => 'string',
            'label' => 'Judul',
            'description' => 'Nama penawaran semester, biasanya dari judul impor Excel.',
            'example' => 'KRS Semester Genap 2025/2026',
        ],
        'source_filename' => [
            'type' => 'string',
            'label' => 'File Sumber',
            'description' => 'Nama file Excel asli yang diimpor.',
            'example' => 'penawaran_mk.xlsx',
        ],
        'imported_at' => [
            'type' => 'datetime',
            'label' => 'Diimpor Pada',
            'description' => 'Waktu impor Excel terakhir.',
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
        'User hanya melihat penawaran miliknya sendiri (scope owner).',
        'Satu user dapat punya banyak penawaran untuk semester berbeda.',
        'Menghapus penawaran akan memengaruhi course, section, dan rencana terkait.',
    ],
    'query_hints' => [
        'Gunakan with=["courses","krsPlans"] untuk ringkasan isi penawaran.',
        'Filter id penawaran aktif sering ada di business_context.active_offering_id saat chat dari Planner.',
    ],
    'computed' => [
        'course_count' => 'Jumlah mata kuliah: aggregate count pada entity course dengan filter course_offering_id.',
        'plan_count' => 'Jumlah rencana KRS: aggregate count pada entity krs_plan dengan filter course_offering_id.',
    ],
    'admin_routes' => [
        'show' => ['route' => 'krs.planner.latest', 'param' => 'offering', 'label' => 'Buka Planner'],
    ],
    'admin_location' => [
        'show' => 'KRS → Planner',
    ],
];
