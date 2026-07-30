<?php

namespace App\Services\Clockify;

use App\Actions\TimeEntry\CreateTimeEntryAction;
use App\Enums\ProjectStatus;
use App\Enums\ProjectVisibility;
use App\Enums\TaskStatus;
use App\Enums\WorkPackageStatus;
use App\Enums\WorkspaceRole;
use App\Models\Client;
use App\Models\Project;
use App\Models\Tag;
use App\Models\Task;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\WorkPackage;
use App\Models\Workspace;
use Closure;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Imports Clients, Projects, Tasks and Time Entries from a Clockify
 * workspace into a local Workspace, via the Clockify v1 REST API.
 *
 * Clockify's domain doesn't map 1:1 onto ours:
 * - Clockify Projects can have no Client; ours always require one, so
 *   client-less Projects are attached to a single fallback "Unspecified
 *   client" Client created on demand.
 * - Clockify has no Work Package concept, but every local Task must belong
 *   to exactly one; imported Tasks are filed under a single "Import
 *   Clockify" Work Package created per Project.
 * - Only the API key owner's time entries are fetched (this is a
 *   single-user personal migration, not a multi-user one).
 *
 * Re-running the import is safe: Clients/Projects/Tags/Tasks are matched by
 * name (Tasks within their Work Package), and Time Entries are matched by
 * Clockify's own id stored in import_old_id, so already-imported records are
 * left alone rather than duplicated. Task.import_old_id also stores
 * Clockify's own task id, but only for traceability — it isn't used as a
 * match key.
 */
class ClockifyImportService
{
    private const string DEFAULT_CLIENT_NAME = 'Cliente non specificato';

    /**
     * Also referenced by ProjectToWorkPackageTransferService, to tell whether
     * a Work Package still carries this placeholder name (and can safely be
     * renamed) or was already customized.
     */
    public const string DEFAULT_WORK_PACKAGE_NAME = 'Import Clockify';

    public function __construct(
        private readonly ClockifyClient $client,
        private readonly CreateTimeEntryAction $createTimeEntryAction,
    ) {}

    public function import(
        Workspace $workspace,
        string $clockifyWorkspaceId,
        string $userEmail,
        ?Carbon $from,
        ?Carbon $until,
        bool $dryRun,
        ?Closure $onProgress = null,
    ): ClockifyImportSummary {
        $summary = new ClockifyImportSummary;
        $report = fn (string $message) => $onProgress ? $onProgress($message) : null;

        DB::beginTransaction();

        try {
            $localUser = $this->resolveLocalUser($userEmail, $summary, $report);
            $this->ensureWorkspaceMembership($workspace, $localUser);

            $report("Recupero utente Clockify associato all'API key...");
            $clockifyUser = $this->client->currentUser();
            $clockifyUserId = (string) $clockifyUser['id'];

            $report('Importazione clienti...');
            $clientMap = $this->importClients($workspace, $clockifyWorkspaceId, $summary, $report);

            $report('Importazione progetti...');
            $projectMap = $this->importProjects($workspace, $clockifyWorkspaceId, $clientMap, $summary, $report);

            $report('Importazione task...');
            $taskMap = $this->importTasks($clockifyWorkspaceId, $projectMap, $clockifyUserId, $localUser, $summary, $report);

            $report('Importazione tag...');
            $tagMap = $this->importTags($workspace, $clockifyWorkspaceId);

            $report('Importazione time entry...');
            $this->importTimeEntries(
                $clockifyWorkspaceId,
                $clockifyUserId,
                $localUser,
                $projectMap,
                $taskMap,
                $tagMap,
                $from,
                $until,
                $summary,
                $report,
            );
        } finally {
            if ($dryRun) {
                DB::rollBack();
            } else {
                DB::commit();
            }
        }

        return $summary;
    }

    private function resolveLocalUser(string $email, ClockifyImportSummary $summary, Closure $report): User
    {
        $user = User::query()->where('email', $email)->first();

        if ($user) {
            return $user;
        }

        $report("Utente locale [{$email}] non trovato, lo creo...");

        $user = User::create([
            'name' => Str::before($email, '@'),
            'email' => $email,
            'password' => Hash::make(Str::random(32)),
        ]);

        $summary->userCreated = true;

        return $user;
    }

    private function ensureWorkspaceMembership(Workspace $workspace, User $user): void
    {
        if ($user->roleIn($workspace) !== null) {
            return;
        }

        $workspace->users()->attach($user, ['role' => WorkspaceRole::Admin->value]);
    }

