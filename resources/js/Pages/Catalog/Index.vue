<script setup>
import { ref, watch } from 'vue'
import { router, usePage, Link } from '@inertiajs/vue3'
import MediaCard from '@/Components/MediaCard.vue'

const props = defineProps({
    media: Object,
    filters: Object,
})

const search = ref(props.filters.search ?? '')
const activeType = ref(props.filters.type ?? '')

let searchTimeout = null

watch(search, (val) => {
    clearTimeout(searchTimeout)
    searchTimeout = setTimeout(() => applyFilters(), 400)
})

function setType(type) {
    activeType.value = type
    applyFilters()
}

function applyFilters() {
    router.get(route('catalog'), {
        search: search.value || undefined,
        type: activeType.value || undefined,
    }, { preserveState: true, replace: true })
}

const user = usePage().props.auth.user
</script>

<template>
    <div class="min-h-screen bg-[#141414] text-white">

        <!-- Navbar -->
        <header class="fixed top-0 inset-x-0 z-50 bg-gradient-to-b from-black/90 to-transparent px-8 py-4 flex items-center justify-between">
            <div class="flex items-center gap-10">
                <span class="text-red-600 font-black text-2xl tracking-widest">STREAMCAVE</span>

                <nav class="flex gap-6 text-sm">
                    <button
                        @click="setType('')"
                        :class="activeType === '' ? 'text-white font-semibold' : 'text-gray-400 hover:text-white'"
                    >Inicio</button>
                    <button
                        @click="setType('movie')"
                        :class="activeType === 'movie' ? 'text-white font-semibold' : 'text-gray-400 hover:text-white'"
                    >Películas</button>
                    <button
                        @click="setType('series')"
                        :class="activeType === 'series' ? 'text-white font-semibold' : 'text-gray-400 hover:text-white'"
                    >Series</button>
                </nav>
            </div>

            <div class="flex items-center gap-4">
                <!-- Search -->
                <div class="relative">
                    <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                    </svg>
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Buscar..."
                        class="bg-black/60 border border-gray-700 text-white text-sm rounded pl-9 pr-4 py-1.5 w-48 focus:outline-none focus:border-white placeholder-gray-500"
                    />
                </div>

                <!-- User -->
                <span class="text-sm text-gray-300">{{ user.name }}</span>
                <Link :href="route('logout')" method="post" as="button"
                      class="text-sm text-gray-400 hover:text-white">Salir</Link>
            </div>
        </header>

        <!-- Content -->
        <main class="pt-24 px-8 pb-16">

            <!-- Results count -->
            <div class="mb-6 text-gray-400 text-sm">
                {{ media.total }} títulos
                <span v-if="search"> para "<span class="text-white">{{ search }}</span>"</span>
            </div>

            <!-- Grid -->
            <div v-if="media.data.length > 0" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-7 gap-4">
                <MediaCard v-for="item in media.data" :key="item.id" :media="item" />
            </div>

            <div v-else class="text-center py-32 text-gray-500">
                No se encontraron resultados.
            </div>

            <!-- Pagination -->
            <div v-if="media.last_page > 1" class="flex justify-center gap-2 mt-12">
                <a
                    v-for="link in media.links"
                    :key="link.label"
                    :href="link.url"
                    v-html="link.label"
                    @click.prevent="link.url && router.get(link.url, {}, { preserveState: true })"
                    :class="[
                        'px-3 py-1.5 rounded text-sm',
                        link.active ? 'bg-red-600 text-white' : 'bg-gray-800 text-gray-400 hover:bg-gray-700',
                        !link.url ? 'opacity-40 cursor-default' : 'cursor-pointer'
                    ]"
                />
            </div>
        </main>
    </div>
</template>
