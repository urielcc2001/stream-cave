<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class TmdbService
{
    private string $baseUrl = 'https://api.themoviedb.org/3';
    private string $imageBase = 'https://image.tmdb.org/t/p/w500';

    private function get(string $endpoint, array $params = []): ?array
    {
        $response = Http::withToken(config('services.tmdb.token'))
            ->get("{$this->baseUrl}{$endpoint}", array_merge(['language' => 'es-MX'], $params));

        if ($response->failed()) {
            Log::warning("TMDB error en {$endpoint}", ['status' => $response->status()]);
            return null;
        }

        return $response->json();
    }

    public function searchMovie(string $title, ?int $year = null): ?array
    {
        $params = ['query' => $title];
        if ($year) $params['primary_release_year'] = $year;

        $data = $this->get('/search/movie', $params);
        return $data['results'][0] ?? null;
    }

    public function searchTv(string $title, ?int $year = null): ?array
    {
        $params = ['query' => $title];
        if ($year) $params['first_air_date_year'] = $year;

        $data = $this->get('/search/tv', $params);
        return $data['results'][0] ?? null;
    }

    public function downloadPoster(string $tmdbPath, string $filename): ?string
    {
        $url = "{$this->imageBase}{$tmdbPath}";
        $response = Http::get($url);

        if ($response->failed()) return null;

        $localPath = "posters/{$filename}";
        Storage::disk('public')->put($localPath, $response->body());

        return $localPath;
    }
}
