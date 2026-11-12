<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <AuthBase title="Create an account" description="Enter your details below to create your account">
        <Head title="Register" />

        <form @submit.prevent="submit" class="flex flex-col gap-6">
            <div class="grid gap-6">
                <div class="grid gap-2">
                    <Label for="name">Name</Label>
                    <Input id="name" type="text" required autofocus tabindex="1" autocomplete="name" v-model="form.name" placeholder="Full name" />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="grid gap-2">
<<<<<<< Updated upstream
=======
                    <Label for="telegram_chat_id">Telegram Chat ID</Label
                    ><Input id="telegram_chat_id" required v-model="form.telegram_chat_id" placeholder="Chat ID dari bot SirkulaFood" /><InputError
                        :message="form.errors.telegram_chat_id"
                    />
                    <p class="text-xs text-muted-foreground break-words">Untuk mendapatkan Chat ID, buka Telegram dan tekan tombol Start pada bot Telegram SirkulaFood_bot atau klik tautan ini: <a href="https://t.me/sirkulafood_bot" target="_blank" rel="noopener noreferrer" class="underline underline-offset-2">https://t.me/sirkulafood_bot</a> agar terhubung terlebih dahulu. Chat ID dipakai untuk notifikasi sesuai jenis mitra.</p>
                </div>

                <div class="grid gap-2">
                    <Label for="address">Alamat organisasi</Label
                    ><textarea
                        id="address"
                        required
                        v-model="form.address"
                        rows="3"
                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                        placeholder="Alamat untuk pengambilan donasi"
                    /><InputError :message="form.errors.address" />
                </div>

                <div class="grid gap-2">
>>>>>>> Stashed changes
                    <Label for="email">Email address</Label>
                    <Input id="email" type="email" required tabindex="2" autocomplete="email" v-model="form.email" placeholder="email@example.com" />
                    <InputError :message="form.errors.email" />
                </div>

                <div class="grid gap-2">
                    <Label for="password">Password</Label>
                    <Input
                        id="password"
                        type="password"
                        required
                        tabindex="3"
                        autocomplete="new-password"
                        v-model="form.password"
                        placeholder="Password"
                    />
                    <InputError :message="form.errors.password" />
                </div>

                <div class="grid gap-2">
                    <Label for="password_confirmation">Confirm password</Label>
                    <Input
                        id="password_confirmation"
                        type="password"
                        required
                        tabindex="4"
                        autocomplete="new-password"
                        v-model="form.password_confirmation"
                        placeholder="Confirm password"
                    />
                    <InputError :message="form.errors.password_confirmation" />
                </div>

                <Button type="submit" class="mt-2 w-full" tabindex="5" :disabled="form.processing">
                    <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                    Create account
                </Button>
            </div>

            <div class="text-center text-sm text-muted-foreground">
                Already have an account?
                <TextLink :href="route('login')" class="underline underline-offset-4" tabindex="6">Log in</TextLink>
            </div>
        </form>
    </AuthBase>
</template>
