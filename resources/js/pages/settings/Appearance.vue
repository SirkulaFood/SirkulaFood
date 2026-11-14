<script setup lang="ts">
import { Head } from '@inertiajs/vue3';

import AppearanceTabs from '@/components/AppearanceTabs.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import { type BreadcrumbItem } from '@/types';

import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Pengaturan tampilan',
        href: '/settings/appearance',
    },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Pengaturan tampilan" />

        <SettingsLayout>
            <div class="min-w-0 space-y-6">
                <HeadingSmall title="Pengaturan tampilan" description="Pilih tampilan terang, gelap, atau mengikuti sistem perangkat" />
                <AppearanceTabs />
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
