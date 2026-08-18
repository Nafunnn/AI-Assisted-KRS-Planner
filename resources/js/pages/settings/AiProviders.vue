<script setup lang="ts">
import { Form, Head, router, useHttp } from '@inertiajs/vue3';
import { ref } from 'vue';
import { toast } from 'vue-sonner';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    edit as editAiProviders,
    test as testAiProviderDraft,
    testSaved as testAiProviderSaved,
} from '@/routes/ai-providers';

type AiConfig = {
    id: number;
    provider: string;
    provider_label: string;
    name: string;
    base_url: string | null;
    default_model: string | null;
    is_active: boolean;
    has_api_key: boolean;
};

type ProviderOption = {
    value: string;
    label: string;
};

type TestResult = {
    status: 'ok' | 'error';
    message: string;
    reply?: string;
    provider_label?: string;
    model?: string | null;
    latency_ms?: number;
};

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'AI Providers', href: editAiProviders() }],
    },
});

const { configs, providers } = defineProps<{
    configs: AiConfig[];
    providers: ProviderOption[];
}>();

const draftTestHttp = useHttp({
    provider: '',
    base_url: '',
    api_key: '',
    default_model: '',
});

const savedTestHttp = useHttp({});

const testingDraft = ref(false);
const testingConfigId = ref<number | null>(null);

function activateConfig(id: number): void {
    router.patch(`/settings/ai-providers/${id}/activate`);
}

function deleteConfig(id: number): void {
    router.delete(`/settings/ai-providers/${id}`);
}

function showTestResult(result: TestResult): void {
    if (result.status === 'ok') {
        const details = [
            result.provider_label,
            result.model,
            result.latency_ms !== undefined
                ? `${result.latency_ms} ms`
                : null,
        ]
            .filter(Boolean)
            .join(' · ');

        toast.success(
            details
                ? `${result.message} (${details})`
                : result.message,
            result.reply ? { description: result.reply } : undefined,
        );

        return;
    }

    toast.error(result.message);
}

async function testDraftFromForm(form: HTMLFormElement): Promise<void> {
    if (testingDraft.value) {
        return;
    }

    const formData = new FormData(form);

    draftTestHttp.provider = String(formData.get('provider') ?? '');
    draftTestHttp.base_url = String(formData.get('base_url') ?? '');
    draftTestHttp.api_key = String(formData.get('api_key') ?? '');
    draftTestHttp.default_model = String(formData.get('default_model') ?? '');

    testingDraft.value = true;

    try {
        const result = (await draftTestHttp.post(
            testAiProviderDraft.url(),
        )) as TestResult;
        showTestResult(result);
    } catch {
        toast.error('Gagal menguji koneksi provider.');
    } finally {
        testingDraft.value = false;
    }
}

async function testSavedConfig(id: number): Promise<void> {
    if (testingConfigId.value !== null) {
        return;
    }

    testingConfigId.value = id;

    try {
        const result = (await savedTestHttp.post(
            testAiProviderSaved.url(id),
        )) as TestResult;
        showTestResult(result);
    } catch {
        toast.error('Gagal menguji koneksi provider.');
    } finally {
        testingConfigId.value = null;
    }
}
</script>

<template>
    <Head title="AI Providers" />

    <div class="space-y-8">
        <Heading
            variant="small"
            title="AI Providers"
            description="Konfigurasi provider AI untuk asisten KRS (review, saran, buat jadwal)"
        />

        <Form
            action="/settings/ai-providers"
            method="post"
            class="space-y-4 rounded-lg border p-4"
            v-slot="{ errors, processing }"
        >
            <div class="grid gap-2">
                <Label for="provider">Provider</Label>
                <select
                    id="provider"
                    name="provider"
                    class="flex h-9 w-full rounded-md border bg-transparent px-3 py-1 text-sm"
                    required
                >
                    <option
                        v-for="provider in providers"
                        :key="provider.value"
                        :value="provider.value"
                    >
                        {{ provider.label }}
                    </option>
                </select>
                <InputError :message="errors.provider" />
            </div>

            <div class="grid gap-2">
                <Label for="name">Nama</Label>
                <Input id="name" name="name" placeholder="9Router Local" required />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="base_url">Base URL (opsional)</Label>
                <Input
                    id="base_url"
                    name="base_url"
                    placeholder="https://openrouter.ai/api/v1 atau http://127.0.0.1:20128/v1"
                />
                <InputError :message="errors.base_url" />
            </div>

            <div class="grid gap-2">
                <Label for="api_key">API Key</Label>
                <Input id="api_key" name="api_key" type="password" />
                <InputError :message="errors.api_key" />
            </div>

            <div class="grid gap-2">
                <Label for="default_model">Default Model</Label>
                <Input
                    id="default_model"
                    name="default_model"
                    placeholder="claude-sonnet-4"
                />
                <InputError :message="errors.default_model" />
            </div>

            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="is_active" value="1" />
                Jadikan provider aktif
            </label>

            <div class="flex flex-wrap gap-2">
                <Button type="submit" :disabled="processing">
                    Simpan Konfigurasi
                </Button>
                <Button
                    type="button"
                    variant="outline"
                    :disabled="testingDraft"
                    @click="
                        testDraftFromForm(
                            ($event.currentTarget as HTMLButtonElement)
                                .form as HTMLFormElement,
                        )
                    "
                >
                    {{ testingDraft ? 'Menguji...' : 'Uji koneksi' }}
                </Button>
            </div>
        </Form>

        <div class="space-y-3">
            <h3 class="text-sm font-medium">Konfigurasi tersimpan</h3>
            <div
                v-if="configs.length === 0"
                class="rounded-lg border border-dashed p-4 text-sm text-muted-foreground"
            >
                Belum ada provider AI yang dikonfigurasi.
            </div>
            <div
                v-for="config in configs"
                :key="config.id"
                class="flex flex-wrap items-center justify-between gap-3 rounded-lg border p-4"
            >
                <div>
                    <div class="font-medium">
                        {{ config.name }}
                        <span
                            v-if="config.is_active"
                            class="ml-2 rounded bg-primary/10 px-2 py-0.5 text-xs text-primary"
                        >
                            Aktif
                        </span>
                    </div>
                    <p class="text-sm text-muted-foreground">
                        {{ config.provider_label }}
                        <span v-if="config.default_model">
                            · {{ config.default_model }}
                        </span>
                    </p>
                </div>
                <div class="flex gap-2">
                    <Button
                        variant="outline"
                        size="sm"
                        :disabled="testingConfigId === config.id"
                        @click="testSavedConfig(config.id)"
                    >
                        {{
                            testingConfigId === config.id
                                ? 'Menguji...'
                                : 'Uji'
                        }}
                    </Button>
                    <Button
                        v-if="!config.is_active"
                        variant="outline"
                        size="sm"
                        @click="activateConfig(config.id)"
                    >
                        Aktifkan
                    </Button>
                    <Button
                        variant="destructive"
                        size="sm"
                        @click="deleteConfig(config.id)"
                    >
                        Hapus
                    </Button>
                </div>
            </div>
        </div>
    </div>
</template>