    /**
     * @return array<string, Client> Clockify client id => local Client
     */
    private function importClients(Workspace $workspace, string $clockifyWorkspaceId, ClockifyImportSummary $summary, Closure $report): array
    {
        $map = [];

        foreach ($this->client->clients($clockifyWorkspaceId) as $clockifyClient) {
            $client = Client::query()->firstOrCreate(
                ['workspace_id' => $workspace->id, 'name' => $clockifyClient['name']],
                ['description' => $clockifyClient['note'] ?? null, 'is_active' => true],
            );

            if ($client->wasRecentlyCreated) {
                $summary->clientsImported++;
            }

            $map[(string) $clockifyClient['id']] = $client;
        }

        $report($summary->clientsImported.' clienti importati.');

        return $map;
    }

    /**
     * @param  array<string, Client>  $clientMap
     * @return array<string, Project> Clockify project id => local Project
     */
    private function importProjects(Workspace $workspace, string $clockifyWorkspaceId, array $clientMap, ClockifyImportSummary $summary, Closure $report): array
    {
        $map = [];
        $fallbackClient = null;

        foreach ($this->client->projects($clockifyWorkspaceId) as $clockifyProject) {
            $clockifyClientId = $clockifyProject['clientId'] ?? null;
            $client = filled($clockifyClientId) ? ($clientMap[(string) $clockifyClientId] ?? null) : null;

            if (! $client) {
                $fallbackClient ??= Client::query()->firstOrCreate(
                    ['workspace_id' => $workspace->id, 'name' => self::DEFAULT_CLIENT_NAME],
                    ['is_active' => true],
                );
                $client = $fallbackClient;
            }

            $hourlyRate = $this->rateToDecimalString($clockifyProject['hourlyRate'] ?? null);

            $project = Project::query()->firstOrCreate(
                [
                    'workspace_id' => $workspace->id, 'name' => $clockifyProject['name'],
                ],
                [
                    'client_id' => $client->id,
                    'color' => $clockifyProject['color'] ?? null,
                    'note' => $clockifyProject['note'] ?? null,
                    'status' => ($clockifyProject['archived'] ?? false) ? ProjectStatus::Archived : ProjectStatus::Active,
                    'visibility' => ($clockifyProject['isPublic'] ?? true) ? ProjectVisibility::Public : ProjectVisibility::Private,
                    'hourly_rate' => $hourlyRate,
                ],
            );

            if ($project->wasRecentlyCreated) {
                $summary->projectsImported++;
            }

            $map[(string) $clockifyProject['id']] = $project;
        }

        $report($summary->projectsImported.' progetti importati.');

        return $map;
    }

    /**
     * @param  array<string, Project>  $projectMap
     * @return array<string, Task> Clockify task id => local Task
     */
    private function importTasks(
        string $clockifyWorkspaceId,
        array $projectMap,
        string $clockifyUserId,
        User $localUser,
        ClockifyImportSummary $summary,
        Closure $report,
    ): array {
        $map = [];
        $workPackagesByProject = [];

        foreach ($projectMap as $clockifyProjectId => $project) {
            foreach ($this->fetchTasksWithCache($clockifyWorkspaceId, $clockifyProjectId) as $clockifyTask) {
                $workPackagesByProject[$project->id] ??= WorkPackage::query()->firstOrCreate(
                    ['project_id' => $project->id, 'name' => self::DEFAULT_WORK_PACKAGE_NAME],
                    ['status' => WorkPackageStatus::InProgress, 'sort_order' => 0],
                );

                $isAssignedToImportedUser = in_array($clockifyUserId, $clockifyTask['assigneeIds'] ?? [], strict: true);

                // Matched by title within the Work Package rather than by
                // Clockify's own id: titles are what's meaningful/stable to a
                // user reconciling tasks across systems, and this also lets
                // the import land on a task created locally (or by an
                // earlier partial/manual import) with the same name.
                $task = Task::query()->firstOrCreate(
                    [
                        'work_package_id' => $workPackagesByProject[$project->id]->id,
                        'name' => $clockifyTask['name'],
                    ],
                    [
                        'status' => ($clockifyTask['status'] ?? null) === 'DONE' ? TaskStatus::Done : TaskStatus::Todo,
                        'assignee_id' => $isAssignedToImportedUser ? $localUser->id : null,
                        // Third-party (ClickUp/Jira) ticket id, conventionally embedded
                        // in the Clockify task description between square brackets.
                        'external_id' => $this->extractClickUpIdFromDescription($clockifyTask['description'] ?? null),
                        // Clockify's own task id, kept only for traceability back to the import source.
                        'import_old_id' => (string) $clockifyTask['id'],
                    ],
                );

                if ($task->wasRecentlyCreated) {
                    $summary->tasksImported++;
                }

                $map[(string) $clockifyTask['id']] = $task;
            }
        }

        $report($summary->tasksImported.' task importati.');

        return $map;
    }

