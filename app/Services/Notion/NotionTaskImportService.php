<?php

namespace App\Services\Notion;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\WorkPackageStatus;
use App\Models\Project;
use App\Models\Task;
use App\Models\WorkPackage;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * One-way pull of the personal Notion "Task" database into local Tasks.
 * Never writes back to Notion.
 *
 * Notion's domain doesn't map 1:1 onto ours:
 * - A Notion page points at a Project, but a local Task must belong to a Work
 *   Package; imported Tasks are filed under a single "Import Notion" Work
 *   Package created per Project, mirroring what the Clockify import does.
 * - Only the Notion Projects with a known internal_id can be resolved; pages
 *   belonging to the others (or to no Project at all) are skipped and counted.
 * - Notion has 11 statuses against our 6, and no priority property at all —
 *   see STATUS_MAP for the lossy mapping.
 * - Notion properties with no local column (Tipo, Tags, Data, HubSpot,
 *   Cartella PC, Parent item) are rendered into a delimited block at the top
 *   of the description, so nothing is lost and re-imports stay idempotent.
 *
 * Re-running is safe: pages are matched on the Notion page uuid stored in
 * Task.notion_id, so already-imported Tasks are updated rather than
 * duplicated, and locally soft-deleted ones are left alone.
 */
class NotionTaskImportService
{
    private const string DATABASE_ID = '2701cdc26f9a81098753f01809b5b838';

    /** Work Package created per Project to hold everything coming from Notion. */
    public const string DEFAULT_WORK_PACKAGE_NAME = 'Import Notion';

    private const string NOTE_BLOCK_OPEN = '--- Notion ---';

    private const string NOTE_BLOCK_CLOSE = '--- /Notion ---';

    /**
     * The page body gets its own delimiters rather than sharing the note
     * block: it is only refreshed when the import is asked to read page
     * bodies, so it has to survive a metadata-only run untouched.
     */
    private const string BODY_BLOCK_OPEN = '--- Notion: contenuto ---';

    private const string BODY_BLOCK_CLOSE = '--- /Notion: contenuto ---';

    private const string PROP_TITLE = 'Titolo';

    private const string PROP_STATUS = 'Stato';

    private const string PROP_DEADLINE = 'Scadenza';

    private const string PROP_DATE = 'Data';

    private const string PROP_TYPE = 'Tipo';

    private const string PROP_TAGS = 'Tags';

    private const string PROP_PROJECT = 'Progetto';

    private const string PROP_DEVOPS = 'DevOps';

    private const string PROP_HUBSPOT = 'HubSpot';

    private const string PROP_FOLDER = 'Cartella PC';

    private const string PROP_PARENT = 'Parent item';

