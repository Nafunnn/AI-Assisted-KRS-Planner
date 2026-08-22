<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import CompareScheduleGrid from '@/components/krs/CompareScheduleGrid.vue';
import { Button } from '@/components/ui/button';
import { index as friendsIndex } from '@/routes/friends';
import { index as krsIndex } from '@/routes/krs';
import type {
    CompareCalendarBlock,
    ComparePlanSummary,
    CompareSection,
    CompareStats,
    CompareTimeOverlap,
    GridConfig,
} from '@/types/krs';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'KRS Planner', href: krsIndex() },
            { title: 'Bandingkan' },
        ],
    },
});

const props = defineProps<{
    plan_a: ComparePlanSummary;
    plan_b: ComparePlanSummary;
    same_sections: CompareSection[];
    only_a: CompareSection[];
    only_b: CompareSection[];
    time_overlaps: CompareTimeOverlap[];
    stats: CompareStats;
    calendar_blocks: CompareCalendarBlock[];
    grid_config: GridConfig;
    offering: { id: number; title: string };
}>();

function scheduleText(section: CompareSection): string {
    if (section.schedules.length === 0) {
        return 'Jadwal kosong';
    }

    return section.schedules
        .map((s) => `${s.day_label} ${s.starts_at}–${s.ends_at}`)
        .join(', ');
}
</script>

