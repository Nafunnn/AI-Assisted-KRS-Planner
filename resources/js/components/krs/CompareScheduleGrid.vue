<script setup lang="ts">
import { computed } from 'vue';
import { timeToMinutes } from '@/lib/krs';
import type { CompareCalendarBlock, GridConfig } from '@/types/krs';

const { blocks, gridConfig } = defineProps<{
    blocks: CompareCalendarBlock[];
    gridConfig: GridConfig;
}>();

const gridTemplateClass =
    'grid-cols-[2.5rem_repeat(5,minmax(0,1fr))] sm:grid-cols-[3rem_repeat(5,minmax(5rem,1fr))] lg:grid-cols-[3.5rem_repeat(5,minmax(7.5rem,1fr))]';

const hours = computed(() => {
    const start = timeToMinutes(gridConfig.start_hour);
    const end = timeToMinutes(gridConfig.end_hour);
    const slots: string[] = [];

    for (let minute = start; minute < end; minute += gridConfig.slot_minutes) {
        const h = Math.floor(minute / 60)
            .toString()
            .padStart(2, '0');
        const m = (minute % 60).toString().padStart(2, '0');
        slots.push(`${h}:${m}`);
    }

    return slots;
});

function blockStyle(startsAt: string, endsAt: string): Record<string, string> {
    const gridStart = timeToMinutes(gridConfig.start_hour);
    const gridEnd = timeToMinutes(gridConfig.end_hour);
    const start = timeToMinutes(startsAt);
    const end = timeToMinutes(endsAt);
    const total = gridEnd - gridStart;

    return {
        top: `${((start - gridStart) / total) * 100}%`,
        height: `${Math.max(((end - start) / total) * 100, 4)}%`,
    };
}

function blockClass(block: CompareCalendarBlock): string {
    const base =
        block.plan === 'both'
            ? 'bg-emerald-600/90 border-emerald-700'
            : block.plan === 'a'
              ? 'bg-sky-600/85 border-sky-700'
              : 'bg-violet-600/85 border-violet-700';

    return [
        base,
        block.has_time_overlap && block.plan !== 'both'
            ? 'ring-2 ring-amber-400 ring-offset-1 ring-offset-background'
            : '',
    ]
        .filter(Boolean)
        .join(' ');
}

function planLabel(plan: CompareCalendarBlock['plan']): string {
    if (plan === 'both') {
        return 'Bareng';
    }

    return plan === 'a' ? 'A' : 'B';
}
</script>

<template>
    <div class="scrollbar-transparent flex h-full max-h-full min-w-0 flex-col overflow-auto rounded-lg border bg-card">
        <div
            class="sticky top-0 z-20 grid w-full border-b bg-card text-[11px] font-medium shadow-sm sm:text-xs"
            :class="gridTemplateClass"
        >
            <div class="sticky left-0 z-30 border-r bg-card px-1 py-2 text-center sm:px-2">
                Jam
            </div>
            <div
                v-for="day in gridConfig.days"
                :key="day.value"
                class="border-r px-1 py-2 text-center last:border-r-0 sm:px-2"
            >
                {{ day.label }}
            </div>
        </div>

        <div class="relative grid w-full min-w-0 flex-1" :class="gridTemplateClass">
            <div class="sticky left-0 z-10 border-r bg-card">
                <div
                    v-for="hour in hours"
                    :key="hour"
                    class="h-7 border-b px-0.5 text-center text-[9px] leading-7 text-muted-foreground sm:h-8 sm:px-1 sm:text-[10px] sm:leading-8"
                >
                    {{ hour }}
                </div>
            </div>

            <div
                v-for="day in gridConfig.days"
                :key="day.value"
                class="relative min-w-0 border-r last:border-r-0"
            >
                <div
                    v-for="hour in hours"
                    :key="`${day.value}-${hour}`"
                    class="pointer-events-none h-7 border-b border-dashed border-border/60 sm:h-8"
                />

                <div
                    v-for="block in blocks.filter((b) => b.day === day.value)"
                    :key="`${block.plan}-${block.section_id}-${block.starts_at}`"
                    class="absolute inset-x-0.5 z-10 flex flex-col gap-0.5 overflow-hidden rounded border px-1 py-0.5 text-left text-[9px] leading-tight text-white shadow-sm sm:inset-x-1 sm:px-1.5 sm:py-1 sm:text-[10px]"
                    :class="blockClass(block)"
                    :style="blockStyle(block.starts_at, block.ends_at)"
                    :title="`${block.code} ${block.name} · ${block.group_code}`"
                >
                    <div class="flex items-center justify-between gap-1">
                        <span class="font-semibold">{{ block.code }}</span>
                        <span class="rounded bg-black/20 px-1 text-[8px] uppercase">
                            {{ planLabel(block.plan) }}
                        </span>
                    </div>
                    <div class="hidden line-clamp-1 text-[9px] opacity-95 sm:block">
                        {{ block.name }}
                    </div>
                    <div class="text-[8px] opacity-90 sm:text-[9px]">
                        {{ block.starts_at }}-{{ block.ends_at }} · {{ block.group_code }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
