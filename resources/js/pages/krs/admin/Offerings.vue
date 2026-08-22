<script setup lang="ts">
import { Form, Head, Link, router, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import CourseOfferingController from '@/actions/App/Http/Controllers/Krs/Admin/CourseOfferingController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index as krsIndex } from '@/routes/krs';
import { index as adminOfferings } from '@/routes/krs/admin/offerings';

type AdminOffering = {
    id: number;
    title: string;
    term: string;
    source_filename: string;
    catalog_version: number;
    imported_at: string;
    published_at: string | null;
    courses_count: number;
    plans_count: number;
};

type SyncPreview = {
    offering_id: number;
    courses_created: number;
    courses_updated: number;
    sections_created: number;
    sections_updated: number;
    sections_deprecated: number;
    schedule_changed_sections: number;
    affected_plan_items: number;
    affected_plans_count: number;
    errors_count: number;
};

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'KRS Planner', href: krsIndex() },
            { title: 'Kelola Katalog', href: adminOfferings() },
        ],
    },
});

const { offerings } = defineProps<{
    offerings: AdminOffering[];
}>();

const page = usePage();
const importOpen = ref(false);
const syncOpenId = ref<number | null>(null);
const title = ref('');
const term = ref('');
const processing = ref(false);
const fileError = ref<string | undefined>();
const pendingSyncFile = ref<File | null>(null);

const syncPreview = page.props.flash?.sync_preview as SyncPreview | null | undefined;

function submitImport(event: Event): void {
    event.preventDefault();
    const form = event.target as HTMLFormElement;
    const fileInput = form.querySelector<HTMLInputElement>('input[type="file"]');

    if (!fileInput?.files?.[0]) {
        fileError.value = 'File penawaran wajib dipilih.';
        return;
    }

    fileError.value = undefined;
    processing.value = true;

    const formData = new FormData();
    formData.append('file', fileInput.files[0]);
    if (title.value.trim()) formData.append('title', title.value.trim());
    if (term.value.trim()) formData.append('term', term.value.trim());

    router.post(CourseOfferingController.store.url(), formData, {
        forceFormData: true,
        onFinish: () => {
            processing.value = false;
        },
        onSuccess: () => {
            importOpen.value = false;
            title.value = '';
            term.value = '';
            form.reset();
        },
    });
}

function previewSync(offeringId: number, event: Event): void {
    event.preventDefault();
    const form = event.target as HTMLFormElement;
    const fileInput = form.querySelector<HTMLInputElement>('input[type="file"]');

    if (!fileInput?.files?.[0]) {
        fileError.value = 'File penawaran wajib dipilih.';
        return;
    }

    pendingSyncFile.value = fileInput.files[0];
    processing.value = true;

    const formData = new FormData();
    formData.append('file', fileInput.files[0]);

    router.post(CourseOfferingController.previewSync.url(offeringId), formData, {
        forceFormData: true,
        onFinish: () => {
            processing.value = false;
        },
    });
}

function confirmSync(offeringId: number): void {
    if (!pendingSyncFile.value) {
        return;
    }

    processing.value = true;
    const formData = new FormData();
    formData.append('file', pendingSyncFile.value);

    router.post(CourseOfferingController.sync.url(offeringId), formData, {
        forceFormData: true,
        onFinish: () => {
            processing.value = false;
            pendingSyncFile.value = null;
            syncOpenId.value = null;
        },
    });
}
</script>

