<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    href: {
        type: String,
        required: true,
    },
});
</script>

<template>
    <Link
        :href="href"
        class="block rounded-md w-full px-4 py-2 text-start text-sm leading-5 ring-2 text-gray-300 hover:bg-gray-900 focus:outline-none focus:bg-gray-800 transition duration-150 ease-in-out"
    >
        <slot />
    </Link>
</template>
