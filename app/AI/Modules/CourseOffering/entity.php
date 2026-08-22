<?php

use App\Models\CourseOffering;
use App\Policies\CourseOfferingPolicy;

return [
    'key' => 'course_offering',
    'name' => 'Penawaran Mata Kuliah',
    'model' => CourseOffering::class,
    'description' => 'Katalog semester bersama yang diunggah admin. Satu penawaran berisi banyak mata kuliah (course), masing-masing punya beberapa kelompok (course_section) beserta jadwal. Semua rencana KRS (krs_plan) mahasiswa terikat ke satu katalog.',
    'policy' => CourseOfferingPolicy::class,
    'permission_action' => 'list',
    'scope' => 'published_catalog',
    'searchable' => ['title', 'source_filename', 'term'],
    'filterable' => ['id', 'title', 'term', 'source_filename', 'catalog_version'],
    'sortable' => ['title', 'term', 'imported_at', 'published_at', 'created_at', 'updated_at'],
    'aggregates' => ['count'],
    'relations' => [
        'uploadedBy' => 'user',
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
        'uploaded_by_user_id' => [
            'type' => 'integer',
            'label' => 'Diunggah Oleh',
            'description' => 'Admin yang terakhir mengunggah/sync katalog.',
        ],
        'title' => [
            'type' => 'string',
            'label' => 'Judul',
            'description' => 'Nama katalog semester.',
            'example' => 'KRS Semester Genap 2025/2026',
        ],
        'term' => [
            'type' => 'string',
            'label' => 'Semester',
            'description' => 'Kode term, misalnya 2025/2026-genap.',
        ],
        'source_filename' => [
            'type' => 'string',
            'label' => 'File Sumber',
            'description' => 'Nama file Excel asli yang diimpor.',
            'example' => 'penawaran_mk.xlsx',
        ],
        'catalog_version' => [
            'type' => 'integer',
            'label' => 'Versi Katalog',
        ],
        'imported_at' => [
            'type' => 'datetime',
            'label' => 'Diimpor Pada',
            'description' => 'Waktu impor/sync Excel terakhir.',
        ],
        'published_at' => [
            'type' => 'datetime',
            'label' => 'Dipublish Pada',
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
        'Mahasiswa hanya melihat katalog yang sudah dipublish.',
        'Admin dapat melihat semua katalog termasuk unpublished.',
        'Hanya admin yang boleh membuat atau sync katalog.',
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
