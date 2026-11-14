<script setup lang="ts">
import { TransitionRoot } from '@headlessui/vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';

import DeleteUser from '@/components/DeleteUser.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { type BreadcrumbItem, type SharedData, type User } from '@/types';

interface Props {
    mustVerifyEmail: boolean;
    status?: string;
    telegramBotConfigured?: boolean;
    className?: string;
}

defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Pengaturan profil',
        href: '/settings/profile',
    },
];

const page = usePage<SharedData>();
const user = page.props.auth.user as User;

const form = useForm({
    name: user.name,
    email: user.email,
    phone: user.phone ?? '',
    telegram_chat_id: user.telegram_chat_id ?? '',
});

const submit = () => {
    form.patch(route('profile.update'), {
        preserveScroll: true,
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Pengaturan profil" />

        <SettingsLayout>
            <div class="flex flex-col space-y-6">
                <HeadingSmall title="Informasi profil" description="Perbarui informasi kontak dan ID Telegram untuk menerima pemberitahuan SirkulaFood" />

                <form @submit.prevent="submit" class="space-y-6">
                    <div class="grid gap-2">
                        <Label for="name">Nama lengkap</Label>
                        <Input id="name" class="mt-1 block w-full" v-model="form.name" required autocomplete="name" placeholder="Nama lengkap" />
                        <InputError class="mt-2" :message="form.errors.name" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="phone">Nomor telepon</Label>
                        <Input id="phone" type="tel" class="mt-1 block w-full" v-model="form.phone" autocomplete="tel" placeholder="+62..." />
                        <InputError class="mt-2" :message="form.errors.phone" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="telegram_chat_id">ID obrolan Telegram</Label>
                        <Input id="telegram_chat_id" class="mt-1 block w-full" v-model="form.telegram_chat_id" placeholder="Mulai bot SirkulaFood, lalu masukkan ID obrolan Anda" />
                        <p class="text-sm text-muted-foreground">Mulai bot SirkulaFood, kirim <code>/id</code>, lalu tempel ID obrolan numerik di sini. Nomor telepon saja tidak dapat menerima pesan bot.</p>
                        <p v-if="!telegramBotConfigured" class="text-sm text-amber-600">Bot Telegram belum dikonfigurasi di server.</p>
                        <InputError class="mt-2" :message="form.errors.telegram_chat_id" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="email">Alamat email</Label>
                        <Input
                            id="email"
                            type="email"
                            class="mt-1 block w-full"
                            v-model="form.email"
                            required
                            autocomplete="username"
                            placeholder="Alamat email"
                        />
                        <InputError class="mt-2" :message="form.errors.email" />
                    </div>

                    <div v-if="mustVerifyEmail && !user.email_verified_at">
                        <p class="mt-2 text-sm text-neutral-800">
                            Alamat email Anda belum diverifikasi.
                            <Link
                                :href="route('verification.send')"
                                method="post"
                                as="button"
                                class="focus:outline-hidden rounded-md text-sm text-neutral-600 underline hover:text-neutral-900 focus:ring-2 focus:ring-offset-2"
                            >
                                Klik di sini untuk mengirim ulang email verifikasi.
                            </Link>
                        </p>

                        <div v-if="status === 'verification-link-sent'" class="mt-2 text-sm font-medium text-green-600">
                            Tautan verifikasi baru telah dikirim ke alamat email Anda.
                        </div>
                    </div>

                    <div class="flex items-center gap-4">
                        <Button :disabled="form.processing">Simpan</Button>

                        <TransitionRoot
                            :show="form.recentlySuccessful"
                            enter="transition ease-in-out"
                            enter-from="opacity-0"
                            leave="transition ease-in-out"
                            leave-to="opacity-0"
                        >
                            <p class="text-sm text-neutral-600">Tersimpan.</p>
                        </TransitionRoot>
                    </div>
                </form>
            </div>

            <DeleteUser />
        </SettingsLayout>
    </AppLayout>
</template>