    /**
     * Notion Project page uuid => local Project. A null project_id means the
     * Notion Project has no local counterpart yet: its pages are skipped.
     *
     * @var array<string, array{name: string, project_id: int|null}>
     */
    private const array PROJECT_MAP = [
        '3211cdc2-6f9a-80c2-bcdc-d1346daca3c5' => ['name' => 'Hoteldoor', 'project_id' => 20],
        '3021cdc2-6f9a-8081-90e4-d9775735dc16' => ['name' => 'Extra', 'project_id' => null],
        '2ef1cdc2-6f9a-8026-ac08-cd0ef2c472a3' => ['name' => 'Casa', 'project_id' => null],
        '2e91cdc2-6f9a-80ce-abd2-d536709a9ca8' => ['name' => 'Supporto IT', 'project_id' => null],
        '2a01cdc2-6f9a-80c7-9b2c-dda6e7af1df2' => ['name' => 'IntegraCRM', 'project_id' => 21],
        '2811cdc2-6f9a-8042-bdc1-f339e1d4a9e1' => ['name' => 'ItineraCloud', 'project_id' => null],
        '2701cdc2-6f9a-818c-9528-c790a74c5628' => ['name' => 'Bancalavoro', 'project_id' => null],
        '2701cdc2-6f9a-81b3-9764-c40e69c18520' => ['name' => 'Arti Motorie', 'project_id' => 1],
        '2701cdc2-6f9a-8129-9d27-f38b10feaba2' => ['name' => 'MagicLeader', 'project_id' => null],
        '2701cdc2-6f9a-8156-a48f-fbc661646805' => ['name' => 'Rilevamiele', 'project_id' => 41],
        '2701cdc2-6f9a-8180-888c-c7b87e1665dd' => ['name' => 'Sito vetrina', 'project_id' => null],
        '2701cdc2-6f9a-8191-b8de-fa284c49b442' => ['name' => 'Camilla', 'project_id' => null],
        '2701cdc2-6f9a-81a2-a894-f093bf1fa4b0' => ['name' => 'Sito portfolio', 'project_id' => null],
        '2701cdc2-6f9a-81c6-9f1c-f50974ca2f08' => ['name' => 'Gestionale fisio', 'project_id' => null],
        '2701cdc2-6f9a-8126-bacf-fa8903b89c74' => ['name' => 'Catalogo PDF', 'project_id' => null],
        '2701cdc2-6f9a-8131-a5ea-ceafc38a3598' => ['name' => 'Documenti', 'project_id' => null],
        '2701cdc2-6f9a-8141-b59e-faa7847c1409' => ['name' => 'Difesa Consumatori', 'project_id' => 14],
        '2701cdc2-6f9a-817f-85e2-d2f0aadab7e6' => ['name' => 'Dropbox', 'project_id' => null],
        '2701cdc2-6f9a-81f4-9b17-f233c68e2361' => ['name' => 'ITS Alto Adriatico', 'project_id' => 24],
        '2701cdc2-6f9a-8149-a53b-ca76b49486d6' => ['name' => 'Dresscode', 'project_id' => 17],
        '2701cdc2-6f9a-8190-9977-c35d414e27f6' => ['name' => "Tre gocce d'oro", 'project_id' => 30],
        '2701cdc2-6f9a-81ee-bf1c-fc1c3fe9fca9' => ['name' => 'Gestionale', 'project_id' => null],
        '2701cdc2-6f9a-8145-a798-fef90ff49ec9' => ['name' => 'Scraper annunci immobiliari', 'project_id' => null],
    ];

    /**
     * Notion's 11 statuses folded onto our 6. Lossy by necessity:
     * - IN PAUSA and FOLLOW-UP have no local equivalent and stay "in corso".
     * - DA RILASCIARE sits between test and done; kept on the test side, since
     *   the work isn't delivered yet.
     * - RILASCIATO is treated as delivered even though Notion files it under
     *   its "In progress" group.
     *
     * @var array<string, TaskStatus>
     */
    private const array STATUS_MAP = [
        'NICE-TO-HAVE' => TaskStatus::Backlog,
        'BACKLOG' => TaskStatus::Backlog,
        'TODO' => TaskStatus::Todo,
        'IN CORSO' => TaskStatus::InProgress,
        'IN PAUSA' => TaskStatus::InProgress,
        'TEST' => TaskStatus::Test,
        'DA RILASCIARE' => TaskStatus::Test,
        'RILASCIATO' => TaskStatus::Done,
        'FOLLOW-UP' => TaskStatus::InProgress,
        'COMPLETATO' => TaskStatus::Done,
        'ANNULLATO' => TaskStatus::Cancelled,
    ];

    /** The only Notion status that carries priority information. */
    private const string NICE_TO_HAVE_STATUS = 'NICE-TO-HAVE';

    /** Fetching page bodies makes the loop slow; report progress this often. */
    private const int PROGRESS_EVERY = 25;

    public function __construct(
        private readonly NotionClient $client,
        private readonly NotionPageBodyRenderer $bodyRenderer,
    ) {}

