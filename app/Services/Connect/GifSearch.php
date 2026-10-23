<?php

namespace App\Services\Connect;

use App\Services\Settings\Settings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * GIF search for chat, through GIPHY. The key stays on the server; the
 * browser only gets image links. Results are cached for a few minutes.
 */
class GifSearch
{
    public function __construct(protected Settings $settings) {}

    public function enabled(): bool
    {
        return (bool) $this->settings->giphyKey();
    }

    /** @return array<int, array{id: string, url: string, preview: string, width: int, height: int, title: string}> */
    public function search(string $query, int $limit = 24): array
    {
        $key = $this->settings->giphyKey();
        if (!$key) return [];
        $query = mb_substr(trim($query), 0, 50);
        $rating = config('services.giphy.rating', 'pg');

        return Cache::remember('giphy:' . md5($key . '|' . $rating . '|' . mb_strtolower($query) . '|' . $limit), now()->addMinutes(10), function () use ($key, $query, $limit, $rating) {
            $res = Http::timeout(6)->get('https://api.giphy.com/v1/gifs/' . ($query === '' ? 'trending' : 'search'), array_filter([
                'api_key' => $key, 'q' => $query ?: null, 'limit' => $limit, 'rating' => $rating, 'bundle' => 'messaging_non_clips',
            ]));
            if (!$res->successful()) return [];

            return collect($res->json('data', []))->map(function ($g) {
                $full = $g['images']['fixed_height'] ?? $g['images']['original'] ?? null;
                $small = $g['images']['fixed_height_small'] ?? $full;
                if (!$full || empty($full['url'])) return null;
                $clean = fn ($u) => preg_replace('~\?.*$~', '', (string) $u);

                return [
                    'id'      => (string) ($g['id'] ?? ''),
                    'url'     => $clean($full['url']),
                    'preview' => $clean($small['url'] ?? $full['url']),
                    'width'   => (int) ($full['width'] ?? 0),
                    'height'  => (int) ($full['height'] ?? 0),
                    'title'   => mb_substr((string) ($g['title'] ?? ''), 0, 120),
                ];
            })->filter(fn ($g) => $g && ConnectService::isGifUrl($g['url']))->values()->all();
        });
    }
}
