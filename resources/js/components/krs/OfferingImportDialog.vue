<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';
import CourseOfferingController from '@/actions/App/Http/Controllers/Krs/CourseOfferingController';
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
import InputError from '@/components/InputError.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const open = ref(false);
const title = ref('');
const processing = ref(false);
const fileError = ref<string | undefined>(undefined);

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

    if (title.value.trim()) {
        formData.append('title', title.value.trim());
    }

    router.post(CourseOfferingController.store.url(), formData, {
        forceFormData: true,
        onFinish: () => {
            processing.value = false;
        },
        onSuccess: () => {
            open.value = false;
            title.value = '';
            form.reset();
        },
    });
}
</script>

<template>
    <Dialog v-model:open="open">
        <DialogTrigger as-child>
            <Button class="min-h-11 w-full sm:min-h-9 sm:w-auto">Import Penawaran</Button>
        </DialogTrigger>
        <DialogContent>
            <form @submit="submitImport">
                <DialogHeader>
                    <DialogTitle>Import Penawaran Mata Kuliah</DialogTitle>
                    <DialogDescription>
                        Unggah file Excel (.xlsx) sesuai template penawaran kampus.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-4 py-4">
                    <div class="grid gap-2">
                        <Label for="title">Judul (opsional)</Label>
                        <Input
                            id="title"
                            v-model="title"
                            placeholder="Semester 5"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="file">File Excel</Label>
                        <Input
                            id="file"
                            name="file"
                            type="file"
                            accept=".xlsx,.xls"
                            required
                        />
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
</template>
