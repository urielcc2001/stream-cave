<?php

namespace App\Console\Commands;

use App\Models\Media;
use App\Services\TmdbService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('media:enrich {--limit=50 : Cuántos títulos procesar por vez} {--force : Re-procesar títulos que ya tienen póster}')]
#[Description('Busca metadatos en TMDB (póster, sinopsis, año, rating) para cada película y serie')]
class EnrichMedia extends Command
{
    public function handle(TmdbService $tmdb): void
    {
        $query = Media::query();

        if (! $this->option('force')) {
            $query->whereNull('poster_path');
        }

        $items = $query->limit((int) $this->option('limit'))->get();

        if ($items->isEmpty()) {
            $this->info('No hay títulos pendientes. Usa --force para re-procesar todo.');
            return;
        }

        $this->info("Procesando {$items->count()} títulos...");
        $bar = $this->output->createProgressBar($items->count());
        $bar->start();

        $found = 0;
        $notFound = [];

        foreach ($items as $media) {
            $searchTitle = $this->prepareSearchTitle($media->title);

            $result = $media->type === 'movie'
                ? $tmdb->searchMovie($searchTitle)
                : $tmdb->searchTv($searchTitle);

            // Si no encontró, intenta con menos palabras (primeras 3)
            if (! $result && str_word_count($searchTitle) > 3) {
                $short = implode(' ', array_slice(explode(' ', $searchTitle), 0, 3));
                $result = $media->type === 'movie'
                    ? $tmdb->searchMovie($short)
                    : $tmdb->searchTv($short);
            }

            if (! $result) {
                $notFound[] = $media->title;
                $bar->advance();
                usleep(250000);
                continue;
            }

            $updates = $this->extractUpdates($media->type, $result);

            if (! empty($result['poster_path'])) {
                $filename = $media->id . '.jpg';
                $localPath = $tmdb->downloadPoster($result['poster_path'], $filename);
                if ($localPath) $updates['poster_path'] = $localPath;
            }

            if (! empty($result['backdrop_path'])) {
                $filename = 'backdrop_' . $media->id . '.jpg';
                $localPath = $tmdb->downloadPoster($result['backdrop_path'], $filename);
                if ($localPath) $updates['backdrop_path'] = $localPath;
            }

            $media->update($updates);
            $found++;
            $bar->advance();
            usleep(250000);
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Encontrados: {$found} | Sin resultado: " . count($notFound));

        if (! empty($notFound)) {
            $this->warn('Sin resultado en TMDB:');
            foreach ($notFound as $title) {
                $this->line("  - {$title}");
            }
        }
    }

    private function prepareSearchTitle(string $title): string
    {
        // Elimina años (1900-2099)
        $title = preg_replace('/\b(19|20)\d{2}\b/', '', $title);

        // Elimina palabras de ruido típicas de filenames
        $noise = 'cinecalidad|descargapormega|descarga ya|peliculas1linkmega|elitetorrent|animers|DescargaPorMega|todo por mega|www|net|com|hd|dvdrip|bluray|brrip|webdl|webrip|imax|theatrical|rogue cut|dual|lat|latino|ingles|español|sub|subs|link|mega|bd|mkv|avi|mp4';
        $title = preg_replace('/\b(' . $noise . ')\b/i', '', $title);

        // Elimina letras o números sueltos al final (ruido residual)
        $title = preg_replace('/\s+[a-z0-9]{1,2}$/i', '', $title);

        // Limpia espacios múltiples
        $title = trim(preg_replace('/\s+/', ' ', $title));

        return $title;
    }

    private function extractUpdates(string $type, array $result): array
    {
        if ($type === 'movie') {
            return [
                'description' => $result['overview'] ?? null,
                'rating'      => $result['vote_average'] ?? null,
                'year'        => isset($result['release_date'])
                    ? (int) substr($result['release_date'], 0, 4)
                    : null,
            ];
        }

        return [
            'description' => $result['overview'] ?? null,
            'rating'      => $result['vote_average'] ?? null,
            'year'        => isset($result['first_air_date'])
                ? (int) substr($result['first_air_date'], 0, 4)
                : null,
        ];
    }
}