    /**
     * Clockify's task list is the slowest, most rate-limit-sensitive part of
     * this import (one request per project) and is very unlikely to change
     * between two runs of the same import — so once fetched for a project,
     * the raw payload is cached to disk and reused on every later run
     * (including repeated dry-runs used just to preview an import) instead
     * of hitting the API again. Delete the file to force a refresh.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function fetchTasksWithCache(string $clockifyWorkspaceId, string $clockifyProjectId): Collection
    {
        $path = "clockify-import/tasks/{$clockifyWorkspaceId}/{$clockifyProjectId}.json";

        if (Storage::exists($path)) {
            return collect(json_decode(Storage::get($path) ?? '[]', true));
        }

        $tasks = $this->client->tasks($clockifyWorkspaceId, $clockifyProjectId);

        Storage::put($path, $tasks->toJson());

        return $tasks;
    }

    /**
     * Clockify has no dedicated field for a linked ClickUp/Jira ticket id,
     * so it's conventionally embedded in the task description between
     * square brackets, e.g. "Fix login bug [PROJ-123]".
     */
    private function extractClickUpIdFromDescription(?string $description): ?string
    {
        if (blank($description) || ! preg_match('/\[([^\[\]]+)\]/', $description, $matches)) {
            return null;
        }

        return $matches[1];
    }

    /**
     * @return array<string, Tag> Clockify tag id => local Tag
     */
    private function importTags(Workspace $workspace, string $clockifyWorkspaceId): array
    {
        $map = [];

        foreach ($this->client->tags($clockifyWorkspaceId) as $clockifyTag) {
            $tag = Tag::query()->firstOrCreate(
                ['workspace_id' => $workspace->id, 'name' => $clockifyTag['name']],
            );

            $map[(string) $clockifyTag['id']] = $tag;
        }

        return $map;
    }

    /**
     * @param  array<string, Project>  $projectMap
     * @param  array<string, Task>  $taskMap
     * @param  array<string, Tag>  $tagMap
     */
    private function importTimeEntries(
        string $clockifyWorkspaceId,
        string $clockifyUserId,
        User $localUser,
        array $projectMap,
        array $taskMap,
        array $tagMap,
        ?Carbon $from,
        ?Carbon $until,
        ClockifyImportSummary $summary,
        Closure $report,
    ): void {
        $entries = $this->client->timeEntries(
            $clockifyWorkspaceId,
            $clockifyUserId,
            $from?->toIso8601String(),
            $until?->toIso8601String(),
        );

        $report($entries->count().' time entry trovate su Clockify, elaborazione in corso...');

        foreach ($entries as $entry) {
            $clockifyEntryId = (string) $entry['id'];

            if (TimeEntry::query()->where('import_old_id', $clockifyEntryId)->exists()) {
                $summary->timeEntriesSkipped++;

                continue;
            }

            $start = $entry['timeInterval']['start'] ?? null;
            $end = $entry['timeInterval']['end'] ?? null;

            if (blank($start) || blank($end)) {
                $summary->timeEntriesSkipped++;
                $summary->warn("Time entry [{$clockifyEntryId}] saltata: è ancora in corso su Clockify (nessun orario di fine).");

                continue;
            }

            $clockifyProjectId = $entry['projectId'] ?? null;
            $project = filled($clockifyProjectId) ? ($projectMap[(string) $clockifyProjectId] ?? null) : null;

            if (! $project) {
                $summary->timeEntriesSkipped++;
                $summary->warn("Time entry [{$clockifyEntryId}] saltata: nessun progetto associato o non importato.");

                continue;
            }

            $task = filled($entry['taskId'] ?? null) ? ($taskMap[(string) $entry['taskId']] ?? null) : null;

            $clockifyTagIds = (array) ($entry['tagIds'] ?? []);

            $tagIds = collect($clockifyTagIds)
                ->map(fn (mixed $tagId): mixed => $tagMap[(string) $tagId]->id ?? null)
                ->filter()
                ->values()
                ->all();

            try {
                $timeEntry = $this->createTimeEntryAction->handle($localUser, [
                    'project_id' => $project->id,
                    'task_id' => $task?->id,
                    'description' => $entry['description'] ?? null,
                    'date' => Carbon::parse($start)->toDateString(),
                    'started_at' => $start,
                    'ended_at' => $end,
                    'tags' => $tagIds,
                ]);

                $timeEntry->update(['import_old_id' => $clockifyEntryId]);

                $summary->timeEntriesImported++;
            } catch (Throwable $exception) {
                $summary->timeEntriesSkipped++;
                $summary->warn("Time entry [{$clockifyEntryId}] saltata: {$exception->getMessage()}");
            }
        }
    }

    /**
     * Clockify represents monetary amounts as the smallest currency unit
     * (e.g. cents). Assumes a single currency, consistent with this app
     * not supporting multi-currency (see roadmap decision).
     *
     * @param  array<string, mixed>|null  $rate
     */
    private function rateToDecimalString(?array $rate): ?string
    {
        if (! $rate || ! isset($rate['amount'])) {
            return null;
        }

        return number_format(((int) $rate['amount']) / 100, 2, '.', '');
    }
}
