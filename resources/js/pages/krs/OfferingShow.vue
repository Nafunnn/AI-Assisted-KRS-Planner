<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { index as krsIndex } from '@/routes/krs';
import { latest } from '@/routes/krs/planner';
import type { CourseOffering } from '@/types/krs';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'KRS Planner', href: krsIndex() },
            { title: 'Detail Penawaran' },
        ],
    },
});

const { offering } = defineProps<{
    offering: CourseOffering;
}>();
</script>

<template>
    <Head :title="offering.title" />

    <div class="flex flex-col gap-4 p-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold">{{ offering.title }}</h1>
                <p class="text-sm text-muted-foreground">
                    {{ offering.courses.length }} mata kuliah diimpor
                </p>
            </div>
            <Button as-child>
                <Link :href="latest({ offering: offering.id })">
                    Buka Planner
                </Link>
            </Button>
        </div>

        <div class="overflow-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead class="bg-muted/40 text-left">
                    <tr>
                        <th class="px-3 py-2">Kode</th>
                        <th class="px-3 py-2">Mata Kuliah</th>
                        <th class="px-3 py-2">SKS</th>
                        <th class="px-3 py-2">Kelompok</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="course in offering.courses"
                        :key="course.id"
                        class="border-t"
                    >
                        <td class="px-3 py-2 font-medium">{{ course.code }}</td>
                        <td class="px-3 py-2">{{ course.name }}</td>
                        <td class="px-3 py-2">{{ course.sks }}</td>
                        <td class="px-3 py-2">
                            {{ course.sections.length }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
