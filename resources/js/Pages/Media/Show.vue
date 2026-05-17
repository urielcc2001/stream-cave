<script setup>
import { ref } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'

const props = defineProps({
    media: Object,
})

const user = usePage().props.auth.user
const openSeason = ref(props.media.seasons?.[0]?.id ?? null)

function toggleSeason(id) {
    openSeason.value = openSeason.value === id ? null : id
}

function posterUrl(path) {
    return path ? `/storage/${path}` : null
}

function backdropUrl(path) {
    return path ? `/storage/${path}` : null
}

function cardColor(title) {
    const colors = [
        'from-red-900 to-red-800',
        'from-blue-900 to-blue-800',
        'from-purple-900 to-purple-800',
        'from-green-900 to-green-800',
        'from-yellow-900 to-yellow-800',
        'from-pink-900 to-pink-800',
        'from-indigo-900 to-indigo-800',
        'from-teal-900 to-teal-800',
    ]
    let hash = 0
    for (let i = 0; i < title.length; i++) hash += title.charCodeAt(i)
    return colors[hash % colors.length]
}

const firstEpisode = props.media.type === 'series'
    ? props.media.seasons?.[0]?.episodes?.[0] ?? null
    : null
</script>

<template>
    <div class="min-h-screen bg-[#141414] text-white overflow-x-hidden">

        <!-- Navbar fijo -->
        <header class="fixed top-0 inset-x-0 z-50 flex items-center justify-between px-8 py-4 bg-gradient-to-b from-black/90 to-transparent">
            <div class="flex items-center gap-6">
                <Link :href="route('catalog')" class="text-gray-400 hover:text-white flex items-center gap-2 text-sm">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                    Catálogo
                </Link>
                <span class="text-red-600 font-black text-xl tracking-widest">STREAMCAVE</span>
            </div>
            <div class="flex items-center gap-4">
                <span class="text-sm text-gray-300">{{ user.name }}</span>
                <Link :href="route('logout')" method="post" as="button" class="text-sm text-gray-400 hover:text-white">Salir</Link>
            </div>
        </header>

        <!-- Hero: altura fija mínima para que quepa el contenido -->
        <div class="relative" style="min-height: 620px;">

            <!-- Backdrop o gradiente (posición absoluta cubriendo todo el hero) -->
            <img
                v-if="media.backdrop_path"
                :src="backdropUrl(media.backdrop_path)"
                class="absolute inset-0 w-full h-full object-cover"
                style="min-height: 620px;"
            />
            <div
                v-else
                :class="`absolute inset-0 bg-gradient-to-br ${cardColor(media.title)}`"
                style="min-height: 620px;"
            />

            <!-- Gradientes de oscurecimiento -->
            <div class="absolute inset-0 bg-gradient-to-r from-black/85 via-black/50 to-transparent" />
            <div class="absolute inset-0 bg-gradient-to-t from-[#141414] via-transparent to-black/40" />

            <!-- Contenido del hero (relativo para estar sobre los absolutos) -->
            <div class="relative z-10 flex items-end px-8 lg:px-16 pb-16" style="min-height: 620px; padding-top: 100px;">
                <div class="flex items-end gap-8 w-full max-w-4xl">

                    <!-- Poster (solo desktop) -->
                    <img
                        v-if="media.poster_path"
                        :src="posterUrl(media.poster_path)"
                        :alt="media.title"
                        class="hidden lg:block w-40 rounded-lg shadow-2xl flex-shrink-0"
                    />

                    <!-- Info -->
                    <div class="flex-1" style="min-width: 0;">
                        <h1 class="text-4xl lg:text-5xl font-bold mb-3 leading-tight" style="word-break: break-word;">
                            {{ media.title }}
                        </h1>

                        <div class="flex flex-wrap items-center gap-3 mb-4 text-sm">
                            <span v-if="media.year" class="text-gray-300">{{ media.year }}</span>
                            <span v-if="media.rating" class="flex items-center gap-1 text-yellow-400">
                                <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>
                                </svg>
                                {{ media.rating.toFixed(1) }}
                            </span>
                            <span class="border border-gray-500 text-gray-400 text-xs px-2 py-0.5 rounded">
                                {{ media.type === 'movie' ? 'Película' : 'Serie' }}
                            </span>
                        </div>

                        <p v-if="media.description" class="text-gray-300 text-sm leading-relaxed mb-6"
                           style="max-width: 560px; word-break: break-word; display: -webkit-box; -webkit-line-clamp: 4; -webkit-box-orient: vertical; overflow: hidden;">
                            {{ media.description }}
                        </p>

                        <!-- Reproducir película -->
                        <Link
                            v-if="media.type === 'movie'"
                            :href="route('media.play', media.id)"
                            class="inline-flex items-center gap-3 bg-white text-black font-bold px-8 py-3 rounded hover:bg-gray-200 transition-colors"
                        >
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M8 5v14l11-7z"/>
                            </svg>
                            Reproducir
                        </Link>

                        <!-- Reproducir primer episodio (serie) -->
                        <Link
                            v-else-if="firstEpisode"
                            :href="route('episode.play', firstEpisode.id)"
                            class="inline-flex items-center gap-3 bg-white text-black font-bold px-8 py-3 rounded hover:bg-gray-200 transition-colors"
                        >
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M8 5v14l11-7z"/>
                            </svg>
                            Reproducir
                        </Link>
                    </div>
                </div>
            </div>
        </div>

        <!-- Cuerpo: descripción completa + episodios -->
        <div class="px-8 lg:px-16 pb-20">

            <!-- Descripción completa -->
            <p v-if="media.description" class="text-gray-400 text-sm leading-relaxed mb-10"
               style="max-width: 700px; word-break: break-word; white-space: normal;">
                {{ media.description }}
            </p>

            <!-- Episodios (series) -->
            <div v-if="media.type === 'series' && media.seasons?.length">
                <h2 class="text-xl font-bold mb-6">Episodios</h2>

                <div v-for="season in media.seasons" :key="season.id" class="mb-4">

                    <!-- Cabecera temporada -->
                    <button
                        @click="toggleSeason(season.id)"
                        class="w-full flex items-center justify-between px-4 py-3 bg-gray-900 hover:bg-gray-800 rounded-lg transition-colors text-left"
                    >
                        <span class="font-semibold">
                            Temporada {{ season.number }}<span v-if="season.title"> — {{ season.title }}</span>
                        </span>
                        <svg
                            class="w-5 h-5 text-gray-400 transition-transform"
                            :class="openSeason === season.id ? 'rotate-180' : ''"
                            fill="none" stroke="currentColor" viewBox="0 0 24 24"
                        >
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>

                    <!-- Lista episodios -->
                    <div v-if="openSeason === season.id" class="mt-1 space-y-1">
                        <div
                            v-for="ep in season.episodes"
                            :key="ep.id"
                            class="flex items-center gap-4 px-4 py-3 bg-gray-900/50 hover:bg-gray-800/70 rounded-lg group transition-colors"
                        >
                            <span class="text-gray-500 text-sm w-8 text-right flex-shrink-0">{{ ep.number }}</span>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium truncate">{{ ep.title || `Episodio ${ep.number}` }}</p>
                                <p v-if="ep.description" class="text-xs text-gray-500 truncate mt-0.5">{{ ep.description }}</p>
                            </div>
                            <Link
                                :href="route('episode.play', ep.id)"
                                class="flex-shrink-0 opacity-0 group-hover:opacity-100 flex items-center gap-1.5 bg-white text-black text-xs font-bold px-4 py-1.5 rounded transition-opacity"
                            >
                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M8 5v14l11-7z"/>
                                </svg>
                                Play
                            </Link>
                        </div>

                        <div v-if="!season.episodes?.length" class="px-4 py-3 text-sm text-gray-500">
                            Sin episodios registrados.
                        </div>
                    </div>
                </div>
            </div>

            <div v-else-if="media.type === 'series'" class="text-gray-500 text-sm">
                No se encontraron episodios para esta serie.
            </div>
        </div>
    </div>
</template>