    /**
     * @param  array<int, int>  $onlyProjectIds  Restrict the import to these local Project ids.
     * @param  bool  $withBody  Also read the text inside each Notion page. Off by
     *                          default: it costs at least one extra API call per
     *                          task, so it turns a quick metadata refresh into a
     *                          long run. A body already imported by a previous
     *                          run is kept as is while this is off.
     */
    public function import(
        bool $dryRun,
        array $onlyProjectIds = [],
        bool $withBody = false,
        ?Closure $onProgress = null,
    ): NotionTaskImportSummary {
        $summary = new NotionTaskImportSummary;
        $report = fn (string $message) => $onProgress ? $onProgress($message) : null;

        $report('Recupero pagine dal database Notion...');
        $pages = $this->client->queryDatabase(self::DATABASE_ID);
        $summary->pagesFetched = $pages->count();
        $report("{$summary->pagesFetched} pagine ricevute.");

        // Sub-items are flattened (local Tasks have no hierarchy), so the
        // parent's title is only kept as a note — hence this id => title index.
        $titles = $this->indexTitles($pages);
        $projectMap = $this->projectMap();

        /** @var array<int, WorkPackage|null> $workPackages Local Project id => its "Import Notion" Work Package. */
        $workPackages = [];

        $report($withBody
            ? 'Lettura del corpo di ogni pagina attiva: una o più chiamate API per task, può richiedere qualche minuto.'
            : 'Corpo delle pagine non letto: vengono importate solo le proprietà.');

        DB::beginTransaction();

        try {
            foreach ($pages as $index => $page) {
                $this->importPage($page, $titles, $projectMap, $onlyProjectIds, $withBody, $workPackages, $summary, $report);

                if (($index + 1) % self::PROGRESS_EVERY === 0) {
                    $report(sprintf('%d/%d pagine elaborate...', $index + 1, $summary->pagesFetched));
                }
            }
        } catch (Throwable $exception) {
            DB::rollBack();

            throw $exception;
        }

        if ($dryRun) {
            DB::rollBack();
        } else {
            DB::commit();
        }

        return $summary;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $pages
     * @return array<string, string>
     */
    private function indexTitles(Collection $pages): array
    {
        return $pages
            ->mapWithKeys(fn (array $page): array => [
                $this->normalizeId((string) ($page['id'] ?? '')) => $this->title($page),
            ])
            ->all();
    }

    /**
     * PROJECT_MAP is written with dashed uuids for readability; this is the
     * same map re-keyed the way incoming relation ids are normalized.
     *
     * @return array<string, array{name: string, project_id: int|null}>
     */
    private function projectMap(): array
    {
        return collect(self::PROJECT_MAP)
            ->mapWithKeys(fn (array $project, string $notionId): array => [$this->normalizeId($notionId) => $project])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $page
     * @param  array<string, string>  $titles
     * @param  array<string, array{name: string, project_id: int|null}>  $projectMap
     * @param  array<int, int>  $onlyProjectIds
     * @param  array<int, WorkPackage|null>  $workPackages
     */
    private function importPage(
        array $page,
        array $titles,
        array $projectMap,
        array $onlyProjectIds,
        bool $withBody,
        array &$workPackages,
        NotionTaskImportSummary $summary,
        Closure $report,
    ): void {
        $pageId = $this->normalizeId((string) ($page['id'] ?? ''));

        // Trashed pages are already excluded by the API; archived ones are not.
        if (($page['is_archived'] ?? $page['archived'] ?? false) === true) {
            $summary->pagesArchived++;

            return;
        }

        $title = $this->title($page);

        if (blank($title)) {
            $summary->tasksSkippedWithoutTitle++;
            $summary->warn("Pagina [{$pageId}]: titolo vuoto, saltata.");

            return;
        }

        $notionProjectId = $this->firstRelationId($page, self::PROP_PROJECT);
        $mapping = $notionProjectId !== null ? ($projectMap[$notionProjectId] ?? null) : null;
        $projectId = $mapping['project_id'] ?? null;

        if ($projectId === null) {
            $summary->tasksSkippedUnmappedProject++;
            $summary->warn(sprintf(
                '"%s": progetto %s, saltato.',
                Str::limit($title, 60),
                $mapping !== null
                    ? "Notion [{$mapping['name']}] senza id locale"
                    : 'Notion assente o sconosciuto',
            ));

            return;
        }

        if ($onlyProjectIds !== [] && ! in_array($projectId, $onlyProjectIds, strict: true)) {
            return;
        }

        $existing = Task::withTrashed()->where('notion_id', $pageId)->first();

        if ($existing?->trashed() === true) {
            $summary->tasksSkippedTrashed++;
            $summary->warn(sprintf('"%s": task locale #%d cancellato, non reimportato.', Str::limit($title, 60), $existing->id));

            return;
        }

        // Second chance before creating anything: the same job may already be
        // in here under a Task imported from Clockify and renamed by the
        // ClickUp sync. Adopting it is better than creating a duplicate.
        $adopted = false;

        if ($existing === null) {
            $existing = $this->findByDevOpsLink($page, $projectId, $title, $summary);
            $adopted = $existing !== null;
        }

        $statusName = $this->statusName($page);
        $attributes = [
            'name' => $title,
            'description' => $this->buildDescription(
                $page,
                $titles,
                $withBody ? $this->body($pageId, $title, $summary) : null,
                $existing?->description,
            ),
            'status' => $this->mapStatus($statusName, $title, $summary),
            'priority' => $statusName === self::NICE_TO_HAVE_STATUS ? TaskPriority::NiceToHave : TaskPriority::Media,
            'expire' => $this->date($page, self::PROP_DEADLINE),
            // The DevOps link is the one worth clicking; the Notion page is the
            // fallback so the Task always points somewhere.
            'url' => $this->url($page, self::PROP_DEVOPS) ?? ($page['url'] ?? null),
            'notion_id' => $pageId,
        ];

        if ($existing !== null) {
            // An adopted Task keeps the name it already had — typically
            // "[CO-2345] ..." written by the ClickUp sync, which is also what
            // made it findable in the first place.
            if ($adopted) {
                unset($attributes['name']);
            }

            $existing->update($attributes);
            $summary->tasksUpdated++;

            return;
        }

        // Only a Task that really has to be created needs a Work Package, so
        // resolving it here keeps empty "Import Notion" ones from appearing.
        // array_key_exists, not ??=: a Project that turned out to be missing
        // caches as null and must not be looked up again for every page.
        if (! array_key_exists($projectId, $workPackages)) {
            $workPackages[$projectId] = $this->resolveWorkPackage($projectId, $summary, $report);
        }

        $workPackage = $workPackages[$projectId];

        if ($workPackage === null) {
            $summary->tasksSkippedUnmappedProject++;

            return;
        }

        Task::create([...$attributes, 'work_package_id' => $workPackage->id]);
        $summary->tasksCreated++;
    }

    /**
     * The local Task that already tracks the same work as this Notion page,
     * matched through the page's DevOps link against the Tasks of the same
     * Project — either because the Task points at that very url, or because
     * its name is prefixed with the code the url ends in ("[CO-2345] ...").
     *
     * Only Tasks with no notion_id are eligible: one already claimed by
     * another page must not be stolen, which would also break the unique index.
     *
     * @param  array<string, mixed>  $page
     */
    private function findByDevOpsLink(array $page, int $projectId, string $title, NotionTaskImportSummary $summary): ?Task
    {
        $devOpsUrl = $this->url($page, self::PROP_DEVOPS);

        if ($devOpsUrl === null) {
            return null;
        }

        $urls = $this->urlVariants($devOpsUrl);
        $code = $this->trailingSegment($devOpsUrl);

        $candidates = Task::query()
            ->whereNull('notion_id')
            ->whereHas('workPackage', fn (Builder $query) => $query->where('project_id', $projectId))
            ->where(function (Builder $query) use ($urls, $code): void {
                $query->whereRaw(
                    'LOWER(url) IN ('.implode(', ', array_fill(0, count($urls), '?')).')',
                    $urls,
                );

                if ($code !== null) {
                    // Coarse prefilter: the exact comparison happens in PHP,
                    // so LIKE wildcards inside the code can't widen the match.
                    $query->orWhere('name', 'like', "[{$code}]%");
                }
            })
            ->orderBy('id')
            ->get()
            ->filter(fn (Task $task): bool => in_array(Str::lower((string) $task->url), $urls, strict: true)
                || ($code !== null && $this->namePrefixCode($task->name) === Str::lower($code)));

        $match = $candidates->first();

        if ($match === null) {
            return null;
        }

        if ($candidates->count() > 1) {
            $summary->warn(sprintf(
                '"%s": %d task locali corrispondono a %s, agganciato il più vecchio (#%d), gli altri: %s.',
                Str::limit($title, 60),
                $candidates->count(),
                $devOpsUrl,
                $match->id,
                $candidates->skip(1)->pluck('id')->map(fn (int $id): string => "#{$id}")->implode(', '),
            ));
        }

        $summary->tasksAdopted++;

        return $match;
    }

    /**
     * The bracketed code a Task name starts with — "[CO-2345] Fix" gives
     * "co-2345" — or null when the name isn't in that shape.
     */
    private function namePrefixCode(string $name): ?string
    {
        return preg_match('/^\[([^\]]+)\](\s|$)/', $name, $matches) === 1
            ? Str::lower(trim($matches[1]))
            : null;
    }

    /**
     * The same url written the handful of ways it may have been stored:
     * lowercased, with and without a trailing slash, over either scheme.
     *
     * @return array<int, string>
     */
    private function urlVariants(string $url): array
    {
        $normalized = Str::lower(rtrim(trim($url), '/'));
        $bare = (string) preg_replace('#^https?://#', '', $normalized);

        return array_values(array_unique([
            $normalized,
            $normalized.'/',
            "http://{$bare}",
            "http://{$bare}/",
            "https://{$bare}",
            "https://{$bare}/",
        ]));
    }

    /**
     * The last path segment of a url — the task code in
     * "https://app.clickup.com/123323/XXX123". Null when the url carries no
     * path at all, so a bare host never gets mistaken for a code.
     */
    private function trailingSegment(string $url): ?string
    {
        $path = parse_url(str_replace('\\', '/', trim($url)), PHP_URL_PATH);

        if (! is_string($path)) {
            return null;
        }

        $segment = trim(Str::afterLast(rtrim($path, '/'), '/'));

        return $segment === '' ? null : $segment;
    }

    /**
     * The "Import Notion" Work Package of a Project, created on first use.
     * Null when PROJECT_MAP points at a Project that no longer exists locally
     * — better to skip its pages than to blow up the whole transaction on a
     * foreign key violation.
     */
    private function resolveWorkPackage(int $projectId, NotionTaskImportSummary $summary, Closure $report): ?WorkPackage
    {
        if (! Project::whereKey($projectId)->exists()) {
            $summary->warn("Progetto locale #{$projectId} non trovato: i suoi task Notion vengono saltati.");

            return null;
        }

        $workPackage = WorkPackage::query()
            ->where('project_id', $projectId)
            ->where('name', self::DEFAULT_WORK_PACKAGE_NAME)
            ->first();

        if ($workPackage !== null) {
            return $workPackage;
        }

        $workPackage = WorkPackage::create([
            'project_id' => $projectId,
            'name' => self::DEFAULT_WORK_PACKAGE_NAME,
            'status' => WorkPackageStatus::InProgress,
            'sort_order' => 0,
        ]);

        $summary->workPackagesCreated++;
        $report("Progetto #{$projectId}: creato Work Package [".self::DEFAULT_WORK_PACKAGE_NAME.'].');

        return $workPackage;
    }

    private function mapStatus(?string $statusName, string $title, NotionTaskImportSummary $summary): TaskStatus
    {
        if ($statusName === null) {
            return TaskStatus::Todo;
        }

        $status = self::STATUS_MAP[$statusName] ?? null;

        if ($status === null) {
            $summary->warn(sprintf('"%s": stato Notion [%s] sconosciuto, impostato "todo".', Str::limit($title, 60), $statusName));

            return TaskStatus::Todo;
        }

        return $status;
    }

    /**
     * The text of the Notion page itself. Null — not an empty string — when
     * the call failed, so the body block already in the description is carried
     * over rather than wiped by a run that couldn't read the page.
     */
    private function body(string $pageId, string $title, NotionTaskImportSummary $summary): ?string
    {
        try {
            return $this->bodyRenderer->render($pageId);
        } catch (NotionApiException $exception) {
            $summary->bodiesFailed++;
            $summary->warn(sprintf(
                '"%s": corpo della pagina non recuperato, quello già presente viene mantenuto — %s',
                Str::limit($title, 60),
                $exception->getMessage(),
            ));

            return null;
        }
    }

    /**
     * The description holds two delimited blocks — the Notion properties with
     * no local column, then the page body — followed by whatever free text the
     * user typed outside them, which always survives.
     *
     * The properties block is regenerated on every run. The body block is only
     * regenerated when $body is given: a null means either that page bodies
     * weren't requested or that reading one failed, and in both cases the block
     * a previous run stored is carried over untouched.
     *
     * @param  array<string, mixed>  $page
     * @param  array<string, string>  $titles
     */
    private function buildDescription(array $page, array $titles, ?string $body, ?string $currentDescription): ?string
    {
        $current = (string) $currentDescription;
        $body ??= $this->extractBlock($current, self::BODY_BLOCK_OPEN, self::BODY_BLOCK_CLOSE);

        $parentId = $this->firstRelationId($page, self::PROP_PARENT);
        $tags = $this->multiSelectNames($page, self::PROP_TAGS);

        $lines = array_filter([
            'Tipo' => $this->selectName($page, self::PROP_TYPE),
            'Tags' => $tags === [] ? null : implode(', ', $tags),
            'Data' => $this->date($page, self::PROP_DATE)?->toDateString(),
            'Parent' => $parentId !== null ? ($titles[$parentId] ?? $parentId) : null,
            'HubSpot' => $this->url($page, self::PROP_HUBSPOT),
            'Cartella PC' => $this->url($page, self::PROP_FOLDER),
            'Notion' => $page['url'] ?? null,
        ], fn (?string $value): bool => filled($value));

        $properties = collect($lines)
            ->map(fn (string $value, string $label): string => "{$label}: {$value}")
            ->implode("\n");

        // Whatever the user typed outside the two blocks survives the re-import.
        $preserved = trim($this->stripBlock(
            $this->stripBlock($current, self::NOTE_BLOCK_OPEN, self::NOTE_BLOCK_CLOSE),
            self::BODY_BLOCK_OPEN,
            self::BODY_BLOCK_CLOSE,
        ));

        $description = collect([
            $this->wrapBlock($properties, self::NOTE_BLOCK_OPEN, self::NOTE_BLOCK_CLOSE),
            $this->wrapBlock($body, self::BODY_BLOCK_OPEN, self::BODY_BLOCK_CLOSE),
            $preserved,
        ])->filter(filled(...))->implode("\n\n");

        return blank($description) ? null : $description;
    }

    private function wrapBlock(?string $content, string $open, string $close): ?string
    {
        return blank($content) ? null : "{$open}\n".trim((string) $content)."\n{$close}";
    }

    /**
     * The content of a delimited block, or null when the description has none.
     */
    private function extractBlock(string $description, string $open, string $close): ?string
    {
        $pattern = '/'.preg_quote($open, '/').'\R?(.*?)\R?'.preg_quote($close, '/').'/s';

        return preg_match($pattern, $description, $matches) === 1 ? $matches[1] : null;
    }

    private function stripBlock(string $description, string $open, string $close): string
    {
        $pattern = '/'.preg_quote($open, '/').'.*?'.preg_quote($close, '/').'/s';

        return (string) preg_replace($pattern, '', $description);
    }

    /**
     * The list-shaped payload of a property (rich text fragments, multi_select
     * options), narrowed down from the untyped decoded JSON.
     *
     * @param  array<string, mixed>  $page
     * @return array<int, array<string, mixed>>
     */
    private function listProperty(array $page, string $property, string $type): array
    {
        $value = $page['properties'][$property][$type] ?? null;

        return is_array($value)
            ? array_values(array_filter($value, is_array(...)))
            : [];
    }

    /**
     * @param  array<string, mixed>  $page
     */
    private function title(array $page): string
    {
        $text = '';

        foreach ($this->listProperty($page, self::PROP_TITLE, 'title') as $fragment) {
            $plainText = $fragment['plain_text'] ?? null;

            if (is_string($plainText)) {
                $text .= $plainText;
            }
        }

        return trim($text);
    }

    /**
     * @param  array<string, mixed>  $page
     */
    private function statusName(array $page): ?string
    {
        $name = $page['properties'][self::PROP_STATUS]['status']['name'] ?? null;

        return filled($name) ? Str::upper(trim((string) $name)) : null;
    }

    /**
     * @param  array<string, mixed>  $page
     */
    private function selectName(array $page, string $property): ?string
    {
        return $page['properties'][$property]['select']['name'] ?? null;
    }

    /**
     * @param  array<string, mixed>  $page
     * @return array<int, string>
     */
    private function multiSelectNames(array $page, string $property): array
    {
        $names = [];

        foreach ($this->listProperty($page, $property, 'multi_select') as $option) {
            $name = $option['name'] ?? null;

            if (is_string($name) && $name !== '') {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * @param  array<string, mixed>  $page
     */
    private function date(array $page, string $property): ?Carbon
    {
        $start = $page['properties'][$property]['date']['start'] ?? null;

        // Notion sends either a date ("2026-07-29") or a full ISO instant;
        // Carbon handles both, and a date-only value lands on midnight.
        return filled($start) ? Carbon::parse($start) : null;
    }

    /**
     * @param  array<string, mixed>  $page
     */
    private function url(array $page, string $property): ?string
    {
        $url = $page['properties'][$property]['url'] ?? null;

        return filled($url) ? (string) $url : null;
    }

    /**
     * @param  array<string, mixed>  $page
     */
    private function firstRelationId(array $page, string $property): ?string
    {
        $id = $page['properties'][$property]['relation'][0]['id'] ?? null;

        return filled($id) ? $this->normalizeId((string) $id) : null;
    }

    /**
     * Notion hands out uuids dashed in payloads but dashless in urls; both the
     * hardcoded maps and the incoming ids go through here so they always meet.
     */
    private function normalizeId(string $id): string
    {
        return Str::lower(str_replace('-', '', $id));
    }
}
