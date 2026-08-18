<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { edit as editAiProviders } from '@/routes/ai-providers';

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

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'AI Providers', href: editAiProviders() }],
    },
});

const { configs, providers } = defineProps<{
    configs: AiConfig[];
    providers: ProviderOption[];
}>();

function activateConfig(id: number): void {
    router.patch(`/settings/ai-providers/${id}/activate`);
}

function deleteConfig(id: number): void {
    router.delete(`/settings/ai-providers/${id}`);
}
</script>

<template>
    <Head title="AI Providers" />

    <div class="space-y-8">
        <Heading
            variant="small"
            title="AI Providers"
            description="Konfigurasi provider AI untuk auto-schedule dan review jadwal KRS"
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
                <Input id="name" name="name" placeholder="OpenAI Production" required />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <Label for="base_url">Base URL (opsional)</Label>
                <Input
                    id="base_url"
                    name="base_url"
                    placeholder="https://openrouter.ai/api/v1"
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
                    placeholder="gpt-4o-mini"
                />
                <InputError :message="errors.default_model" />
            </div>

            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="is_active" value="1" />
                Jadikan provider aktif
            </label>

            <Button type="submit" :disabled="processing">
                Simpan Konfigurasi
            </Button>
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
