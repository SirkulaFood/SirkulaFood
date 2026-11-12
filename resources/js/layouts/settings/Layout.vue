<script setup lang="ts">
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { type NavItem } from '@/types';
import { Link } from '@inertiajs/vue3';

const sidebarNavItems: NavItem[] = [
    {
        title: 'Profil',
        href: '/settings/profile',
    },
    {
        title: 'Kata sandi',
        href: '/settings/password',
    },
    {
        title: 'Tampilan',
        href: '/settings/appearance',
    },
];

const currentPath = window.location.pathname;
</script>

<template>
    <div class="min-w-0 px-3 py-5 sm:px-4 sm:py-6">
        <Heading title="Pengaturan" description="Kelola profil dan pengaturan akun Anda" />

        <div class="flex min-w-0 flex-col space-y-6 md:space-y-0 lg:flex-row lg:space-x-12 lg:space-y-0">
            <aside class="w-full max-w-xl lg:w-48">
                <nav class="flex flex-col space-x-0 space-y-1">
                    <Button
                        v-for="item in sidebarNavItems"
                        :key="item.href"
                        variant="ghost"
                        :class="['w-full justify-start', { 'bg-muted': currentPath === item.href }]"
                        as-child
                    >
                        <Link :href="item.href">
                            {{ item.title }}
                        </Link>
                    </Button>
                </nav>
            </aside>

            <Separator class="my-6 md:hidden" />

            <div class="min-w-0 flex-1 md:max-w-2xl">
                <section class="max-w-xl space-y-12">
                    <slot />
                </section>
            </div>
        </div>
    </div>
</template>
