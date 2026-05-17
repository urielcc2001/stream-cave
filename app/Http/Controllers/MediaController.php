<?php

namespace App\Http\Controllers;

use App\Models\Episode;
use App\Models\Media;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class MediaController extends Controller
{
    public function index(Request $request)
    {
        $query = Media::query()
            ->when($request->type, fn($q, $type) => $q->where('type', $type))
            ->when($request->search, fn($q, $search) => $q->where('title', 'like', "%{$search}%"))
            ->orderBy('title');

        $media = $query->paginate(40)->withQueryString();

        return Inertia::render('Catalog/Index', [
            'media'   => $media,
            'filters' => $request->only(['type', 'search']),
        ]);
    }

    public function show(Media $media)
    {
        if ($media->type === 'series') {
            $media->load('seasons.episodes');
        }
        return Inertia::render('Media/Show', ['media' => $media]);
    }

    public function play(Media $media)
    {
        $info = $media->file_path ? $this->probeFile($media->file_path) : [];

        return Inertia::render('Media/Player', [
            'title'          => $media->title,
            'streamUrl'      => route('media.stream', $media),
            'backUrl'        => route('media.show', $media),
            'audioTracks'    => $info['audioTracks']    ?? [],
            'subtitleTracks' => $info['subtitleTracks'] ?? [],
            'fileSize'       => $info['fileSize']       ?? null,
            'videoCodec'     => $info['videoCodec']     ?? null,
            'audioCodec'     => $info['audioCodec']     ?? null,
            'mediaId'        => $media->id,
        ]);
    }

    public function playEpisode(Episode $episode)
    {
        $episode->load('season.media');
        $info = $episode->file_path ? $this->probeFile($episode->file_path) : [];

        $seriesTitle = $episode->season->media->title;
        $label = "T{$episode->season->number} E{$episode->number}" . ($episode->title ? " · {$episode->title}" : '');

        return Inertia::render('Media/Player', [
            'title'          => "{$seriesTitle} — {$label}",
            'streamUrl'      => route('episode.stream', $episode),
            'backUrl'        => route('media.show', $episode->season->media_id),
            'audioTracks'    => $info['audioTracks']    ?? [],
            'subtitleTracks' => $info['subtitleTracks'] ?? [],
            'fileSize'       => $info['fileSize']       ?? null,
            'videoCodec'     => $info['videoCodec']     ?? null,
            'audioCodec'     => $info['audioCodec']     ?? null,
            'mediaId'        => null,
            'episodeId'      => $episode->id,
        ]);
    }

    // ── Streaming ──────────────────────────────────────────────────────────

    public function stream(Media $media, Request $request): BinaryFileResponse
    {
        abort_unless($media->file_path && file_exists($media->file_path), 404);

        $audioTrack = (int) $request->query('audio', 0);

        if ($this->needsTranscode($media->file_path) || $audioTrack > 0) {
            $this->streamViaFfmpeg($media->file_path, $audioTrack);
        }

        return response()->file($media->file_path, [
            'Content-Type' => $this->mimeFor($media->file_path),
        ]);
    }

    public function streamEpisode(Episode $episode, Request $request): BinaryFileResponse
    {
        abort_unless($episode->file_path && file_exists($episode->file_path), 404);

        $audioTrack = (int) $request->query('audio', 0);

        if ($this->needsTranscode($episode->file_path) || $audioTrack > 0) {
            $this->streamViaFfmpeg($episode->file_path, $audioTrack);
        }

        return response()->file($episode->file_path, [
            'Content-Type' => $this->mimeFor($episode->file_path),
        ]);
    }

    public function subtitlesEpisode(Episode $episode, int $index): Response
    {
        abort_unless($episode->file_path && file_exists($episode->file_path), 404);

        $cmd = sprintf(
            'ffmpeg -i %s -map 0:s:%d -f webvtt pipe:1 2>/dev/null',
            escapeshellarg($episode->file_path),
            $index
        );

        $vtt = shell_exec($cmd) ?? '';

        abort_if(empty($vtt), 404);

        return response($vtt, 200, [
            'Content-Type'                => 'text/vtt; charset=UTF-8',
            'Access-Control-Allow-Origin' => '*',
        ]);
    }

    public function subtitles(Media $media, int $index): Response
    {
        abort_unless($media->file_path && file_exists($media->file_path), 404);

        $cmd = sprintf(
            'ffmpeg -i %s -map 0:s:%d -f webvtt pipe:1 2>/dev/null',
            escapeshellarg($media->file_path),
            $index
        );

        $vtt = shell_exec($cmd) ?? '';

        abort_if(empty($vtt), 404);

        return response($vtt, 200, [
            'Content-Type'                => 'text/vtt; charset=UTF-8',
            'Access-Control-Allow-Origin' => '*',
        ]);
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    private function needsTranscode(string $path): bool
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if ($ext === 'avi') return true;

        if (in_array($ext, ['mkv', 'mp4', 'mov'])) {
            $probe = trim(shell_exec(
                'ffprobe -v quiet -select_streams a:0 -show_entries stream=codec_name -of csv=p=0 '
                . escapeshellarg($path) . ' 2>/dev/null'
            ) ?? '');
            return in_array($probe, ['ac3', 'dts', 'eac3', 'truehd', 'mlp']);
        }

        return false;
    }

    private function streamViaFfmpeg(string $path, int $audioTrack = 0): never
    {
        ignore_user_abort(true);
        set_time_limit(0);

        while (ob_get_level() > 0) ob_end_clean();
        ob_implicit_flush(true);

        header('Content-Type: video/mp4');
        header('Cache-Control: no-cache, no-store');
        header('X-Accel-Buffering: no');
        header('X-Content-Type-Options: nosniff');

        // Mapeamos explícitamente la pista de video y audio seleccionada
        // para evitar ambigüedad con archivos que tienen múltiples pistas
        $cmd = sprintf(
            'ffmpeg -i %s -map 0:v:0 -map 0:a:%d -c:v copy -c:a aac -b:a 192k -ac 2 -movflags frag_keyframe+empty_moov+default_base_moof -f mp4 pipe:1 2>/dev/null',
            escapeshellarg($path),
            $audioTrack
        );

        $handle = popen($cmd, 'r');

        if ($handle === false) {
            http_response_code(500);
            exit;
        }

        fpassthru($handle);
        pclose($handle);
        exit;
    }

    private function probeFile(string $path): array
    {
        if (!file_exists($path)) return [];

        $cmd = sprintf(
            'ffprobe -v quiet -print_format json -show_streams %s 2>/dev/null',
            escapeshellarg($path)
        );

        $json    = shell_exec($cmd) ?? '{}';
        $data    = json_decode($json, true);
        $streams = $data['streams'] ?? [];

        $audioTracks    = [];
        $subtitleTracks = [];
        $videoCodec     = null;
        $audioCodec     = null;

        foreach ($streams as $stream) {
            $type = $stream['codec_type'] ?? '';

            if ($type === 'video' && !$videoCodec) {
                $videoCodec = strtoupper($stream['codec_name'] ?? '');
            }

            if ($type === 'audio') {
                if (!$audioCodec) $audioCodec = strtoupper($stream['codec_name'] ?? '');
                $lang  = $stream['tags']['language'] ?? null;
                $title = $stream['tags']['title']    ?? null;
                $audioTracks[] = [
                    'index'    => count($audioTracks),
                    'label'    => $title ?? ($lang ? strtoupper($lang) : 'Audio ' . (count($audioTracks) + 1)),
                    'language' => $lang,
                    'codec'    => strtoupper($stream['codec_name'] ?? ''),
                    'channels' => $stream['channels'] ?? null,
                ];
            }

            if ($type === 'subtitle') {
                $lang  = $stream['tags']['language'] ?? null;
                $title = $stream['tags']['title']    ?? null;
                $subtitleTracks[] = [
                    'index'    => count($subtitleTracks),
                    'label'    => $title ?? ($lang ? strtoupper($lang) : 'Sub ' . (count($subtitleTracks) + 1)),
                    'language' => $lang,
                    'codec'    => $stream['codec_name'] ?? '',
                ];
            }
        }

        return [
            'audioTracks'    => $audioTracks,
            'subtitleTracks' => $subtitleTracks,
            'fileSize'       => filesize($path),
            'videoCodec'     => $videoCodec,
            'audioCodec'     => $audioCodec,
        ];
    }

    private function mimeFor(string $path): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'mp4'  => 'video/mp4',
            'mkv'  => 'video/x-matroska',
            'avi'  => 'video/x-msvideo',
            'webm' => 'video/webm',
            'mov'  => 'video/quicktime',
            default => 'application/octet-stream',
        };
    }
}
