<script setup lang="ts">
import { Form, Head, Link, router, useHttp } from '@inertiajs/vue3';
import { RefreshCw } from '@lucide/vue';
import { ref, toRaw, watch } from 'vue';
import { toast } from 'vue-sonner';
import {
    destroy as destroyPlan,
    store as storePlan,
    update as updatePlan,
} from '@/actions/App/Http/Controllers/Krs/KrsPlanController';
import CourseListPanel from '@/components/krs/CourseListPanel.vue';
import AiAssistantPanel from '@/components/krs/AiAssistantPanel.vue';
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
        unavailable_sections: cloned.unavailable_sections ?? [],
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
const refreshing = ref(false);
const isDragActive = ref(false);
const renameOpen = ref(false);
const aiOpen = ref(false);
const mobilePane = ref<'calendar' | 'courses'>('calendar');
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

    if (plan.value.unavailable_section_ids.includes(sectionId)) {
        return;
    }

    const updated = await submitSection(sectionId, 'add');

    if (updated && window.matchMedia('(max-width: 1023px)').matches) {
        mobilePane.value = 'calendar';
    }
}

async function toggleSection(sectionId: number): Promise<void> {
    const isSelected = plan.value.selected_section_ids.includes(sectionId);

    await submitSection(sectionId, isSelected ? 'remove' : 'add');
}

async function submitSection(
    sectionId: number,
    action: 'add' | 'remove',
): Promise<boolean> {
    const previousPlan = clonePlan(plan.value);

    http.course_section_id = sectionId;
    http.action = action;
    processing.value = true;

    try {
        const response = (await http.post(
            togglePlanItem({ plan: plan.value.id }).url,
        )) as { plan: KrsPlan };

        plan.value = clonePlan(response.plan);

        return true;
    } catch {
        plan.value = previousPlan;
        toast.error('Gagal memperbarui jadwal. Periksa bentrok jadwal.');

        return false;
    } finally {
        processing.value = false;
    }
}

function downloadPdf(): void {
    window.location.href = exportPdf.url({ plan: plan.value.id });
}

function refreshPlanFromServer(notify = false): void {
    refreshing.value = true;

    router.reload({
        only: ['plan', 'plans'],
        onSuccess: () => {
            if (notify) {
                toast.success('Kalender diperbarui.');
            }
        },
        onError: () => {
            toast.error('Gagal memperbarui kalender.');
        },
        onFinish: () => {
            refreshing.value = false;
        },
    });
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
    <div class="flex h-full min-w-0 flex-1 flex-col gap-3 p-3 pb-[max(0.75rem,env(safe-area-inset-bottom))] sm:gap-4 sm:p-4">
        <Head :title="`Planner - ${offering.title}`" />
        <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
            <div class="min-w-0">
                <h1 class="text-xl font-semibold break-words">{{ offering.title }}</h1>
                <p class="text-sm text-muted-foreground">
                    Tap kelompok untuk menambah, atau drag ke kalender di layar besar
                </p>
            </div>
            <div class="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap">
                <Button variant="outline" class="min-h-11 sm:min-h-9" as-child>
                    <Link :href="krsIndex()">Kembali</Link>
                </Button>
                <Button variant="outline" class="min-h-11 sm:min-h-9" @click="aiOpen = true">
                    AI Assistant
                </Button>
                <Button variant="outline" class="min-h-11 sm:min-h-9" @click="downloadPdf">
                    Export PDF
                </Button>
                <Button
                    variant="outline"
                    class="min-h-11 sm:min-h-9"
                    :disabled="exportingPng"
                    @click="downloadPng"
                >
                    {{ exportingPng ? 'Menyiapkan PNG...' : 'Export PNG' }}
                </Button>
            </div>
        </div>

        <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-end">
            <div class="grid min-w-0 flex-1 gap-1">
                <Label for="plan-switcher" class="text-xs text-muted-foreground">
                    Rencana
                </Label>
                <select
                    id="plan-switcher"
                    :value="plan.id"
                    class="flex h-11 w-full rounded-md border bg-transparent px-3 py-1 text-base sm:h-9 sm:text-sm"
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
            <div class="grid grid-cols-3 gap-2 sm:flex">
                <Button variant="outline" size="sm" class="min-h-11 sm:min-h-8" @click="renameOpen = true">
                    Ubah nama
                </Button>
                <Form v-bind="storePlan.form({ offering: offering.id })">
                    <Button type="submit" variant="outline" size="sm" class="min-h-11 w-full sm:min-h-8">
                        Plan baru
                    </Button>
                </Form>
                <Button
                    variant="outline"
                    size="sm"
                    class="min-h-11 sm:min-h-8"
                    :disabled="plans.length <= 1"
                    @click="deleteCurrentPlan"
                >
                    Hapus
                </Button>
            </div>
        </div>

        <PlanSummaryBar :plan="plan">
            <div class="flex w-full items-center justify-between gap-2 sm:w-auto sm:justify-end">
                <span v-if="processing" class="text-xs text-muted-foreground">
                    Menyimpan...
                </span>
                <Button
                    variant="outline"
                    size="sm"
                    class="min-h-11 sm:min-h-8"
                    :disabled="refreshing || processing"
                    @click="refreshPlanFromServer(true)"
                >
                    <RefreshCw :class="refreshing ? 'animate-spin' : ''" />
                    <span class="sm:hidden">{{ refreshing ? 'Memuat...' : 'Refresh' }}</span>
                    <span class="hidden sm:inline">{{ refreshing ? 'Memuat...' : 'Refresh kalender' }}</span>
                </Button>
            </div>
        </PlanSummaryBar>

        <div class="grid grid-cols-2 gap-1 rounded-lg border bg-muted/40 p-1 lg:hidden">
            <Button
                :variant="mobilePane === 'calendar' ? 'default' : 'ghost'"
                size="sm"
                class="min-h-11"
                @click="mobilePane = 'calendar'"
            >
                Kalender
            </Button>
            <Button
                :variant="mobilePane === 'courses' ? 'default' : 'ghost'"
                size="sm"
                class="min-h-11"
                @click="mobilePane = 'courses'"
            >
                Mata kuliah
            </Button>
        </div>

        <div class="grid min-h-0 flex-1 gap-4 lg:grid-cols-[minmax(16rem,20rem)_minmax(0,1fr)]">
            <div
                class="scrollbar-transparent min-h-0 space-y-3 overflow-y-auto lg:max-h-[calc(100dvh-8rem)]"
                :class="mobilePane === 'courses' ? 'block max-h-[min(70dvh,36rem)] lg:max-h-[calc(100dvh-8rem)]' : 'hidden lg:block'"
            >
                <CourseListPanel
                    v-for="course in offering.courses"
                    :key="course.id"
                    :course="course"
                    :selected-section-ids="plan.selected_section_ids"
                    :unavailable-sections="plan.unavailable_sections ?? []"
                    @select="addSection"
                    @drag-start="isDragActive = true"
                    @drag-end="isDragActive = false"
                />
            </div>

            <div
                class="min-h-[22rem] min-w-0 lg:sticky lg:top-20 lg:z-10 lg:self-start"
                :class="
                    mobilePane === 'calendar'
                        ? 'block h-[min(70dvh,36rem)] lg:h-[calc(100dvh-6.5rem)]'
                        : 'hidden lg:block lg:h-[calc(100dvh-6.5rem)]'
                "
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

        <AiAssistantPanel
            v-model:open="aiOpen"
            :plan-id="plan.id"
            :offering-id="offering.id"
            @plan-updated="refreshPlanFromServer()"
        />

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
