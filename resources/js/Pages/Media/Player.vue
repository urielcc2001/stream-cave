<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'

const props = defineProps({
    title:          String,
    streamUrl:      String,
    backUrl:        String,
    audioTracks:    { type: Array, default: () => [] },
    subtitleTracks: { type: Array, default: () => [] },
    fileSize:       Number,
    videoCodec:     String,
    audioCodec:     String,
    mediaId:        Number,
    episodeId:      Number,
})

const videoEl      = ref(null)
const isPlaying    = ref(false)
const isMuted      = ref(false)
const currentTime  = ref(0)
const duration     = ref(0)
const videoW       = ref(0)
const videoH       = ref(0)
const showControls = ref(true)
const showSettings = ref(false)
const activeAudio  = ref(0)
const activeSub    = ref(-1)

let hideTimer = null
let savedTime = 0

// ── Stream URL ──────────────────────────────────────────────────────────────
const currentStreamUrl = computed(() => {
    if (activeAudio.value === 0) return props.streamUrl
    const sep = props.streamUrl.includes('?') ? '&' : '?'
    return `${props.streamUrl}${sep}audio=${activeAudio.value}`
})

// ── Resolución ──────────────────────────────────────────────────────────────
const resolutionLabel = computed(() => {
    const h = videoH.value
    if (!h) return null
    if (h >= 2160) return '4K'
    if (h >= 1440) return '1440p'
    if (h >= 1080) return '1080p'
    if (h >= 720)  return '720p'
    if (h >= 480)  return '480p'
    return `${h}p`
})

// ── Tamaño ──────────────────────────────────────────────────────────────────
const fileSizeStr = computed(() => {
    const b = props.fileSize
    if (!b) return null
    if (b >= 1024 ** 3) return (b / 1024 ** 3).toFixed(2) + ' GB'
    return (b / 1024 ** 2).toFixed(0) + ' MB'
})

// ── URLs de subtítulos ───────────────────────────────────────────────────────
function subtitleUrl(index) {
    if (props.mediaId)   return `/media/${props.mediaId}/subtitles/${index}`
    if (props.episodeId) return `/episode/${props.episodeId}/subtitles/${index}`
    return ''
}

// ── Tiempo ──────────────────────────────────────────────────────────────────
function fmt(s) {
    s = Math.floor(s || 0)
    const h   = Math.floor(s / 3600)
    const m   = Math.floor((s % 3600) / 60)
    const sec = s % 60
    if (h > 0) return `${h}:${String(m).padStart(2,'0')}:${String(sec).padStart(2,'0')}`
    return `${m}:${String(sec).padStart(2,'0')}`
}
const timeStr     = computed(() => fmt(currentTime.value))
const durationStr = computed(() => fmt(duration.value))
const progress    = computed(() => duration.value ? (currentTime.value / duration.value) * 100 : 0)

// ── Controles básicos ───────────────────────────────────────────────────────
function togglePlay() {
    if (!videoEl.value) return
    videoEl.value.paused ? videoEl.value.play() : videoEl.value.pause()
}
function toggleMute() {
    if (!videoEl.value) return
    videoEl.value.muted = !videoEl.value.muted
    isMuted.value = videoEl.value.muted
}
function seek(e) {
    if (!videoEl.value || !duration.value) return
    const rect  = e.currentTarget.getBoundingClientRect()
    const ratio = Math.max(0, Math.min(1, (e.clientX - rect.left) / rect.width))
    videoEl.value.currentTime = ratio * duration.value
}
function toggleFullscreen() {
    document.fullscreenElement
        ? document.exitFullscreen()
        : document.documentElement.requestFullscreen()
}

// ── Pista de audio ───────────────────────────────────────────────────────────
function switchAudio(index) {
    if (activeAudio.value === index) return
    savedTime = videoEl.value?.currentTime ?? 0
    activeAudio.value = index
    closeSettings()
}

// ── Subtítulos ───────────────────────────────────────────────────────────────
function applySubMode() {
    if (!videoEl.value) return
    const tracks = videoEl.value.textTracks
    for (let i = 0; i < tracks.length; i++) {
        tracks[i].mode = (i === activeSub.value) ? 'showing' : 'hidden'
    }
}
function switchSub(index) {
    activeSub.value = (activeSub.value === index) ? -1 : index
    applySubMode()
}
function toggleCC() {
    switchSub(activeSub.value !== -1 ? -1 : 0)
}