<template>
    <Head title="Kelola Katalog" />

    <div class="flex h-full flex-1 flex-col gap-4 p-3 sm:p-4">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h1 class="text-xl font-semibold">Kelola Katalog</h1>
                <p class="text-sm text-muted-foreground">
                    Upload dan sync penawaran mata kuliah untuk seluruh mahasiswa
                </p>
            </div>

            <Dialog v-model:open="importOpen">
                <DialogTrigger as-child>
                    <Button class="min-h-11 w-full sm:min-h-9 sm:w-auto">
                        Import Katalog Baru
                    </Button>
                </DialogTrigger>
                <DialogContent>
                    <form @submit="submitImport">
                        <DialogHeader>
                            <DialogTitle>Import Katalog</DialogTitle>
                            <DialogDescription>
                                Unggah Excel penawaran. Katalog akan langsung dipublish.
                            </DialogDescription>
                        </DialogHeader>
                        <div class="grid gap-4 py-4">
                            <div class="grid gap-2">
                                <Label for="title">Judul</Label>
                                <Input id="title" v-model="title" placeholder="Semester 5" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="term">Term</Label>
                                <Input id="term" v-model="term" placeholder="2025/2026-genap" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="file">File Excel</Label>
                                <Input id="file" type="file" accept=".xlsx,.xls" required />
                                <InputError :message="fileError" />
                            </div>
                        </div>
                        <DialogFooter>
                            <Button type="submit" :disabled="processing">
                                {{ processing ? 'Mengimpor...' : 'Import' }}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </div>

        <div
            v-if="syncPreview"
            class="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm dark:border-amber-800 dark:bg-amber-950/40"
        >
            <p class="font-medium">Preview sync katalog #{{ syncPreview.offering_id }}</p>
            <ul class="mt-2 list-inside list-disc text-muted-foreground">
                <li>Course baru: {{ syncPreview.courses_created }}, diubah: {{ syncPreview.courses_updated }}</li>
                <li>Section baru: {{ syncPreview.sections_created }}, diubah: {{ syncPreview.sections_updated }}</li>
                <li>Section deprecated: {{ syncPreview.sections_deprecated }}</li>
                <li>Jadwal berubah: {{ syncPreview.schedule_changed_sections }}</li>
                <li>
                    Dampak: {{ syncPreview.affected_plan_items }} item di
                    {{ syncPreview.affected_plans_count }} rencana
                </li>
            </ul>
            <div class="mt-3 flex gap-2">
                <Button
                    size="sm"
                    :disabled="processing || !pendingSyncFile"
                    @click="confirmSync(syncPreview.offering_id)"
                >
                    Terapkan Sync
                </Button>
            </div>
        </div>

        <div v-if="offerings.length === 0" class="rounded-xl border border-dashed p-8 text-center">
            Belum ada katalog. Import Excel untuk memulai.
        </div>

        <div v-else class="grid gap-3">
            <div
                v-for="offering in offerings"
                :key="offering.id"
                class="rounded-lg border bg-card p-4"
            >
                <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <h2 class="font-medium">{{ offering.title }}</h2>
                        <p class="text-sm text-muted-foreground">
                            {{ offering.term }} · v{{ offering.catalog_version }} ·
                            {{ offering.courses_count }} MK ·
                            {{ offering.plans_count }} rencana ·
                            {{ offering.published_at ? 'Published' : 'Unpublished' }}
                        </p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <Dialog
                            :open="syncOpenId === offering.id"
                            @update:open="(open) => (syncOpenId = open ? offering.id : null)"
                        >
                            <DialogTrigger as-child>
                                <Button size="sm" variant="outline">Sync Excel</Button>
                            </DialogTrigger>
                            <DialogContent>
                                <form @submit="(e) => previewSync(offering.id, e)">
                                    <DialogHeader>
                                        <DialogTitle>Sync Katalog</DialogTitle>
                                        <DialogDescription>
                                            Preview dampak sebelum menerapkan perubahan.
                                        </DialogDescription>
                                    </DialogHeader>
                                    <div class="grid gap-2 py-4">
                                        <Label>File Excel</Label>
                                        <Input type="file" accept=".xlsx,.xls" required />
                                    </div>
                                    <DialogFooter>
                                        <Button type="submit" :disabled="processing">
                                            Preview
                                        </Button>
                                    </DialogFooter>
                                </form>
                            </DialogContent>
                        </Dialog>

                        <Form
                            v-if="offering.published_at"
                            v-bind="CourseOfferingController.unpublish.form(offering.id)"
                        >
                            <Button type="submit" size="sm" variant="outline">Unpublish</Button>
                        </Form>
                        <Form v-else v-bind="CourseOfferingController.publish.form(offering.id)">
                            <Button type="submit" size="sm" variant="outline">Publish</Button>
                        </Form>
                        <Form
                            v-bind="CourseOfferingController.destroy.form(offering.id)"
                            @submit="
                                (e) => {
                                    if (!confirm('Hapus katalog ini?')) e.preventDefault();
                                }
                            "
                        >
                            <Button type="submit" size="sm" variant="destructive">Hapus</Button>
                        </Form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
