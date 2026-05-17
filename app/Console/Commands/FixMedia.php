<?php

namespace App\Console\Commands;

use App\Models\Media;
use App\Services\TmdbService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

#[Signature('media:fix {id : ID del registro} {title : Título correcto} {--year= : Año de estreno para afinar la búsqueda}')]
#[Description('Corrige el título de un registro y busca sus metadatos en TMDB')]
class FixMedia extends Command
{
    public function handle(TmdbService $tmdb): void
    {
        $media = Media::find($this->argument('id'));

        if (! $media) {
            $this->error("No existe un registro con ID {$this->argument('id')}");
            return;
        }

        $newTitle = $this->argument('title');
        $this->info("Corrigiendo: \"{$media->title}\" → \"{$newTitle}\"");

        $year = $this->option('year') ? (int) $this->option('year') : null;

        $result = $media->type === 'movie'
            ? $tmdb->searchMovie($newTitle, $year)
            : $tmdb->searchTv($newTitle, $year);

        if (! $result) {
            $this->warn('No encontrado en TMDB. Solo se actualiza el título.');
            $media->update(['title' => $newTitle]);
            return;
        }

        $updates = ['title' => $newTitle];

        if ($media->type === 'movie') {
            $updates['description'] = $result['overview'] ?? null;
            $updates['rating']      = $result['vote_average'] ?? null;
            $updates['year']        = isset($result['release_date']) ? (int) substr($result['release_date'], 0, 4) : null;
        } else {
            $updates['description'] = $result['overview'] ?? null;
            $updates['rating']      = $result['vote_average'] ?? null;
            $updates['year']        = isset($result['first_air_date']) ? (int) substr($result['first_air_date'], 0, 4) : null;
        }

        if (! empty($result['poster_path'])) {
            if ($media->poster_path) Storage::disk('public')->delete($media->poster_path);
            $local = $tmdb->downloadPoster($result['poster_path'], $media->id . '.jpg');
            if ($local) $updates['poster_path'] = $local;
        }

        if (! empty($result['backdrop_path'])) {
            if ($media->backdrop_path) Storage::disk('public')->delete($media->backdrop_path);
            $local = $tmdb->downloadPoster($result['backdrop_path'], 'backdrop_' . $media->id . '.jpg');
            if ($local) $updates['backdrop_path'] = $local;
        }

        $media->update($updates);
        $this->info("Listo. Póster y metadatos actualizados.");
    }
}
