<script setup lang="ts">
import { Form, Head, Link, router, useHttp } from '@inertiajs/vue3';
import { ref, toRaw, watch } from 'vue';
import { toast } from 'vue-sonner';
import {
    destroy as destroyPlan,
    store as storePlan,
    update as updatePlan,
} from '@/actions/App/Http/Controllers/Krs/KrsPlanController';
import CourseListPanel from '@/components/krs/CourseListPanel.vue';
import PlanSummaryBar from '@/components/krs/PlanSummaryBar.vue';
import WeeklyScheduleGrid from '@/components/krs/WeeklyScheduleGrid.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { downloadKrsPlanPng } from '@/lib/exportKrsPng';
import { index as krsIndex, planner } from '@/routes/krs';
import { pdf as exportPdf } from '@/routes/krs/plans/export';
import { toggle as togglePlanItem } from '@/routes/krs/plans/items';
import type { CourseOffering, GridConfig, KrsPlan, PlanSummary } from '@/types/krs';

function clonePlan(value: KrsPlan): KrsPlan {
    const cloned = JSON.parse(JSON.stringify(toRaw(value))) as KrsPlan;

    return {
        ...cloned,
        selected_course_ids: cloned.selected_course_ids ?? [],
        unavailable_section_ids: cloned.unavailable_section_ids ?? [],
    };
}

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'KRS Planner', href: krsIndex() },
            { title: 'Planner' },
        ],
    },
});

const props = defineProps<{
    offering: CourseOffering;
    plan: KrsPlan;
    plans: PlanSummary[];
    gridConfig: GridConfig;
}>();

const plan = ref<KrsPlan>(clonePlan(props.plan));
const processing = ref(false);
const exportingPng = ref(false);
const isDragActive = ref(false);
const renameOpen = ref(false);
const http = useHttp({
    course_section_id: 0,
    action: 'add' as 'add' | 'remove',
});

watch(
    () => props.plan,
    (value) => {
        plan.value = clonePlan(value);
    },
);

function switchPlan(event: Event): void {
    const planId = Number((event.target as HTMLSelectElement).value);

    if (planId === plan.value.id) {
        return;
    }

    router.get(
        planner.url({
            offering: props.offering.id,
            plan: planId,
        }),
    );
}

function deleteCurrentPlan(): void {
    if (props.plans.length <= 1) {
        return;
    }

    if (!window.confirm('Hapus rencana ini? Jadwal di rencana ini akan hilang.')) {
        return;
    }

    router.delete(destroyPlan.url(plan.value.id));
}

async function addSection(sectionId: number): Promise<void> {
    if (plan.value.selected_section_ids.includes(sectionId)) {
        return;
    }

    await submitSection(sectionId, 'add');
}

async function toggleSection(sectionId: number): Promise<void> {
    const isSelected = plan.value.selected_section_ids.includes(sectionId);

    await submitSection(sectionId, isSelected ? 'remove' : 'add');
}

async function submitSection(
    sectionId: number,
    action: 'add' | 'remove',
): Promise<void> {
    const previousPlan = clonePlan(plan.value);

    http.course_section_id = sectionId;
    http.action = action;
    processing.value = true;

    try {
        const response = (await http.post(
            togglePlanItem({ plan: plan.value.id }).url,
        )) as { plan: KrsPlan };

        plan.value = clonePlan(response.plan);
    } catch {
        plan.value = previousPlan;
        toast.error('Gagal memperbarui jadwal. Periksa bentrok jadwal.');
    } finally {
        processing.value = false;
    }
}

function downloadPdf(): void {
    window.location.href = exportPdf.url({ plan: plan.value.id });
}

async function downloadPng(): Promise<void> {
    exportingPng.value = true;

    try {
        downloadKrsPlanPng({
            offering: props.offering,
            plan: plan.value,
            gridConfig: props.gridConfig,
            appName: import.meta.env.VITE_APP_NAME || 'KRS Planner',
        });
        toast.success('Gambar jadwal berhasil diunduh.');
    } catch {
        toast.error('Gagal mengekspor PNG. Coba lagi.');
    } finally {
        exportingPng.value = false;
    }
}
</script>

