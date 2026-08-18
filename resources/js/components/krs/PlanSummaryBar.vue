<script setup lang="ts">
import type { KrsPlan } from '@/types/krs';

const { plan } = defineProps<{
    plan: KrsPlan;
}>();
</script>

<template>
    <div
        class="flex flex-wrap items-center justify-between gap-3 rounded-lg border bg-card px-4 py-3"
    >
        <div class="flex flex-wrap gap-4 text-sm">
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