<template>
    <Head :title="`Bandingkan · ${plan_a.name} vs ${plan_b.name}`" />

    <div class="flex h-full flex-1 flex-col gap-4 p-3 sm:p-4">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <h1 class="text-xl font-semibold">Bandingkan Rencana</h1>
                <p class="text-sm text-muted-foreground">
                    {{ offering.title }}
                </p>
                <div class="mt-2 flex flex-wrap gap-3 text-sm">
                    <p>
                        <span class="inline-block size-2.5 rounded-full bg-sky-600 align-middle" />
                        <strong class="ml-1">A:</strong>
                        {{ plan_a.name }}
                        <span class="text-muted-foreground">({{ plan_a.owner.name }})</span>
                    </p>
                    <p>
                        <span class="inline-block size-2.5 rounded-full bg-violet-600 align-middle" />
                        <strong class="ml-1">B:</strong>
                        {{ plan_b.name }}
                        <span class="text-muted-foreground">({{ plan_b.owner.name }})</span>
                    </p>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <Button as-child variant="outline" class="min-h-11 sm:min-h-9">
                    <Link :href="krsIndex()">KRS Planner</Link>
                </Button>
                <Button as-child variant="outline" class="min-h-11 sm:min-h-9">
                    <Link :href="friendsIndex()">Teman</Link>
                </Button>
            </div>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-lg border bg-card p-3">
                <p class="text-xs text-muted-foreground">SKS</p>
                <p class="text-lg font-semibold">
                    A {{ stats.sks_a }} · B {{ stats.sks_b }}
                </p>
            </div>
            <div class="rounded-lg border border-emerald-300 bg-emerald-50 p-3 dark:border-emerald-800 dark:bg-emerald-950/40">
                <p class="text-xs text-muted-foreground">Kelompok sama</p>
                <p class="text-lg font-semibold text-emerald-700 dark:text-emerald-300">
                    {{ stats.same_count }}
                    <span class="text-sm font-normal">({{ stats.same_sks }} SKS)</span>
                </p>
            </div>
            <div class="rounded-lg border bg-card p-3">
                <p class="text-xs text-muted-foreground">Hanya di A / B</p>
                <p class="text-lg font-semibold">
                    {{ stats.only_a_count }} / {{ stats.only_b_count }}
                </p>
            </div>
            <div class="rounded-lg border border-amber-300 bg-amber-50 p-3 dark:border-amber-800 dark:bg-amber-950/40">
                <p class="text-xs text-muted-foreground">Tabrakan jam</p>
                <p class="text-lg font-semibold text-amber-800 dark:text-amber-200">
                    {{ stats.time_overlap_count }}
                </p>
            </div>
        </div>

        <div class="flex flex-wrap gap-3 text-xs text-muted-foreground">
            <span class="inline-flex items-center gap-1.5">
                <span class="size-3 rounded bg-sky-600" /> Hanya rencana A
            </span>
            <span class="inline-flex items-center gap-1.5">
                <span class="size-3 rounded bg-violet-600" /> Hanya rencana B
            </span>
            <span class="inline-flex items-center gap-1.5">
                <span class="size-3 rounded bg-emerald-600" /> Kelompok sama (bareng)
            </span>
            <span class="inline-flex items-center gap-1.5">
                <span class="size-3 rounded ring-2 ring-amber-400" /> Ada tabrakan jam
            </span>
        </div>

        <div class="h-[min(70dvh,36rem)] min-h-[22rem] lg:h-[calc(100dvh-22rem)]">
            <CompareScheduleGrid :blocks="calendar_blocks" :grid-config="grid_config" />
        </div>

        <section class="grid gap-2">
            <h2 class="text-base font-semibold text-emerald-700 dark:text-emerald-300">
                Kuliah bareng (kelompok sama)
            </h2>
            <p class="text-sm text-muted-foreground">
                Kedua rencana mengambil kelompok mata kuliah yang sama — kalian duduk di kelas yang sama.
            </p>
            <p v-if="same_sections.length === 0" class="rounded-lg border border-dashed p-4 text-sm text-muted-foreground">
                Tidak ada kelompok yang sama.
            </p>
            <div
                v-for="section in same_sections"
                :key="`same-${section.section_id}`"
                class="rounded-lg border border-emerald-200 bg-emerald-50/50 p-3 dark:border-emerald-900 dark:bg-emerald-950/30"
            >
                <p class="font-medium">
                    {{ section.code }} · {{ section.name }}
                    <span class="text-muted-foreground">({{ section.sks }} SKS)</span>
                </p>
                <p class="text-sm">
                    Kelompok {{ section.group_code }} · {{ section.time_period_label }}
                </p>
                <p class="text-sm text-muted-foreground">{{ scheduleText(section) }}</p>
            </div>
        </section>

        <section class="grid gap-2">
            <h2 class="text-base font-semibold text-amber-800 dark:text-amber-200">
                Tabrakan jam (beda mata kuliah)
            </h2>
            <p class="text-sm text-muted-foreground">
                Di jam yang sama kalian berada di kelas berbeda — tidak kuliah bareng, tapi jadwalnya bertabrakan.
            </p>
            <p v-if="time_overlaps.length === 0" class="rounded-lg border border-dashed p-4 text-sm text-muted-foreground">
                Tidak ada tabrakan jam antar rencana.
            </p>
            <div
                v-for="(overlap, index) in time_overlaps"
                :key="`overlap-${index}`"
                class="rounded-lg border border-amber-200 bg-amber-50/60 p-3 dark:border-amber-900 dark:bg-amber-950/30"
            >
                <p class="font-medium">
                    {{ overlap.day_label }}
                    {{ overlap.overlap_starts_at }}–{{ overlap.overlap_ends_at }}
                    <span class="text-sm font-normal text-muted-foreground">
                        (overlap {{ overlap.overlap_minutes }} menit)
                    </span>
                </p>
                <div class="mt-2 grid gap-2 sm:grid-cols-2">
                    <div class="rounded-md border bg-sky-50/80 p-2 text-sm dark:bg-sky-950/40">
                        <p class="text-xs font-medium text-sky-700 dark:text-sky-300">Rencana A</p>
                        <p>
                            {{ overlap.section_a.code }} · {{ overlap.section_a.name }}
                        </p>
                        <p class="text-muted-foreground">
                            Kelompok {{ overlap.section_a.group_code }} ·
                            {{ overlap.section_a.starts_at }}–{{ overlap.section_a.ends_at }}
                        </p>
                    </div>
                    <div class="rounded-md border bg-violet-50/80 p-2 text-sm dark:bg-violet-950/40">
                        <p class="text-xs font-medium text-violet-700 dark:text-violet-300">Rencana B</p>
                        <p>
                            {{ overlap.section_b.code }} · {{ overlap.section_b.name }}
                        </p>
                        <p class="text-muted-foreground">
                            Kelompok {{ overlap.section_b.group_code }} ·
                            {{ overlap.section_b.starts_at }}–{{ overlap.section_b.ends_at }}
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <div class="grid gap-4 lg:grid-cols-2">
            <section class="grid gap-2">
                <h2 class="text-base font-semibold text-sky-700 dark:text-sky-300">
                    Hanya di rencana A
                </h2>
                <p class="text-sm text-muted-foreground">
                    {{ plan_a.owner.name }} mengambil ini; {{ plan_b.owner.name }} tidak.
                </p>
                <p v-if="only_a.length === 0" class="rounded-lg border border-dashed p-4 text-sm text-muted-foreground">
                    Tidak ada mata kuliah unik di A.
                </p>
                <div
                    v-for="section in only_a"
                    :key="`only-a-${section.section_id}`"
                    class="rounded-lg border border-sky-200 bg-sky-50/50 p-3 dark:border-sky-900 dark:bg-sky-950/30"
                >
                    <p class="font-medium">
                        {{ section.code }} · {{ section.name }}
                        <span class="text-muted-foreground">({{ section.sks }} SKS)</span>
                    </p>
                    <p class="text-sm">Kelompok {{ section.group_code }}</p>
                    <p class="text-sm text-muted-foreground">{{ scheduleText(section) }}</p>
                </div>
            </section>

            <section class="grid gap-2">
                <h2 class="text-base font-semibold text-violet-700 dark:text-violet-300">
                    Hanya di rencana B
                </h2>
                <p class="text-sm text-muted-foreground">
                    {{ plan_b.owner.name }} mengambil ini; {{ plan_a.owner.name }} tidak.
                </p>
                <p v-if="only_b.length === 0" class="rounded-lg border border-dashed p-4 text-sm text-muted-foreground">
                    Tidak ada mata kuliah unik di B.
                </p>
                <div
                    v-for="section in only_b"
                    :key="`only-b-${section.section_id}`"
                    class="rounded-lg border border-violet-200 bg-violet-50/50 p-3 dark:border-violet-900 dark:bg-violet-950/30"
                >
                    <p class="font-medium">
                        {{ section.code }} · {{ section.name }}
                        <span class="text-muted-foreground">({{ section.sks }} SKS)</span>
                    </p>
                    <p class="text-sm">Kelompok {{ section.group_code }}</p>
                    <p class="text-sm text-muted-foreground">{{ scheduleText(section) }}</p>
                </div>
            </section>
        </div>
    </div>
</template>
