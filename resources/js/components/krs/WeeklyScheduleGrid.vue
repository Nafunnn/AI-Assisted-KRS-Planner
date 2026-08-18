<script setup lang="ts">
import { computed, ref } from 'vue';
import { courseColorClass, timeToMinutes } from '@/lib/krs';
import type { GridConfig, KrsPlan } from '@/types/krs';

const { plan, gridConfig } = defineProps<{
    plan: KrsPlan;
    gridConfig: GridConfig;
    isDragActive?: boolean;
}>();

const emit = defineEmits<{
    select: [sectionId: number];
    dropSection: [sectionId: number];
}>();

const gridTemplateClass =
    'grid-cols-[2.5rem_repeat(5,minmax(0,1fr))] sm:grid-cols-[3rem_repeat(5,minmax(5rem,1fr))] lg:grid-cols-[3.5rem_repeat(5,minmax(7.5rem,1fr))]';
const dragOverDay = ref<string | null>(null);

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

const selectedBlocks = computed(() => {
    return plan.items.flatMap((item) =>
        item.section.schedules.map((schedule) => ({
            courseId: item.course.id,
            code: item.course.code,
            name: item.course.name,
            groupCode: item.section.group_code,
            sectionId: item.section.id,
            day: schedule.day,
            startsAt: schedule.starts_at,
            endsAt: schedule.ends_at,
            conflict: plan.conflict_section_ids.includes(item.section.id),
        })),
    );
});

function blockStyle(startsAt: string, endsAt: string): Record<string, string> {
    const gridStart = timeToMinutes(gridConfig.start_hour);
    const gridEnd = timeToMinutes(gridConfig.end_hour);
    const start = timeToMinutes(startsAt);
    const end = timeToMinutes(endsAt);
    const total = gridEnd - gridStart;

    const top = ((start - gridStart) / total) * 100;
    const height = ((end - start) / total) * 100;

    return {
        top: `${top}%`,
        height: `${Math.max(height, 4)}%`,
    };
}

function readSectionId(event: DragEvent): number | null {
    const id =
        event.dataTransfer?.getData('application/x-krs-section-id') ||
        event.dataTransfer?.getData('text/plain');

    const sectionId = Number(id);

    return sectionId > 0 ? sectionId : null;
}

function onDragOverDay(event: DragEvent, day: string): void {
    event.preventDefault();

    if (event.dataTransfer) {
        event.dataTransfer.dropEffect = 'copy';
    }

    dragOverDay.value = day;
}

function onDragLeaveDay(event: DragEvent, day: string): void {
    const related = event.relatedTarget as Node | null;
    const current = event.currentTarget as HTMLElement | null;

    if (current && related && current.contains(related)) {
        return;
    }

    if (dragOverDay.value === day) {
        dragOverDay.value = null;
    }
}

function onDropDay(event: DragEvent): void {
    event.preventDefault();
    dragOverDay.value = null;

    const sectionId = readSectionId(event);

    if (sectionId) {
        emit('dropSection', sectionId);
    }
}

function onDragOverGrid(event: DragEvent): void {
    event.preventDefault();

    if (event.dataTransfer) {
        event.dataTransfer.dropEffect = 'copy';
    }
}

function onDropGrid(event: DragEvent): void {
    event.preventDefault();
    dragOverDay.value = null;

    const sectionId = readSectionId(event);

    if (sectionId) {
        emit('dropSection', sectionId);
    }
}
</script>

<template>
    <div
        class="scrollbar-transparent flex h-full max-h-full min-w-0 flex-col overflow-auto rounded-lg border bg-card transition"
        :class="isDragActive ? 'ring-2 ring-primary/30' : ''"
    >
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

        <div
            class="relative grid w-full min-w-0 flex-1"
            :class="gridTemplateClass"
            @dragover="onDragOverGrid"
            @drop="onDropGrid"
            @dragleave="dragOverDay = null"
        >
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
                :class="
                    dragOverDay === day.value
                        ? 'bg-primary/10 ring-2 ring-inset ring-primary/40'
                        : isDragActive
                          ? 'bg-muted/20'
                          : ''
                "
                @dragover="onDragOverDay($event, day.value)"
                @dragleave="onDragLeaveDay($event, day.value)"
                @drop="onDropDay"
            >
                <div
                    v-for="hour in hours"
                    :key="`${day.value}-${hour}`"
                    class="pointer-events-none h-7 border-b border-dashed border-border/60 sm:h-8"
                />

                <template
                    v-for="block in selectedBlocks.filter((b) => b.day === day.value)"
                    :key="`${block.sectionId}-${block.startsAt}`"
                >
                    <button
                        type="button"
                        class="absolute inset-x-0.5 z-10 flex flex-col gap-0.5 overflow-hidden rounded border px-1 py-0.5 text-left text-[9px] leading-tight text-white shadow-sm sm:inset-x-1 sm:px-1.5 sm:py-1 sm:text-[10px]"
                        :class="[
                            courseColorClass(block.courseId),
                            block.conflict ? 'ring-2 ring-destructive' : '',
                        ]"
                        :style="blockStyle(block.startsAt, block.endsAt)"
                        @click="emit('select', block.sectionId)"
                    >
                        <div class="font-semibold">{{ block.code }}</div>
                        <div class="hidden line-clamp-2 text-[9px] leading-snug opacity-95 sm:block">
                            {{ block.name }}
                        </div>
                        <div class="text-[8px] font-medium opacity-90 sm:text-[9px]">
                            {{ block.startsAt }}-{{ block.endsAt }}
                        </div>
                        <div class="truncate text-[8px] opacity-75 sm:text-[9px]">
                            {{ block.groupCode }}
                        </div>
                    </button>
                </template>
            </div>
        </div>

        <p
            v-if="isDragActive"
            class="border-t px-3 py-2 text-center text-xs text-muted-foreground"
        >
            Lepaskan kelompok di kolom hari mana saja untuk menambah ke KRS
        </p>
    </div>
</template>
