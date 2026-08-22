<?php

use App\Models\Course;
use App\Policies\CourseOfferingPolicy;

return [
    'key' => 'course',
    'name' => 'Mata Kuliah',
    'model' => Course::class,
    'description' => 'Mata kuliah dalam satu penawaran semester. Identitas unik per penawaran adalah kode (code). Setiap mata kuliah punya SKS, jenis kelas (Teori/Praktikum), dan satu atau lebih kelompok (course_section) yang bisa dipilih di KRS.',
    'policy' => CourseOfferingPolicy::class,
    'permission_action' => 'list',
    'scope' => 'published_offering',
    'searchable' => ['code', 'name'],
    'filterable' => ['id', 'course_offering_id', 'code', 'name', 'sks', 'class_type'],
    'sortable' => ['code', 'name', 'sks', 'class_type', 'created_at'],
    'aggregates' => ['count', 'sum', 'avg', 'min', 'max'],
    'relations' => [
        'courseOffering' => 'course_offering',
        'sections' => 'course_section',
    ],
    'hidden' => [],
    'fields' => [
        'id' => [
            'type' => 'integer',
            'label' => 'ID',
        ],
        'course_offering_id' => [
            'type' => 'integer',
            'label' => 'Penawaran',
            'description' => 'FK ke course_offering tempat mata kuliah ini berada.',
        ],
        'code' => [
            'type' => 'string',
            'label' => 'Kode MK',
            'description' => 'Kode mata kuliah unik dalam satu penawaran.',
            'example' => 'IF101',
        ],
        'name' => [
            'type' => 'string',
            'label' => 'Nama MK',
            'description' => 'Nama lengkap mata kuliah.',
            'example' => 'Algoritma dan Pemrograman',
        ],
        'sks' => [
            'type' => 'integer',
            'label' => 'SKS',
            'description' => 'Satuan Kredit Semester. Dihitung unik per kode saat total SKS rencana KRS.',
            'example' => '3',
        ],
        'class_type' => [
            'type' => 'enum',
            'label' => 'Jenis Kelas',
            'description' => 'T = Teori, P = Praktikum.',
            'values' => [
                'T' => 'Teori',
                'P' => 'Praktikum',
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
        'Kode mata kuliah unik per course_offering_id.',
        'SKS dihitung sekali per kode mata kuliah dalam satu rencana KRS, meskipun ada Teori dan Praktikum terpisah.',
        'Mahasiswa memilih kelompok (course_section), bukan langsung course.',
    ],
    'query_hints' => [
        'Cari mata kuliah: search by code atau name.',
        'Gunakan with=["sections","sections.schedules"] untuk alternatif kelompok dan jadwal.',
        'Aggregate sum/avg pada field sks untuk analisis beban SKS penawaran.',
        'Filter class_type=T atau P untuk membedakan teori vs praktikum.',
    ],
    'computed' => [
        'section_count' => 'Jumlah kelompok: aggregate count course_section dengan filter course_id.',
    ],
];
