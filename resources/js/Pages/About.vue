<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    content: String
});
</script>

<template>
    <div class="min-h-screen bg-gray-50 text-gray-800 flex flex-col items-center justify-center p-6">
        <div class="max-w-xl text-center">
            <h1 class="text-3xl font-bold mb-4 text-indigo-600">About Us</h1>
            <p class="text-lg text-gray-600 mb-6">{{ content }}</p>

            <Link href="/" class="px-5 py-2.5 bg-gray-600 text-white rounded-lg shadow hover:bg-gray-700 transition">
                Back to Home
            </Link>
        </div>
    </div>
</template>