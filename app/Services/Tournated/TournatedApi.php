<?php

namespace App\Services\Tournated;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Thin client for the official Tournated API v2 (https://api.tournated.com/portal/docs).
 * Rate limit: 100 requests / 60 s per key — a 429 is retried after Retry-After.
 */
class TournatedApi
{
    // Cursor pagination without a starting cursor repeats/misses rows (their
    // first page is not id-ordered — reported 2026-11-12). Starting from
    // "id:0" returns every row exactly once.
    private const FIRST_CURSOR = 'aWQ6MA';

    public function __construct(
        private readonly string $key,
        private readonly string $baseUrl,
    ) {}

    public static function fromConfig(): self
    {
        $key = (string) config('services.tournated.key');
        if ($key === '') {
            throw new RuntimeException('TOURNATED_API_KEY nenustatytas .env faile');
        }

        return new self($key, rtrim((string) config('services.tournated.base_url'), '/'));
    }

    public function get(string $path, array $query = []): array
    {
        for ($attempt = 1; ; $attempt++) {
            $res = Http::withHeaders(['x-api-key' => $this->key])
                ->acceptJson()
                ->timeout(20)
                ->get($this->baseUrl.$path, $query);

            if ($res->status() === 429 && $attempt < 3) {
                sleep(min(30, (int) ($res->header('Retry-After') ?: 10)));
                continue;
            }
            if (! $res->successful()) {
                $body = $res->body();
                throw new RuntimeException("API {$path}: HTTP {$res->status()}".(str_starts_with(ltrim($body), '<') ? ' (Cloudflare)' : ': '.mb_substr($body, 0, 200)));
            }

            return $res->json() ?? [];
        }
    }

    public function tournament(string $id): array
    {
        return $this->get("/tournaments/{$id}", ['include' => 'courts,categories', 'fields' => 'show_oop,is_groups_enabled,is_draws_enabled,updated_at']);
    }

    public function list(string $resource, string $tournamentId, array $query = []): array
    {
        $rows = [];
        $page = 1;
        do {
            $json = $this->get("/{$resource}", ['tournamentId' => $tournamentId, 'limit' => 100, 'page' => $page] + $query);
            array_push($rows, ...($json['data'] ?? []));
            $pages = (int) ($json['meta']['totalPages'] ?? 1);
        } while (++$page <= $pages && $page <= 20);

        return $rows;
    }

    /** Every match in view=full shape, each exactly once. */
    public function matchesFull(string $tournamentId): array
    {
        $byId = [];
        $cursor = self::FIRST_CURSOR;
        for ($i = 0; $cursor && $i < 50; $i++) {
            $json = $this->get('/matches', ['tournamentId' => $tournamentId, 'view' => 'full', 'limit' => 100, 'cursor' => $cursor]);
            foreach ($json['data'] ?? [] as $m) {
                $byId[$m['match_id']] = $m;
            }
            $cursor = $json['meta']['next_cursor'] ?? null;
        }

        return array_values($byId);
    }
}