<template>
    <div class="flex h-full flex-1 flex-col gap-4 p-4">
        <Head :title="`Planner - ${offering.title}`" />
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold">{{ offering.title }}</h1>
                <p class="text-sm text-muted-foreground">
                    Pilih kelompok dengan klik atau drag ke kalender
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <Button variant="outline" as-child>
                    <Link :href="krsIndex()">Kembali</Link>
                </Button>
                <Button variant="outline" @click="downloadPdf">Export PDF</Button>
                <Button variant="outline" :disabled="exportingPng" @click="downloadPng">
                    {{ exportingPng ? 'Menyiapkan PNG...' : 'Export PNG' }}
                </Button>
            </div>
        </div>

        <div class="flex flex-wrap items-end gap-2">
            <div class="grid min-w-52 flex-1 gap-1">
                <Label for="plan-switcher" class="text-xs text-muted-foreground">
                    Rencana
                </Label>
                <select
                    id="plan-switcher"
                    :value="plan.id"
                    class="flex h-9 w-full rounded-md border bg-transparent px-3 py-1 text-sm"
                    @change="switchPlan"
                >
                    <option
                        v-for="item in plans"
                        :key="item.id"
                        :value="item.id"
                    >
                        {{ item.name }}
                    </option>
                </select>
            </div>
            <Button variant="outline" size="sm" @click="renameOpen = true">
                Ubah nama
            </Button>
            <Form v-bind="storePlan.form({ offering: offering.id })">
                <Button type="submit" variant="outline" size="sm">
                    Plan baru
                </Button>
            </Form>
            <Button
                variant="outline"
                size="sm"
                :disabled="plans.length <= 1"
                @click="deleteCurrentPlan"
            >
                Hapus
            </Button>
        </div>

        <PlanSummaryBar :plan="plan">
            <span v-if="processing" class="text-xs text-muted-foreground">
                Menyimpan...
            </span>
        </PlanSummaryBar>

        <div class="grid min-h-0 flex-1 gap-4 lg:grid-cols-[320px_minmax(0,1fr)]">
            <div class="scrollbar-transparent min-h-0 space-y-3 overflow-y-auto lg:max-h-[calc(100dvh-8rem)]">
                <CourseListPanel
                    v-for="course in offering.courses"
                    :key="course.id"
                    :course="course"
                    :selected-section-ids="plan.selected_section_ids"
                    :unavailable-section-ids="plan.unavailable_section_ids ?? []"
                    @select="addSection"
                    @drag-start="isDragActive = true"
                    @drag-end="isDragActive = false"
                />
            </div>

            <div
                class="min-h-[28rem] lg:sticky lg:top-20 lg:z-10 lg:h-[calc(100dvh-6.5rem)] lg:self-start"
            >
                <WeeklyScheduleGrid
                    :plan="plan"
                    :grid-config="gridConfig"
                    :is-drag-active="isDragActive"
                    @select="toggleSection"
                    @drop-section="addSection"
                />
            </div>
        </div>

        <Dialog v-model:open="renameOpen">
            <DialogContent>
                <Form
                    v-bind="updatePlan.form(plan.id)"
                    @success="renameOpen = false"
                    v-slot="{ errors, processing: renaming }"
                >
                    <DialogHeader>
                        <DialogTitle>Ubah nama rencana</DialogTitle>
                    </DialogHeader>
                    <div class="grid gap-2 py-4">
                        <Label for="plan-name">Nama</Label>
                        <Input
                            id="plan-name"
                            name="name"
                            :default-value="plan.name"
                            required
                        />
                        <InputError :message="errors.name" />
                    </div>
                    <DialogFooter>
                        <Button type="submit" :disabled="renaming">
                            Simpan
                        </Button>
                    </DialogFooter>
                </Form>
            </DialogContent>
        </Dialog>
    </div>
</template>
