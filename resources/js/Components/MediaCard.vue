<script setup>
import { Link } from '@inertiajs/vue3'

const props = defineProps({
    media: Object,
})

function cardColor(title) {
    const colors = [
        'from-red-900 to-red-700',
        'from-blue-900 to-blue-700',
        'from-purple-900 to-purple-700',
        'from-green-900 to-green-700',
        'from-yellow-900 to-yellow-700',
        'from-pink-900 to-pink-700',
        'from-indigo-900 to-indigo-700',
        'from-teal-900 to-teal-700',
    ]
    let hash = 0
    for (let i = 0; i < title.length; i++) hash += title.charCodeAt(i)
    return colors[hash % colors.length]
}

function posterUrl(path) {
    if (!path) return null
    return `/storage/${path}`
}
</script>

<template>
    <Link :href="route('media.show', media.id)" class="group relative cursor-pointer rounded-md overflow-hidden transition-transform duration-200 hover:scale-105 hover:z-10 block">
        <!-- Poster -->
        <div class="aspect-[2/3] w-full relative">
            <img
                v-if="media.poster_path"
                :src="posterUrl(media.poster_path)"
                :alt="media.title"
                class="w-full h-full object-cover"
            />
            <div
                v-else
                :class="`bg-gradient-to-b ${cardColor(media.title)} w-full h-full flex items-end p-3`"
            >
                <span class="text-xs font-bold uppercase tracking-wider text-white/60">
                    {{ media.type === 'movie' ? 'Película' : 'Serie' }}
                </span>
            </div>
        </div>

        <!-- Overlay on hover -->
        <div class="absolute inset-0 bg-black/60 opacity-0 group-hover:opacity-100 transition-opacity flex flex-col items-center justify-center gap-2 p-3">
            <svg class="w-10 h-10 text-white" fill="currentColor" viewBox="0 0 24 24">
                <path d="M8 5v14l11-7z"/>
            </svg>
            <p class="text-white text-xs text-center font-medium leading-tight">{{ media.title }}</p>
            <p v-if="media.year" class="text-gray-300 text-xs">{{ media.year }}</p>
            <div v-if="media.rating" class="flex items-center gap-1">
                <svg class="w-3 h-3 text-yellow-400" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                </svg>
                <span class="text-yellow-400 text-xs">{{ media.rating.toFixed(1) }}</span>
            </div>
        </div>

        <!-- Title (sin póster) -->
        <div v-if="!media.poster_path" class="mt-2 px-1">
            <p class="text-sm text-white font-medium truncate">{{ media.title }}</p>
        </div>
    </Link>
</template>
