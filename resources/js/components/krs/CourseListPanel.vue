<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { courseColorClass } from '@/lib/krs';
import type { Course, CourseSection, SectionConflict, UnavailableSection } from '@/types/krs';

const { course, selectedSectionIds, unavailableSections } = defineProps<{
    course: Course;
    selectedSectionIds: number[];
    unavailableSections: UnavailableSection[];
}>();

const emit = defineEmits<{
    select: [sectionId: number];
    dragStart: [];
    dragEnd: [];
}>();

const isCourseSelected = computed(() =>
    course.sections.some((section) => selectedSectionIds.includes(section.id)),
);

const expanded = ref(!isCourseSelected.value);
const didDrag = ref(false);

watch(isCourseSelected, (selected) => {
    if (selected) {
        expanded.value = false;
    }
});

const conflictsBySectionId = computed(() => {
    const map = new Map<number, SectionConflict[]>();

    for (const unavailable of unavailableSections) {
        map.set(unavailable.section_id, unavailable.conflicts_with);
    }

    return map;
});

const selectedSection = computed(
    () =>
        course.sections.find((section) =>
            selectedSectionIds.includes(section.id),
        ) ?? null,
);

function conflictsFor(sectionId: number): SectionConflict[] {
    return conflictsBySectionId.value.get(sectionId) ?? [];
}

function isUnavailable(sectionId: number): boolean {
    return conflictsBySectionId.value.has(sectionId);
}

function scheduleLabel(section: CourseSection): string {
    if (section.schedules.length === 0) {
        return 'Jadwal kosong';
    }

    return section.schedules
        .map((s) => `${s.day_label} ${s.starts_at}-${s.ends_at}`)
        .join(', ');
}

function conflictLabel(conflict: SectionConflict): string {
    return `${conflict.course_code} ${conflict.group_code} (${conflict.day_label} ${conflict.starts_at}–${conflict.ends_at})`;
}

function onDragStart(event: DragEvent, sectionId: number): void {
    if (isUnavailable(sectionId)) {
        event.preventDefault();

        return;
    }

    didDrag.value = true;
    emit('dragStart');

    if (!event.dataTransfer) {
        return;
    }

    event.dataTransfer.setData('text/plain', String(sectionId));
    event.dataTransfer.setData('application/x-krs-section-id', String(sectionId));
    event.dataTransfer.effectAllowed = 'copy';
}

function onDragEnd(): void {
    emit('dragEnd');
    window.setTimeout(() => {
        didDrag.value = false;
    }, 0);
}

function onSelect(sectionId: number): void {
    if (didDrag.value || isUnavailable(sectionId)) {
        return;
    }

    emit('select', sectionId);
}
</script>

<template>
    <div v-if="course.sections.length > 0" class="rounded-lg border">
        <button
            type="button"
            class="flex min-h-11 w-full items-center justify-between gap-2 px-3 py-2.5 text-left hover:bg-muted/50"
            @click="expanded = !expanded"
        >
            <div class="flex min-w-0 items-center gap-2">
                <span
                    class="size-2.5 shrink-0 rounded-full"
                    :class="courseColorClass(course.id)"
                />
                <div class="min-w-0">
                    <div class="text-sm font-medium">{{ course.code }}</div>
                    <div class="truncate text-xs text-muted-foreground">
                        {{ course.name }} · {{ course.sks }} SKS
                    </div>
                    <div
                        v-if="selectedSection && !expanded"
                        class="mt-1 text-xs text-primary"
                    >
                        {{ selectedSection.group_code }} ·
                        {{ scheduleLabel(selectedSection) }}
                    </div>
                </div>
            </div>
            <span class="shrink-0 text-xs text-muted-foreground">
                {{
                    isCourseSelected
                        ? 'Terpilih'
                        : `${course.sections.length} kelompok`
                }}
            </span>
        </button>

        <div v-if="expanded" class="space-y-2 border-t p-2">
            <div
                v-for="section in course.sections"
                :key="section.id"
                :draggable="!isUnavailable(section.id)"
                role="button"
                :tabindex="isUnavailable(section.id) ? -1 : 0"
                :aria-disabled="isUnavailable(section.id)"
                class="min-h-11 w-full rounded-md border px-3 py-2.5 text-left text-sm transition select-none"
                :class="
                    selectedSectionIds.includes(section.id)
                        ? 'cursor-grab border-primary bg-primary/5 ring-1 ring-primary/30 active:cursor-grabbing hover:bg-muted/60'
                        : isUnavailable(section.id)
                          ? 'cursor-not-allowed border-destructive/30 bg-destructive/5 opacity-80'
                          : 'cursor-grab hover:bg-muted/60 active:cursor-grabbing'
                "
                @click="onSelect(section.id)"
                @keydown.enter="onSelect(section.id)"
                @keydown.space.prevent="onSelect(section.id)"
                @dragstart="onDragStart($event, section.id)"
                @dragend="onDragEnd"
            >
                <div class="flex items-center justify-between gap-2">
                    <span class="font-medium">{{ section.group_code }}</span>
                    <span
                        class="text-xs"
                        :class="
                            isUnavailable(section.id)
                                ? 'text-destructive'
                                : 'text-muted-foreground'
                        "
                    >
                        {{
                            isUnavailable(section.id)
                                ? 'Bentrok'
                                : section.time_period_label
                        }}
                    </span>
                </div>
                <p class="mt-1 text-xs text-muted-foreground">
                    {{ scheduleLabel(section) }}
                </p>
                <p
                    v-for="(conflict, index) in conflictsFor(section.id)"
                    :key="`${conflict.section_id}-${conflict.day}-${index}`"
                    class="mt-1 text-xs text-destructive"
                >
                    Bentrok dengan {{ conflictLabel(conflict) }}
                </p>
            </div>
        </div>
    </div>
</template>
