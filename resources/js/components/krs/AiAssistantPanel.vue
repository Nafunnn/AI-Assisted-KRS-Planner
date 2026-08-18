<script setup lang="ts">
import { Link, useHttp } from '@inertiajs/vue3';
import { computed, nextTick, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { edit as editAiProviders } from '@/routes/ai-providers';
import { send as sendAiChat } from '@/routes/ai/chat';
import AiMarkdownContent from '@/components/krs/AiMarkdownContent.vue';

type ChatMessage = {
    id: string;
    role: 'user' | 'assistant';
    content: string;
};

const props = defineProps<{
    planId: number;
    offeringId: number;
    open: boolean;
}>();

const emit = defineEmits<{
    'update:open': [value: boolean];
    planUpdated: [];
}>();

const messages = ref<ChatMessage[]>([]);
const input = ref('');
const conversationId = ref<string | null>(null);
const processing = ref(false);
const messagesEnd = ref<HTMLElement | null>(null);

const http = useHttp({
    message: '',
    plan_id: props.planId,
    offering_id: props.offeringId,
    conversation_id: null as string | null,
});

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            http.plan_id = props.planId;
            http.offering_id = props.offeringId;
        }
    },
);

const quickActions = [
    {
        label: 'Review jadwal',
        prompt: 'Review jadwal KRS saya saat ini. Analisis SKS, bentrok, distribusi hari, dan berikan rekomendasi perbaikan.',
    },
    {
        label: 'Saran perbaikan',
        prompt: 'Sarankan perbaikan jadwal KRS saya. Fokus pada kelompok alternatif yang tidak bentrok.',
    },
    {
        label: 'Buat jadwal otomatis',
        prompt: 'Buatkan jadwal KRS otomatis untuk rencana ini. Gunakan preview dulu (apply=false), jelaskan hasilnya, lalu tanyakan apakah saya ingin menerapkan.',
    },
];

const hasMessages = computed(() => messages.value.length > 0);

async function scrollToBottom(): Promise<void> {
    await nextTick();
    messagesEnd.value?.scrollIntoView({ behavior: 'smooth' });
}

async function sendMessage(text?: string): Promise<void> {
    const message = (text ?? input.value).trim();

    if (!message || processing.value) {
        return;
    }

    input.value = '';
    processing.value = true;

    const userMessage: ChatMessage = {
        id: crypto.randomUUID(),
        role: 'user',
        content: message,
    };
    messages.value.push(userMessage);
    await scrollToBottom();

    http.message = message;
    http.plan_id = props.planId;
    http.offering_id = props.offeringId;
    http.conversation_id = conversationId.value;

    try {
        const response = (await http.post(sendAiChat.url())) as {
            status: string;
            reply?: string;
            message?: string;
            conversation_id?: string;
        };

        if (response.status === 'unavailable') {
            toast.error(response.message ?? 'AI tidak tersedia.');
            messages.value.pop();

            return;
        }

        if (response.conversation_id) {
            conversationId.value = response.conversation_id;
        }

        messages.value.push({
            id: crypto.randomUUID(),
            role: 'assistant',
            content: response.reply ?? '',
        });

        if (
            message.toLowerCase().includes('terapkan') ||
            response.reply?.includes('"applied":true')
        ) {
            emit('planUpdated');
        }
    } catch {
        toast.error('Gagal menghubungi AI assistant.');
        messages.value.pop();
    } finally {
        processing.value = false;
        await scrollToBottom();
    }
}

</script>

<template>
    <Sheet :open="open" @update:open="emit('update:open', $event)">
        <SheetContent side="right" class="flex h-dvh w-full flex-col gap-0 p-0 sm:max-w-md">
            <SheetHeader class="border-b px-4 py-3">
                <SheetTitle>AI Assistant KRS</SheetTitle>
                <p class="text-xs text-muted-foreground">
                    Review, saran, dan buat jadwal via tool calling Laravel
                </p>
            </SheetHeader>

            <div class="flex flex-wrap gap-2 border-b px-4 py-2">
                <Button
                    v-for="action in quickActions"
                    :key="action.label"
                    variant="outline"
                    size="sm"
                    :disabled="processing"
                    @click="sendMessage(action.prompt)"
                >
                    {{ action.label }}
                </Button>
            </div>

            <div class="flex-1 space-y-3 overflow-y-auto px-4 py-3">
                <div
                    v-if="!hasMessages"
                    class="rounded-lg border border-dashed p-4 text-sm text-muted-foreground"
                >
                    Tanyakan apapun tentang jadwal KRS Anda, atau gunakan quick
                    action di atas.
                    <Link
                        :href="editAiProviders()"
                        class="mt-2 block text-primary underline"
                    >
                        Atur AI Provider
                    </Link>
                </div>

                <div
                    v-for="message in messages"
                    :key="message.id"
                    class="rounded-lg px-3 py-2 text-sm"
                    :class="
                        message.role === 'user'
                            ? 'ml-6 max-w-[calc(100%-1.5rem)] break-words bg-primary text-primary-foreground'
                            : 'mr-4 max-w-full min-w-0 break-words bg-muted'
                    "
                >
                    <p
                        v-if="message.role === 'user'"
                        class="whitespace-pre-wrap"
                    >
                        {{ message.content }}
                    </p>
                    <AiMarkdownContent
                        v-else
                        :content="message.content"
                    />
                </div>
                <div ref="messagesEnd" />
            </div>

            <div class="border-t p-4 pb-[max(1rem,env(safe-area-inset-bottom))]">
                <form class="flex flex-col gap-2" @submit.prevent="sendMessage()">
                    <textarea
                        v-model="input"
                        class="flex min-h-[5rem] w-full rounded-md border bg-transparent px-3 py-2 text-base md:text-sm"
                        placeholder="Tulis pesan..."
                        rows="3"
                        :disabled="processing"
                    />
                    <Button type="submit" :disabled="processing || !input.trim()">
                        {{ processing ? 'Memproses...' : 'Kirim' }}
                    </Button>
                </form>
            </div>
        </SheetContent>
    </Sheet>
</template>