// ── Panel settings ───────────────────────────────────────────────────────────
function toggleSettings() {
    showSettings.value = !showSettings.value
    if (showSettings.value) {
        clearTimeout(hideTimer)
        showControls.value = true
    } else {
        scheduleHide()
    }
}
function closeSettings() {
    showSettings.value = false
    scheduleHide()
}

// ── Eventos video ────────────────────────────────────────────────────────────
function onMetadata() {
    duration.value = videoEl.value.duration
    videoW.value   = videoEl.value.videoWidth
    videoH.value   = videoEl.value.videoHeight
    if (savedTime > 0) {
        videoEl.value.currentTime = savedTime
        savedTime = 0
    }
    applySubMode()
}
function onTimeUpdate() { currentTime.value = videoEl.value.currentTime }
function onPlay()  { isPlaying.value = true;  scheduleHide() }
function onPause() { isPlaying.value = false; showControls.value = true; clearTimeout(hideTimer) }
function onEnded() { isPlaying.value = false; showControls.value = true }

// ── Auto-ocultar ─────────────────────────────────────────────────────────────
function scheduleHide() {
    clearTimeout(hideTimer)
    showControls.value = true
    if (isPlaying.value && !showSettings.value) {
        hideTimer = setTimeout(() => {
            if (!showSettings.value) showControls.value = false
        }, 3000)
    }
}
function onMouseMove() { scheduleHide() }
function onMouseLeave() {
    if (isPlaying.value && !showSettings.value) showControls.value = false
}

// ── Teclado ──────────────────────────────────────────────────────────────────
function onKey(e) {
    if (['INPUT','TEXTAREA'].includes(e.target.tagName)) return
    if (e.code === 'Space')      { e.preventDefault(); togglePlay() }
    if (e.code === 'ArrowRight') { if (videoEl.value) videoEl.value.currentTime += 10 }
    if (e.code === 'ArrowLeft')  { if (videoEl.value) videoEl.value.currentTime -= 10 }
    if (e.code === 'ArrowUp')    { if (videoEl.value) videoEl.value.volume = Math.min(1, videoEl.value.volume + 0.1) }
    if (e.code === 'ArrowDown')  { if (videoEl.value) videoEl.value.volume = Math.max(0, videoEl.value.volume - 0.1) }
    if (e.code === 'KeyF')       { toggleFullscreen() }
    if (e.code === 'KeyM')       { toggleMute() }
    if (e.code === 'Escape')     { closeSettings() }
}

onMounted(()   => window.addEventListener('keydown', onKey))
onUnmounted(() => { clearTimeout(hideTimer); window.removeEventListener('keydown', onKey) })
</script>

