<?php

use App\Models\KrsPlanItem;
use App\Policies\KrsPlanPolicy;

return [
    'key' => 'krs_plan_item',
    'name' => 'Item Rencana KRS',
    'model' => KrsPlanItem::class,
    'description' => 'Satu baris pilihan kelompok mata kuliah dalam rencana KRS. Menghubungkan krs_plan dengan course_section. Create/delete item = tambah/hapus mata kuliah dari jadwal. AI dapat create/delete entity ini; validasi bentrok dilakukan server-side.',
    'policy' => KrsPlanPolicy::class,
    'permission_action' => 'list',
    'scope' => 'plan_owner',
    'searchable' => [],
    'filterable' => ['id', 'krs_plan_id', 'course_section_id'],
    'sortable' => ['id', 'created_at'],
    'aggregates' => ['count'],
    'relations' => [
        'krsPlan' => 'krs_plan',
        'courseSection' => 'course_section',
    ],
    'hidden' => [],
    'fields' => [
        'id' => [
            'type' => 'integer',
            'label' => 'ID',
        ],
        'krs_plan_id' => [
            'type' => 'integer',
            'label' => 'Rencana KRS',
            'description' => 'FK ke krs_plan induk.',
            'required' => true,
        ],
        'course_section_id' => [
            'type' => 'integer',
            'label' => 'Kelompok Terpilih',
            'description' => 'FK ke course_section yang dipilih mahasiswa.',
            'required' => true,
        ],
        'created_at' => [
            'type' => 'datetime',
            'label' => 'Ditambahkan',
        ],
        'updated_at' => [
            'type' => 'datetime',
            'label' => 'Diperbarui',
        ],
    ],
    'business_rules' => [
        'Kombinasi krs_plan_id + course_section_id unik (satu kelompok tidak boleh duplikat).',
        'Tidak boleh dua item dengan course_id sama dalam satu rencana (kelompok berbeda dari MK sama).',
        'Create via CreateEntityTool: { krs_plan_id, course_section_id }.',
        'Delete via DeleteEntityTool dengan id item.',
        'Untuk mengganti banyak kelompok sekaligus, prefer SyncPlanSectionsTool.',
    ],
    'query_hints' => [
        'Filter krs_plan_id untuk melihat semua mata kuliah terpilih.',
        'Gunakan with=["courseSection","courseSection.course","courseSection.schedules"] untuk detail jadwal per item.',
    ],
    'computed' => [
        'course_code' => 'Via courseSection.course.code.',
        'course_name' => 'Via courseSection.course.name.',
        'course_sks' => 'Via courseSection.course.sks.',
        'group_code' => 'Via courseSection.group_code.',
    ],
];
