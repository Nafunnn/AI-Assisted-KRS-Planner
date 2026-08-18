<script setup lang="ts">
import { Form, Head, Link } from '@inertiajs/vue3';
import { store as storePlan } from '@/actions/App/Http/Controllers/Krs/KrsPlanController';
import OfferingImportDialog from '@/components/krs/OfferingImportDialog.vue';
import { Button } from '@/components/ui/button';
import { index as krsIndex, planner } from '@/routes/krs';
import type { OfferingListItem } from '@/types/krs';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'KRS Planner', href: krsIndex() }],
    },
});

const { offerings } = defineProps<{
    offerings: OfferingListItem[];
}>();
</script>

<template>
    <Head title="KRS Planner" />

    <div class="flex h-full flex-1 flex-col gap-4 p-3 sm:p-4">
        <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
            <div class="min-w-0">
                <h1 class="text-xl font-semibold">KRS Planner</h1>
                <p class="text-sm text-muted-foreground">
                    Import penawaran mata kuliah dan susun jadwal KRS
                </p>
            </div>
            <OfferingImportDialog />
        </div>

        <div
            v-if="offerings.length === 0"
            class="flex flex-1 flex-col items-center justify-center rounded-xl border border-dashed p-8 text-center"
        >
            <h2 class="text-lg font-medium">Belum ada penawaran</h2>
            <p class="mt-2 max-w-md text-sm text-muted-foreground">
                Import file Excel penawaran mata kuliah untuk mulai menyusun
                jadwal KRS.
            </p>
            <div class="mt-4">
                <OfferingImportDialog />
            </div>
        </div>

        <div v-else class="grid gap-3">
            <div
                v-for="offering in offerings"
                :key="offering.id"
                class="rounded-lg border bg-card p-4"
            >
                <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-start sm:justify-between">
                    <div class="min-w-0">
                        <h2 class="font-medium break-words">{{ offering.title }}</h2>
                        <p class="text-sm text-muted-foreground break-all">
                            {{ offering.courses_count }} mata kuliah ·
                            {{ offering.source_filename }}
                        </p>
                    </div>
                    <Form v-bind="storePlan.form({ offering: offering.id })">
                        <Button type="submit" variant="outline" size="sm" class="min-h-11 w-full sm:min-h-8 sm:w-auto">
                            Plan baru
                        </Button>
                    </Form>
                </div>

                <ul class="mt-3 grid gap-2">
                    <li
                        v-for="plan in offering.plans"
                        :key="plan.id"
                        class="flex items-center justify-between gap-3 rounded-md border bg-background px-3 py-2"
                    >
                        <div class="min-w-0">
                            <p class="truncate text-sm font-medium">{{ plan.name }}</p>
                            <p class="text-xs text-muted-foreground">
                                {{ plan.items_count }} kelompok dipilih
                            </p>
                        </div>
                        <Button as-child size="sm" class="min-h-11 shrink-0 sm:min-h-8">
                            <Link
                                :href="
                                    planner({
                                        offering: offering.id,
                                        plan: plan.id,
                                    })
                                "
                            >
                                Buka
                            </Link>
                        </Button>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</template>
