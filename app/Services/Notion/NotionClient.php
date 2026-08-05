<?php

namespace App\Services\Notion;

use Closure;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Thin wrapper around the Notion REST API (https://developers.notion.com).
 * Knows nothing about our domain models — it only fetches raw arrays.
 * Mapping Notion's shape onto our models is NotionTaskImportService's job.
 */
class NotionClient
{
    /** Notion's own hard cap for a single page of results. */
    private const int PAGE_SIZE = 100;

    public function __construct(
        private readonly string $token,
        private readonly string $baseUrl,
        private readonly string $version,
    ) {}

    /**
     * Every page of a database, following the has_more/next_cursor chain.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function queryDatabase(string $databaseId): Collection
    {
        return $this->paginate(
            fn (?string $cursor): array => $this->send('post', "/databases/{$databaseId}/query", $cursor),
        );
    }

    /**
     * The direct children of a page or block, following the same chain.
     * Blocks that themselves have children must be fetched separately.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function blockChildren(string $blockId): Collection
    {
        return $this->paginate(
            fn (?string $cursor): array => $this->send('get', "/blocks/{$blockId}/children", $cursor),
        );
    }

    /**
     * Notion paginates every list endpoint the same way: ask for a page, then
     * keep going while has_more, handing back the cursor it gave us.
     *
     * @param  Closure(?string): array<string, mixed>  $fetch
     * @return Collection<int, array<string, mixed>>
     */
    private function paginate(Closure $fetch): Collection
    {
        $results = collect();
        $cursor = null;

        do {
            $payload = $fetch($cursor);

            /** @var array<int, array<string, mixed>> $pageResults */
            $pageResults = $payload['results'] ?? [];
            $results = $results->concat($pageResults);

            $next = ($payload['has_more'] ?? false) === true ? ($payload['next_cursor'] ?? null) : null;
            $cursor = is_string($next) ? $next : null;
        } while ($cursor !== null);

        return $results;
    }

    /**
     * @return array<string, mixed>
     */
    private function send(string $method, string $path, ?string $cursor): array
    {
        $parameters = array_filter([
            'page_size' => self::PAGE_SIZE,
            'start_cursor' => $cursor,
        ]);

        $request = Http::withHeaders([
            'Authorization' => "Bearer {$this->token}",
            'Notion-Version' => $this->version,
        ])
            ->baseUrl($this->baseUrl)
            // 429 is Notion's rate limit (~3 req/s average); it answers with a
            // Retry-After header, but a flat backoff is enough at our volume.
            ->retry(3, 1000, fn ($exception) => $exception instanceof RequestException
                && $exception->response->status() === 429)
            ->timeout(30)
            ->asJson();

        // Query endpoints take their pagination in the JSON body, the
        // /blocks/{id}/children listing takes it in the query string.
        $response = $method === 'post'
            ? $request->post($path, $parameters)
            : $request->get($path, $parameters);

        if ($response->failed()) {
            throw new NotionApiException(sprintf(
                'Notion API error on %s %s: HTTP %d — %s',
                Str::upper($method),
                $path,
                $response->status(),
                $response->body(),
            ));
        }

        return $response->json() ?? [];
    }
}
