<?php

use App\Models\KrsPlan;
use App\Policies\KrsPlanPolicy;

return [
    'key' => 'krs_plan',
    'name' => 'Rencana KRS',
    'model' => KrsPlan::class,
    'description' => 'Rencana KRS mahasiswa untuk satu penawaran semester. Berisi daftar kelompok terpilih (krs_plan_item). Status draft/final; nama bebas. Total SKS dihitung unik per kode mata kuliah dari item terpilih.',
    'policy' => KrsPlanPolicy::class,
    'permission_action' => 'list',
    'scope' => 'owner',
    'scope_field' => 'user_id',
    'searchable' => ['name'],
    'filterable' => ['id', 'user_id', 'course_offering_id', 'name', 'status'],
    'sortable' => ['name', 'status', 'created_at', 'updated_at'],
    'aggregates' => ['count'],
    'relations' => [
        'user' => 'user',
        'courseOffering' => 'course_offering',
        'items' => 'krs_plan_item',
    ],
    'hidden' => [],
    'fields' => [
        'id' => [
            'type' => 'integer',
            'label' => 'ID',
            'description' => 'ID rencana; sering ada di business_context.active_plan_id.',
        ],
        'user_id' => [
            'type' => 'integer',
            'label' => 'Pemilik',
            'description' => 'User pemilik rencana KRS.',
        ],
        'course_offering_id' => [
            'type' => 'integer',
            'label' => 'Penawaran',
            'description' => 'Penawaran semester yang menjadi sumber mata kuliah.',
        ],
        'name' => [
            'type' => 'string',
            'label' => 'Nama Rencana',
            'description' => 'Label rencana, bisa banyak per penawaran.',
            'example' => 'Rencana KRS 1',
        ],
        'status' => [
            'type' => 'enum',
            'label' => 'Status',
            'description' => 'draft = masih disusun, final = sudah final (informasi saja).',
            'values' => [
                'draft' => 'Draft',
                'final' => 'Final',
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
        'User hanya mengelola rencana miliknya (scope owner).',
        'Semua item rencana harus merujuk ke course_section dalam penawaran yang sama.',
        'Maksimal satu kelompok per mata kuliah dalam satu rencana.',
        'Update name/status via AI; tambah/hapus kelompok via krs_plan_item atau domain tools.',
    ],
    'query_hints' => [
        'Gunakan with=["items","items.courseSection","items.courseSection.course","items.courseSection.schedules"] untuk review jadwal lengkap.',
        'Atau gunakan DetectPlanConflictsTool untuk ringkasan bentrok + SKS resmi.',
        'Filter course_offering_id untuk rencana dalam satu semester.',
    ],
    'computed' => [
        'total_sks' => 'Total SKS unik per kode mata kuliah; gunakan DetectPlanConflictsTool atau KrsPlanItemSyncer summary.',
        'course_count' => 'Jumlah mata kuliah unik terpilih.',
        'has_conflicts' => 'Apakah ada bentrok jadwal; gunakan DetectPlanConflictsTool.',
    ],
    'admin_routes' => [
        'show' => ['route' => 'krs.planner', 'param' => 'plan', 'label' => 'Buka Planner'],
    ],
    'admin_location' => [
        'show' => 'KRS → Planner',
    ],
];
