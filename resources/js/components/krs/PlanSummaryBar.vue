<script setup lang="ts">
import type { KrsPlan } from '@/types/krs';

const { plan } = defineProps<{
    plan: KrsPlan;
}>();
</script>

<template>
    <div
        class="flex flex-col gap-3 rounded-lg border bg-card px-3 py-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between sm:px-4"
    >
        <div class="flex flex-wrap gap-x-4 gap-y-1 text-sm">
            <span>
                <strong>{{ plan.course_count }}</strong> mata kuliah
            </span>
            <span>
                <strong>{{ plan.total_sks }}</strong> SKS
            </span>
            <span
                :class="
                    plan.has_conflicts
                        ? 'font-medium text-destructive'
                        : 'text-emerald-600 dark:text-emerald-400'
                "
            >
                {{
                    plan.has_conflicts
                        ? 'Ada bentrok jadwal'
                        : 'Tidak ada bentrok'
                }}
            </span>
        </div>
        <slot />
    </div>
</template>
