<script setup lang="ts">
import { Form, Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import FriendshipController from '@/actions/App/Http/Controllers/Friends/FriendshipController';
import KrsPlanCompareController from '@/actions/App/Http/Controllers/Krs/KrsPlanCompareController';
import { copyFrom } from '@/actions/App/Http/Controllers/Krs/KrsPlanController';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index as friendsIndex } from '@/routes/friends';
import { index as krsIndex, planner } from '@/routes/krs';
import type { MyPlanOption } from '@/types/krs';

type FriendRow = {
    friendship_id: number;
    id: number;
    name: string;
    email: string;
};

type SharedPlan = {
    id: number;
    name: string;
    owner_name: string;
    offering_id: number;
    offering_title: string;
};

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Teman', href: friendsIndex() }],
    },
});

const props = defineProps<{
    friends: FriendRow[];
    incoming: FriendRow[];
    outgoing: FriendRow[];
    sharedPlans: SharedPlan[];
    myPlans: MyPlanOption[];
}>();

const email = ref('');
const compareOpen = ref(false);
const selectedFriendPlan = ref<SharedPlan | null>(null);
const selectedMyPlanId = ref<number | null>(null);

const compatibleMyPlans = computed(() => {
    if (!selectedFriendPlan.value) {
        return [];
    }

    return props.myPlans.filter(
        (plan) => plan.offering_id === selectedFriendPlan.value?.offering_id,
    );
});

function openCompare(plan: SharedPlan): void {
    selectedFriendPlan.value = plan;
    selectedMyPlanId.value =
        props.myPlans.find((item) => item.offering_id === plan.offering_id)?.id ?? null;
    compareOpen.value = true;
}

function goCompare(): void {
    if (!selectedFriendPlan.value || !selectedMyPlanId.value) {
        return;
    }

    router.get(
        KrsPlanCompareController.url({
            query: {
                plan_a: selectedMyPlanId.value,
                plan_b: selectedFriendPlan.value.id,
            },
        }),
    );
}
</script>

