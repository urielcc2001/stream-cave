<?php

namespace App\Console\Commands;

use App\Models\Episode;
use App\Models\Media;
use App\Models\Season;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

#[Signature('media:scan {--fresh : Elimina todo y re-escanea desde cero}')]
#[Description('Escanea el disco duro y registra películas y series en la base de datos')]
class ScanMedia extends Command
{
    const VIDEO_EXTENSIONS = ['mp4', 'mkv', 'avi', 'mov', 'wmv', 'm4v'];

    const MOVIE_DIRS = [
        '/mnt/movies/PELICULAS',
        '/mnt/movies/Peliculas de anime',
        '/mnt/movies/pelis eyv',
        '/mnt/movies/pelis vistas',
    ];

    const SERIES_DIRS = [
        '/mnt/movies/Series',
        '/mnt/movies/ANIME',
        '/mnt/movies/Animes que no he visto xd',
    ];

    public function handle(): void
    {
        if ($this->option('fresh')) {
            Episode::truncate();
            Season::truncate();
            Media::truncate();
            $this->info('Base de datos limpiada.');
        }

        $this->info('Escaneando películas...');
        $movies = $this->scanMovies();
        $this->info("  {$movies} películas registradas.");

        $this->info('Escaneando series...');
        $series = $this->scanSeries();
        $this->info("  {$series} series registradas.");

        $this->info('Escaneo completado.');
    }

    private function scanMovies(): int
    {
        $count = 0;

        foreach (self::MOVIE_DIRS as $dir) {
            if (! is_dir($dir)) {
                $this->warn("  Carpeta no encontrada: {$dir}");
                continue;
            }

            foreach (scandir($dir) as $file) {
                if ($file === '.' || $file === '..') continue;

                $path = "{$dir}/{$file}";
                if (! is_file($path) || ! $this->isVideo($path)) continue;

                if (Media::where('file_path', $path)->exists()) continue;

                Media::create([
                    'title'     => $this->cleanTitle($file),
                    'type'      => 'movie',
                    'file_path' => $path,
                ]);

                $count++;
            }
        }

        return $count;
    }

    private function scanSeries(): int
    {
        $count = 0;

        foreach (self::SERIES_DIRS as $dir) {
            if (! is_dir($dir)) {
                $this->warn("  Carpeta no encontrada: {$dir}");
                continue;
            }

            foreach (scandir($dir) as $seriesFolder) {
                if ($seriesFolder === '.' || $seriesFolder === '..') continue;

                $seriesPath = "{$dir}/{$seriesFolder}";
                if (! is_dir($seriesPath)) continue;

                $media = Media::firstOrCreate(
                    ['file_path' => $seriesPath],
                    ['title' => $seriesFolder, 'type' => 'series']
                );

                $this->scanSeriesFolder($media, $seriesPath);
                $count++;
            }
        }

        return $count;
    }

    private function scanSeriesFolder(Media $media, string $path): void
    {
        $contents = array_diff(scandir($path), ['.', '..', 'desktop.ini']);

        $hasSeasonFolders = collect($contents)->contains(
            fn($item) => is_dir("{$path}/{$item}")
        );

        if ($hasSeasonFolders) {
            foreach ($contents as $item) {
                $itemPath = "{$path}/{$item}";
                if (! is_dir($itemPath)) continue;

                $seasonNumber = $this->extractSeasonNumber($item);
                $season = Season::firstOrCreate(
                    ['media_id' => $media->id, 'number' => $seasonNumber],
                    ['title' => $item]
                );

                $this->scanEpisodes($season, $itemPath, $seasonNumber);
            }
        } else {
            $season = Season::firstOrCreate(
                ['media_id' => $media->id, 'number' => 1],
                ['title' => 'Temporada 1']
            );

            $this->scanEpisodes($season, $path, 1);
        }
    }

    private function scanEpisodes(Season $season, string $path, int $defaultSeason): void
    {
        $files = array_diff(scandir($path), ['.', '..', 'desktop.ini']);

        $episodeNumber = 1;

        foreach ($files as $file) {
            $filePath = "{$path}/{$file}";
            if (! is_file($filePath) || ! $this->isVideo($filePath)) continue;
            if (Episode::where('file_path', $filePath)->exists()) continue;

            $extracted = $this->extractEpisodeNumber($file);

            Episode::create([
                'season_id' => $season->id,
                'number'    => $extracted ?? $episodeNumber,
                'title'     => $this->cleanTitle($file),
                'file_path' => $filePath,
            ]);

            $episodeNumber++;
        }
    }

    private function isVideo(string $path): bool
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return in_array($ext, self::VIDEO_EXTENSIONS);
    }

    private function cleanTitle(string $filename): string
    {
        $name = pathinfo($filename, PATHINFO_FILENAME);
        $name = preg_replace('/\b(1080p|720p|480p|bluray|hd|dual|lat|latino|ingles|subt|mkv|avi|mp4|www\.[^\s]+|\[.*?\]|\(.*?\))\b/i', '', $name);
        $name = preg_replace('/[-_.]/', ' ', $name);
        $name = preg_replace('/\s+/', ' ', $name);
        return trim(Str::title($name));
    }

    private function extractEpisodeNumber(string $filename): ?int
    {
        // Pattern: 1x01 o S01E01
        if (preg_match('/\d+x(\d+)/i', $filename, $m)) return (int) $m[1];
        if (preg_match('/S\d+E(\d+)/i', $filename, $m)) return (int) $m[1];
        // Pattern: nombre 01.ext
        if (preg_match('/\s(\d{1,3})\s*\./i', $filename, $m)) return (int) $m[1];
        // El filename completo es un número: 1.mp4, 01.mp4
        if (preg_match('/^(\d+)\s*\./i', $filename, $m)) return (int) $m[1];

        return null;
    }

    private function extractSeasonNumber(string $folderName): int
    {
        // "Season 2", "Temporada 2", "castlevania 2", "S2"
        if (preg_match('/(\d+)/', $folderName, $m)) return (int) $m[1];
        return 1;
    }
}
