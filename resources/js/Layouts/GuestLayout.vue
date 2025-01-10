<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-gray-100 dark:bg-black">
        <div>
            <Link href="/">
                <ApplicationLogo />
            </Link>
        </div>

        <div
            class="w-full sm:max-w-md mt-6 px-6 py-4 ring-2 bg-white dark:bg-black shadow-md overflow-hidden sm:rounded-lg"
        >
            <slot />
        </div>
    </div>
</template>
