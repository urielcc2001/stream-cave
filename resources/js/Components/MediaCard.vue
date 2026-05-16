<script setup>
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
</script>

<template>
    <div class="group relative cursor-pointer rounded-md overflow-hidden transition-transform duration-200 hover:scale-105 hover:z-10">
        <!-- Poster placeholder -->
        <div :class="`bg-gradient-to-b ${cardColor(media.title)} aspect-[2/3] w-full flex items-end p-3`">
            <span class="text-xs font-bold uppercase tracking-wider text-white/60">
                {{ media.type === 'movie' ? 'Película' : 'Serie' }}
            </span>
        </div>

        <!-- Overlay on hover -->
        <div class="absolute inset-0 bg-black/60 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
            <svg class="w-12 h-12 text-white" fill="currentColor" viewBox="0 0 24 24">
                <path d="M8 5v14l11-7z"/>
            </svg>
        </div>

        <!-- Title -->
        <div class="mt-2 px-1">
            <p class="text-sm text-white font-medium truncate">{{ media.title }}</p>
        </div>
    </div>
</template>
