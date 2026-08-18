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

    <div class="flex flex-col gap-4 p-3 sm:p-4">
        <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
            <div class="min-w-0">
                <h1 class="text-xl font-semibold break-words">{{ offering.title }}</h1>
                <p class="text-sm text-muted-foreground">
                    {{ offering.courses.length }} mata kuliah diimpor
                </p>
            </div>
            <Button as-child class="min-h-11 sm:min-h-9">
                <Link :href="latest({ offering: offering.id })">
                    Buka Planner
                </Link>
            </Button>
        </div>

        <div class="grid gap-2 md:hidden">
            <div
                v-for="course in offering.courses"
                :key="course.id"
                class="rounded-lg border bg-card p-3"
            >
                <p class="font-medium">{{ course.code }}</p>
                <p class="text-sm text-muted-foreground">{{ course.name }}</p>
                <p class="mt-1 text-xs text-muted-foreground">
                    {{ course.sks }} SKS · {{ course.sections.length }} kelompok
                </p>
            </div>
        </div>

        <div class="hidden overflow-auto rounded-lg border md:block">
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
