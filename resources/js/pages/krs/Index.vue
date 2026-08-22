<script setup lang="ts">
import { Form, Head, Link, router, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import KrsPlanCompareController from '@/actions/App/Http/Controllers/Krs/KrsPlanCompareController';
import { store as storePlan } from '@/actions/App/Http/Controllers/Krs/KrsPlanController';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { index as krsIndex, planner } from '@/routes/krs';
import { latest as plannerLatest } from '@/routes/krs/planner';
import { index as adminOfferings } from '@/routes/krs/admin/offerings';
import type { OfferingListItem, PlanSummary } from '@/types/krs';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'KRS Planner', href: krsIndex() }],
    },
});

const { offerings } = defineProps<{
    offerings: OfferingListItem[];
}>();

const page = usePage();
const isAdmin = Boolean(page.props.auth.user?.is_admin);

const compareOpen = ref(false);
const comparePlansList = ref<PlanSummary[]>([]);
const planAId = ref<number | null>(null);
const planBId = ref<number | null>(null);

function openCompare(plans: PlanSummary[]): void {
    comparePlansList.value = plans;
    planAId.value = plans[0]?.id ?? null;
    planBId.value = plans[1]?.id ?? plans[0]?.id ?? null;
    compareOpen.value = true;
}

function goCompare(): void {
    if (!planAId.value || !planBId.value || planAId.value === planBId.value) {
        return;
    }

    router.get(
        KrsPlanCompareController.url({
            query: {
                plan_a: planAId.value,
                plan_b: planBId.value,
            },
        }),
    );
}
</script>

<template>
    <Head title="KRS Planner" />

    <div class="flex h-full flex-1 flex-col gap-4 p-3 sm:p-4">
        <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
            <div class="min-w-0">
                <h1 class="text-xl font-semibold">KRS Planner</h1>
                <p class="text-sm text-muted-foreground">
                    Pilih katalog semester dan susun jadwal KRS Anda
                </p>
            </div>
            <Button
                v-if="isAdmin"
                as-child
                variant="outline"
                class="min-h-11 w-full sm:min-h-9 sm:w-auto"
            >
                <Link :href="adminOfferings()">Kelola Katalog</Link>
            </Button>
        </div>

        <div
            v-if="offerings.length === 0"
            class="flex flex-1 flex-col items-center justify-center rounded-xl border border-dashed p-8 text-center"
        >
            <h2 class="text-lg font-medium">Belum ada katalog</h2>
            <p class="mt-2 max-w-md text-sm text-muted-foreground">
                Katalog semester belum dipublish. Tunggu admin mengunggah
                penawaran mata kuliah.
            </p>
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
                            {{ offering.term }} · {{ offering.courses_count }}
                            mata kuliah · v{{ offering.catalog_version }}
                        </p>
                    </div>
                    <div class="flex flex-col gap-2 sm:flex-row">
                        <Button
                            v-if="offering.plans.length === 0"
                            as-child
                            size="sm"
                            class="min-h-11 w-full sm:min-h-8 sm:w-auto"
                        >
                            <Link :href="plannerLatest(offering.id)">
                                Mulai Rencana
                            </Link>
                        </Button>
                        <Button
                            v-if="offering.plans.length >= 2"
                            size="sm"
                            variant="outline"
                            class="min-h-11 w-full sm:min-h-8 sm:w-auto"
                            @click="openCompare(offering.plans)"
                        >
                            Bandingkan rencana
                        </Button>
                        <Form v-bind="storePlan.form({ offering: offering.id })">
                            <Button
                                type="submit"
                                variant="outline"
                                size="sm"
                                class="min-h-11 w-full sm:min-h-8 sm:w-auto"
                            >
                                Plan baru
                            </Button>
                        </Form>
                    </div>
                </div>

                <ul v-if="offering.plans.length > 0" class="mt-3 grid gap-2">
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

        <Dialog v-model:open="compareOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Bandingkan dua rencana</DialogTitle>
                    <DialogDescription>
                        Pilih dua rencana milik Anda dari katalog yang sama.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-4 py-2">
                    <div class="grid gap-2">
                        <Label for="plan-a">Rencana A</Label>
                        <select
                            id="plan-a"
                            v-model.number="planAId"
                            class="flex h-11 w-full rounded-md border bg-transparent px-3 py-1 text-sm"
                        >
                            <option
                                v-for="plan in comparePlansList"
                                :key="`a-${plan.id}`"
                                :value="plan.id"
                            >
                                {{ plan.name }}
                            </option>
                        </select>
                    </div>
                    <div class="grid gap-2">
                        <Label for="plan-b">Rencana B</Label>
                        <select
                            id="plan-b"
                            v-model.number="planBId"
                            class="flex h-11 w-full rounded-md border bg-transparent px-3 py-1 text-sm"
                        >
                            <option
                                v-for="plan in comparePlansList"
                                :key="`b-${plan.id}`"
                                :value="plan.id"
                            >
                                {{ plan.name }}
                            </option>
                        </select>
                    </div>
                    <p
                        v-if="planAId && planBId && planAId === planBId"
                        class="text-sm text-destructive"
                    >
                        Pilih dua rencana yang berbeda.
                    </p>
                </div>

                <DialogFooter>
                    <Button
                        :disabled="!planAId || !planBId || planAId === planBId"
                        @click="goCompare"
                    >
                        Bandingkan
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