<template>
    <div
        class="fixed inset-0 bg-black overflow-hidden"
        :style="{ cursor: (showControls || !isPlaying) ? 'default' : 'none' }"
        @mousemove="onMouseMove"
        @mouseleave="onMouseLeave"
        @click="showSettings ? closeSettings() : null"
    >
        <!-- ── VIDEO ─────────────────────────────────────────────────────── -->
        <video
            ref="videoEl"
            :src="currentStreamUrl"
            autoplay
            class="absolute inset-0 w-full h-full object-contain"
            @click.stop="togglePlay"
            @loadedmetadata="onMetadata"
            @timeupdate="onTimeUpdate"
            @play="onPlay"
            @pause="onPause"
            @ended="onEnded"
        >
            <template v-for="(sub, i) in subtitleTracks" :key="i">
                <track
                    v-if="subtitleUrl(i)"
                    kind="subtitles"
                    :src="subtitleUrl(i)"
                    :srclang="sub.language ?? 'und'"
                    :label="sub.label"
                />
            </template>
        </video>

        <!-- ── GRADIENTES ─────────────────────────────────────────────────── -->
        <Transition name="ui">
            <div v-show="showControls || !isPlaying" class="absolute inset-0 pointer-events-none">
                <div class="absolute top-0 inset-x-0 h-40 bg-gradient-to-b from-black/75 to-transparent" />
                <div class="absolute bottom-0 inset-x-0 h-48 bg-gradient-to-t from-black/85 to-transparent" />
            </div>
        </Transition>

        <!-- ── TOP BAR ────────────────────────────────────────────────────── -->
        <Transition name="ui">
            <div
                v-show="showControls || !isPlaying"
                class="absolute top-0 inset-x-0 flex items-center gap-4 px-8 pt-6 pointer-events-auto"
            >
                <a
                    :href="backUrl"
                    class="flex items-center gap-2 text-white/80 hover:text-white text-sm transition-colors flex-shrink-0"
                >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                    </svg>
                    Volver
                </a>
                <span class="flex-1 text-white/70 text-sm truncate">{{ title }}</span>
                <div class="flex items-center gap-2 flex-shrink-0">
                    <span v-if="resolutionLabel" class="text-xs text-white/50 border border-white/20 px-2 py-0.5 rounded">
                        {{ resolutionLabel }}
                    </span>
                    <span
                        v-if="audioTracks.length > 1 && audioTracks[activeAudio]"
                        class="text-xs text-white/50 border border-white/20 px-2 py-0.5 rounded"
                    >
                        {{ audioTracks[activeAudio].label }}
                    </span>
                </div>
            </div>
        </Transition>

        <!-- ── ICONO CENTRAL (pausa) ──────────────────────────────────────── -->
        <Transition name="pop">
            <div
                v-if="!isPlaying"
                class="absolute inset-0 flex items-center justify-center pointer-events-none"
            >
                <div class="w-20 h-20 rounded-full bg-black/50 flex items-center justify-center">
                    <svg class="w-10 h-10 text-white" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M8 5v14l11-7z"/>
                    </svg>
                </div>
            </div>
        </Transition>

        <!-- ── BARRA DE CONTROLES ─────────────────────────────────────────── -->
        <Transition name="ui">
            <div
                v-show="showControls || !isPlaying"
                class="absolute bottom-0 inset-x-0 px-8 pb-7 pointer-events-auto"
                @click.stop
            >
                <!-- Barra de progreso -->
                <div
                    class="group/prog w-full h-1 bg-white/25 rounded-full cursor-pointer mb-5 hover:h-1.5 transition-all duration-150"
                    @click="seek"
                >
                    <div class="h-full bg-red-600 rounded-full relative" :style="{ width: progress + '%' }">
                        <div class="absolute right-0 top-1/2 -translate-y-1/2 w-3.5 h-3.5 bg-white rounded-full shadow opacity-0 group-hover/prog:opacity-100 transition-opacity" />
                    </div>
                </div>

                <!-- Botones -->
                <div class="flex items-center justify-between text-white">

                    <!-- Izquierda -->
                    <div class="flex items-center gap-5">

                        <!-- Play/Pausa -->
                        <button @click="togglePlay" class="hover:text-gray-300 transition-colors">
                            <svg v-if="!isPlaying" class="w-7 h-7" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M8 5v14l11-7z"/>
                            </svg>
                            <svg v-else class="w-7 h-7" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/>
                            </svg>
                        </button>

                        <!-- −10s -->
                        <button @click="videoEl.currentTime -= 10" class="hover:text-gray-300 transition-colors" title="Retroceder 10s">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 5V1L7 6l5 5V7c3.31 0 6 2.69 6 6s-2.69 6-6 6-6-2.69-6-6H4c0 4.42 3.58 8 8 8s8-3.58 8-8-3.58-8-8-8z"/>
                            </svg>
                        </button>

                        <!-- +10s -->
                        <button @click="videoEl.currentTime += 10" class="hover:text-gray-300 transition-colors" title="Avanzar 10s">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 5V1l5 5-5 5V7c-3.31 0-6 2.69-6 6s2.69 6 6 6 6-2.69 6-6h2c0 4.42-3.58 8-8 8s-8-3.58-8-8 3.58-8 8-8z"/>
                            </svg>
                        </button>

                        <!-- Mute -->
                        <button @click="toggleMute" class="hover:text-gray-300 transition-colors">
                            <svg v-if="!isMuted" class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M3 9v6h4l5 5V4L7 9H3zm13.5 3c0-1.77-1.02-3.29-2.5-4.03v8.05c1.48-.73 2.5-2.25 2.5-4.02zM14 3.23v2.06c2.89.86 5 3.54 5 6.71s-2.11 5.85-5 6.71v2.06c4.01-.91 7-4.49 7-8.77s-2.99-7.86-7-8.77z"/>
                            </svg>
                            <svg v-else class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M16.5 12c0-1.77-1.02-3.29-2.5-4.03v2.21l2.45 2.45c.03-.2.05-.41.05-.63zm2.5 0c0 .94-.2 1.82-.54 2.64l1.51 1.51C20.63 14.91 21 13.5 21 12c0-4.28-2.99-7.86-7-8.77v2.06c2.89.86 5 3.54 5 6.71zM4.27 3L3 4.27 7.73 9H3v6h4l5 5v-6.73l4.25 4.25c-.67.52-1.42.93-2.25 1.18v2.06c1.38-.31 2.63-.95 3.69-1.81L19.73 21 21 19.73l-9-9L4.27 3zM12 4L9.91 6.09 12 8.18V4z"/>
                            </svg>
                        </button>

                        <!-- Tiempo -->
                        <span class="text-sm text-gray-300 tabular-nums select-none">
                            {{ timeStr }} / {{ durationStr }}
                        </span>
                    </div>

                    <!-- Derecha -->
                    <div class="flex items-center gap-4">

                        <!-- Botón CC (subtítulos rápido) -->
                        <button
                            v-if="subtitleTracks.length > 0"
                            @click.stop="toggleCC"
                            class="text-xs font-bold border px-2 py-0.5 rounded transition-colors"
                            :class="activeSub !== -1
                                ? 'border-red-500 text-red-500'
                                : 'border-white/30 text-white/50 hover:border-white/60 hover:text-white/80'"
                            title="Subtítulos"
                        >
                            CC
                        </button>

                        <!-- Pantalla completa -->
                        <button @click="toggleFullscreen" class="hover:text-gray-300 transition-colors">
                            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M7 14H5v5h5v-2H7v-3zm-2-4h2V7h3V5H5v5zm12 7h-3v2h5v-5h-2v3zM14 5v2h3v3h2V5h-5z"/>
                            </svg>
                        </button>

                        <!-- Menú 3 puntos -->
                        <div class="relative">
                            <button
                                @click.stop="toggleSettings"
                                class="transition-colors p-1"
                                :class="showSettings ? 'text-white' : 'hover:text-gray-300'"
                            >
                                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                                    <path d="M12 8c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zm0 2c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm0 6c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2z"/>
                                </svg>
                            </button>

                            <!-- Panel -->
                            <Transition name="pop">
                                <div
                                    v-if="showSettings"
                                    class="absolute bottom-10 right-0 bg-gray-950/97 backdrop-blur-sm border border-white/10 rounded-xl shadow-2xl overflow-hidden"
                                    style="min-width: 250px; max-width: 310px;"
                                    @click.stop
                                >
                                    <!-- Información -->
                                    <div class="px-4 pt-4 pb-3">
                                        <p class="text-xs text-gray-500 uppercase tracking-widest mb-3">Información</p>
                                        <div class="space-y-2">
                                            <div class="flex items-baseline justify-between gap-4">
                                                <span class="text-xs text-gray-400 flex-shrink-0">Resolución</span>
                                                <span class="text-xs font-semibold text-white text-right">
                                                    {{ resolutionLabel ?? '—' }}
                                                    <span v-if="videoW && videoH" class="text-gray-500 font-normal"> ({{ videoW }}×{{ videoH }})</span>
                                                </span>
                                            </div>
                                            <div v-if="fileSizeStr" class="flex items-baseline justify-between gap-4">
                                                <span class="text-xs text-gray-400 flex-shrink-0">Tamaño</span>
                                                <span class="text-xs font-semibold text-white">{{ fileSizeStr }}</span>
                                            </div>
                                            <div v-if="videoCodec" class="flex items-baseline justify-between gap-4">
                                                <span class="text-xs text-gray-400 flex-shrink-0">Vídeo</span>
                                                <span class="text-xs font-semibold text-white">{{ videoCodec }}</span>
                                            </div>
                                            <div v-if="audioCodec" class="flex items-baseline justify-between gap-4">
                                                <span class="text-xs text-gray-400 flex-shrink-0">Audio</span>
                                                <span class="text-xs font-semibold text-white">{{ audioCodec }}</span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Pistas de audio -->
                                    <template v-if="audioTracks.length > 1">
                                        <hr class="border-white/10"/>
                                        <div class="px-4 py-3">
                                            <p class="text-xs text-gray-500 uppercase tracking-widest mb-2">Audio</p>
                                            <div class="space-y-0.5">
                                                <button
                                                    v-for="track in audioTracks"
                                                    :key="track.index"
                                                    @click="switchAudio(track.index)"
                                                    class="w-full flex items-center gap-3 py-1.5 px-2 rounded-lg text-left transition-colors"
                                                    :class="activeAudio === track.index
                                                        ? 'bg-red-600/20 text-white'
                                                        : 'text-gray-400 hover:text-white hover:bg-white/5'"
                                                >
                                                    <span
                                                        class="w-2 h-2 rounded-full flex-shrink-0 transition-colors"
                                                        :class="activeAudio === track.index ? 'bg-red-500' : 'bg-gray-600'"
                                                    />
                                                    <span class="text-xs flex-1 truncate">{{ track.label }}</span>
                                                    <span v-if="track.codec" class="text-xs text-gray-600 flex-shrink-0">{{ track.codec }}</span>
                                                </button>
                                            </div>
                                        </div>
                                    </template>

                                    <!-- Pistas de subtítulos -->
                                    <template v-if="subtitleTracks.length > 0">
                                        <hr class="border-white/10"/>
                                        <div class="px-4 py-3">
                                            <p class="text-xs text-gray-500 uppercase tracking-widest mb-2">Subtítulos</p>
                                            <div class="space-y-0.5">
                                                <button
                                                    @click="switchSub(-1)"
                                                    class="w-full flex items-center gap-3 py-1.5 px-2 rounded-lg text-left transition-colors"
                                                    :class="activeSub === -1
                                                        ? 'bg-red-600/20 text-white'
                                                        : 'text-gray-400 hover:text-white hover:bg-white/5'"
                                                >
                                                    <span class="w-2 h-2 rounded-full flex-shrink-0" :class="activeSub === -1 ? 'bg-red-500' : 'bg-gray-600'" />
                                                    <span class="text-xs">Sin subtítulos</span>
                                                </button>
                                                <button
                                                    v-for="track in subtitleTracks"
                                                    :key="track.index"
                                                    @click="switchSub(track.index)"
                                                    class="w-full flex items-center gap-3 py-1.5 px-2 rounded-lg text-left transition-colors"
                                                    :class="activeSub === track.index
                                                        ? 'bg-red-600/20 text-white'
                                                        : 'text-gray-400 hover:text-white hover:bg-white/5'"
                                                >
                                                    <span
                                                        class="w-2 h-2 rounded-full flex-shrink-0"
                                                        :class="activeSub === track.index ? 'bg-red-500' : 'bg-gray-600'"
                                                    />
                                                    <span class="text-xs flex-1 truncate">{{ track.label }}</span>
                                                    <span v-if="track.codec" class="text-xs text-gray-600 flex-shrink-0">{{ track.codec }}</span>
                                                </button>
                                            </div>
                                        </div>
                                    </template>

                                    <!-- Atajos -->
                                    <hr class="border-white/10"/>
                                    <div class="px-4 py-3 pb-4">
                                        <p class="text-xs text-gray-500 uppercase tracking-widest mb-2">Atajos</p>
                                        <div class="text-xs text-gray-500 space-y-1.5">
                                            <div class="flex justify-between gap-6"><span class="text-white/60">Espacio</span><span>Play/Pausa</span></div>
                                            <div class="flex justify-between gap-6"><span class="text-white/60">← →</span><span>±10 seg</span></div>
                                            <div class="flex justify-between gap-6"><span class="text-white/60">↑ ↓</span><span>Volumen</span></div>
                                            <div class="flex justify-between gap-6"><span class="text-white/60">F</span><span>Pantalla completa</span></div>
                                            <div class="flex justify-between gap-6"><span class="text-white/60">M</span><span>Silencio</span></div>
                                        </div>
                                    </div>
                                </div>
                            </Transition>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </div>
</template>

<style scoped>
.ui-enter-active  { transition: opacity 0.3s ease; }
.ui-leave-active  { transition: opacity 0.4s ease; }
.ui-enter-from,
.ui-leave-to      { opacity: 0; }

.pop-enter-active { transition: opacity 0.15s, transform 0.15s; }
.pop-leave-active { transition: opacity 0.12s; }
.pop-enter-from   { opacity: 0; transform: scale(0.95) translateY(6px); }
.pop-leave-to     { opacity: 0; }
</style>
