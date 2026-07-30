<?php

namespace App\Services\Clockify;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

/**
 * Thin wrapper around the Clockify v1 REST API (https://docs.clockify.me).
 * Knows nothing about our domain models — it only fetches raw arrays.
 * Mapping Clockify's shape onto our models is ClockifyImportService's job.
 */
class ClockifyClient
{
    private const int PAGE_SIZE = 200;

    public function __construct(
        private readonly string $apiKey,
        private readonly string $baseUrl,
    ) {}

    /**
     * The workspace user the API key belongs to.
     *
     * @return array<string, mixed>
     */
    public function currentUser(): array
    {
        return $this->get('/user');
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function clients(string $workspaceId): Collection
    {
        return $this->paginate("/workspaces/{$workspaceId}/clients");
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function projects(string $workspaceId): Collection
    {
        return $this->paginate("/workspaces/{$workspaceId}/projects");
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function tasks(string $workspaceId, string $projectId): Collection
    {
        return $this->paginate("/workspaces/{$workspaceId}/projects/{$projectId}/tasks");
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function tags(string $workspaceId): Collection
    {
        return $this->paginate("/workspaces/{$workspaceId}/tags");
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function timeEntries(string $workspaceId, string $userId, ?string $start = null, ?string $end = null): Collection
    {
        $query = array_filter([
            'start' => $start,
            'end' => $end,
        ]);

        return $this->paginate("/workspaces/{$workspaceId}/user/{$userId}/time-entries", $query);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return Collection<int, array<string, mixed>>
     */
    private function paginate(string $path, array $query = []): Collection
    {
        $results = collect();
        $page = 1;

        do {
            /** @var array<int, array<string, mixed>> $pageResults */
            $pageResults = $this->get($path, [
                ...$query,
                'page' => $page,
                'page-size' => self::PAGE_SIZE,
            ]);

            $results = $results->concat($pageResults);
            $page++;
        } while (count($pageResults) === self::PAGE_SIZE);

        return $results;
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<mixed>
     */
    private function get(string $path, array $query = []): array
    {
        $response = Http::withHeaders(['X-Api-Key' => $this->apiKey])
            ->baseUrl($this->baseUrl)
            ->retry(3, 1000, fn ($exception) => $exception instanceof RequestException
                && $exception->response->status() === 429)
            ->timeout(30)
            ->get($path, $query);

        if ($response->failed()) {
            throw new ClockifyApiException(
                "Clockify API error on GET {$path}: HTTP {$response->status()} — ".$response->body(),
            );
        }

        return $response->json() ?? [];
    }
}