<template>
    <Head title="Teman" />

    <div class="flex h-full flex-1 flex-col gap-6 p-3 sm:p-4">
        <div>
            <h1 class="text-xl font-semibold">Teman</h1>
            <p class="text-sm text-muted-foreground">
                Tambah teman untuk melihat rencana KRS yang mereka bagikan
            </p>
        </div>

        <Form
            v-bind="FriendshipController.store.form()"
            class="grid max-w-lg gap-3 rounded-lg border p-4"
            @success="email = ''"
        >
            <div class="grid gap-2">
                <Label for="email">Email teman</Label>
                <Input
                    id="email"
                    v-model="email"
                    name="email"
                    type="email"
                    required
                    placeholder="teman@example.com"
                />
                <InputError :message="$page.props.errors?.email" />
            </div>
            <Button type="submit" class="w-fit">Kirim permintaan</Button>
        </Form>

        <section class="grid gap-2">
            <h2 class="font-medium">Permintaan masuk</h2>
            <p v-if="incoming.length === 0" class="text-sm text-muted-foreground">
                Tidak ada permintaan.
            </p>
            <div
                v-for="row in incoming"
                :key="row.friendship_id"
                class="flex flex-wrap items-center justify-between gap-2 rounded-lg border p-3"
            >
                <div>
                    <p class="font-medium">{{ row.name }}</p>
                    <p class="text-sm text-muted-foreground">{{ row.email }}</p>
                </div>
                <div class="flex gap-2">
                    <Form v-bind="FriendshipController.accept.form(row.friendship_id)">
                        <Button type="submit" size="sm">Terima</Button>
                    </Form>
                    <Form v-bind="FriendshipController.decline.form(row.friendship_id)">
                        <Button type="submit" size="sm" variant="outline">Tolak</Button>
                    </Form>
                </div>
            </div>
        </section>

        <section class="grid gap-2">
            <h2 class="font-medium">Menunggu konfirmasi</h2>
            <p v-if="outgoing.length === 0" class="text-sm text-muted-foreground">
                Tidak ada permintaan keluar.
            </p>
            <div
                v-for="row in outgoing"
                :key="row.friendship_id"
                class="flex flex-wrap items-center justify-between gap-2 rounded-lg border p-3"
            >
                <div>
                    <p class="font-medium">{{ row.name }}</p>
                    <p class="text-sm text-muted-foreground">{{ row.email }}</p>
                </div>
                <Form v-bind="FriendshipController.destroy.form(row.friendship_id)">
                    <Button type="submit" size="sm" variant="outline">Batalkan</Button>
                </Form>
            </div>
        </section>

        <section class="grid gap-2">
            <h2 class="font-medium">Teman</h2>
            <p v-if="friends.length === 0" class="text-sm text-muted-foreground">
                Belum ada teman.
            </p>
            <div
                v-for="row in friends"
                :key="row.friendship_id"
                class="flex flex-wrap items-center justify-between gap-2 rounded-lg border p-3"
            >
                <div>
                    <p class="font-medium">{{ row.name }}</p>
                    <p class="text-sm text-muted-foreground">{{ row.email }}</p>
                </div>
                <Form v-bind="FriendshipController.destroy.form(row.friendship_id)">
                    <Button type="submit" size="sm" variant="destructive">Hapus</Button>
                </Form>
            </div>
        </section>

        <section class="grid gap-2">
            <h2 class="font-medium">Rencana dibagikan teman</h2>
            <p v-if="sharedPlans.length === 0" class="text-sm text-muted-foreground">
                Belum ada rencana yang dibagikan.
            </p>
            <div
                v-for="plan in sharedPlans"
                :key="plan.id"
                class="flex flex-wrap items-center justify-between gap-2 rounded-lg border p-3"
            >
                <div>
                    <p class="font-medium">{{ plan.name }}</p>
                    <p class="text-sm text-muted-foreground">
                        {{ plan.owner_name }} · {{ plan.offering_title }}
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button as-child size="sm" variant="outline">
                        <Link
                            :href="
                                planner({
                                    offering: plan.offering_id,
                                    plan: plan.id,
                                })
                            "
                        >
                            Lihat
                        </Link>
                    </Button>
                    <Button size="sm" variant="outline" @click="openCompare(plan)">
                        Bandingkan
                    </Button>
                    <Form v-bind="copyFrom.form(plan.id)">
                        <Button type="submit" size="sm">Salin ke rencanaku</Button>
                    </Form>
                </div>
            </div>
        </section>

        <Dialog v-model:open="compareOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Bandingkan dengan rencana teman</DialogTitle>
                    <DialogDescription>
                        Pilih rencana Anda dari katalog
                        {{ selectedFriendPlan?.offering_title }} untuk dibandingkan dengan
                        {{ selectedFriendPlan?.name }}.
                    </DialogDescription>
                </DialogHeader>

                <div class="grid gap-3 py-2">
                    <p
                        v-if="compatibleMyPlans.length === 0"
                        class="text-sm text-muted-foreground"
                    >
                        Anda belum punya rencana di katalog ini.
                        <Link :href="krsIndex()" class="underline">Buat di KRS Planner</Link>
                        dulu.
                    </p>
                    <div v-else class="grid gap-2">
                        <Label for="my-plan">Rencana saya</Label>
                        <select
                            id="my-plan"
                            v-model.number="selectedMyPlanId"
                            class="flex h-11 w-full rounded-md border bg-transparent px-3 py-1 text-sm"
                        >
                            <option
                                v-for="plan in compatibleMyPlans"
                                :key="plan.id"
                                :value="plan.id"
                            >
                                {{ plan.name }} ({{ plan.items_count }} kelompok)
                            </option>
                        </select>
                    </div>
                </div>

                <DialogFooter>
                    <Button
                        :disabled="!selectedMyPlanId || compatibleMyPlans.length === 0"
                        @click="goCompare"
                    >
                        Bandingkan
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
