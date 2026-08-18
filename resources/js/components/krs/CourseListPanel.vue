<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { courseColorClass } from '@/lib/krs';
import type { Course, CourseSection } from '@/types/krs';

const { course, selectedSectionIds, unavailableSectionIds } = defineProps<{
    course: Course;
    selectedSectionIds: number[];
    unavailableSectionIds: number[];
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

const visibleSections = computed(() =>
    course.sections.filter(
        (section) =>
            selectedSectionIds.includes(section.id) ||
            !unavailableSectionIds.includes(section.id),
    ),
);

const selectedSection = computed(
    () =>
        course.sections.find((section) =>
            selectedSectionIds.includes(section.id),
        ) ?? null,
);

function scheduleLabel(section: CourseSection): string {
    if (section.schedules.length === 0) {
        return 'Jadwal kosong';
    }

    return section.schedules
        .map((s) => `${s.day_label} ${s.starts_at}-${s.ends_at}`)
        .join(', ');
}

function onDragStart(event: DragEvent, sectionId: number): void {
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
    if (didDrag.value) {
        return;
    }

    emit('select', sectionId);
}
</script>

<template>
    <div v-if="visibleSections.length > 0 || isCourseSelected" class="rounded-lg border">
        <button
            type="button"
            class="flex w-full items-center justify-between px-3 py-2 text-left hover:bg-muted/50"
            @click="expanded = !expanded"
        >
            <div class="flex items-center gap-2">
                <span
                    class="size-2.5 rounded-full"
                    :class="courseColorClass(course.id)"
                />
                <div>
                    <div class="text-sm font-medium">{{ course.code }}</div>
                    <div class="text-xs text-muted-foreground">
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
            <span class="text-xs text-muted-foreground">
                {{
                    isCourseSelected
                        ? 'Terpilih'
                        : `${visibleSections.length} kelompok`
                }}
            </span>
        </button>

        <div v-if="expanded" class="space-y-2 border-t p-2">
            <div
                v-for="section in visibleSections"
                :key="section.id"
                draggable="true"
                role="button"
                tabindex="0"
                class="w-full cursor-grab rounded-md border px-3 py-2 text-left text-sm transition select-none active:cursor-grabbing hover:bg-muted/60"
                :class="
                    selectedSectionIds.includes(section.id)
                        ? 'border-primary bg-primary/5 ring-1 ring-primary/30'
                        : ''
                "
                @click="onSelect(section.id)"
                @keydown.enter="onSelect(section.id)"
                @keydown.space.prevent="onSelect(section.id)"
                @dragstart="onDragStart($event, section.id)"
                @dragend="onDragEnd"
            >
                <div class="flex items-center justify-between gap-2">
                    <span class="font-medium">{{ section.group_code }}</span>
                    <span class="text-xs text-muted-foreground">
                        {{ section.time_period_label }}
                    </span>
                </div>
                <p class="mt-1 text-xs text-muted-foreground">
                    {{ scheduleLabel(section) }}
                </p>
            </div>
        </div>
    </div>
</template>
