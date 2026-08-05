<?php

namespace App\Services\Notion;

use Illuminate\Support\Str;

/**
 * Flattens the block tree of a Notion page into the plain, lightly
 * markdown-flavoured text that goes into Task.description.
 *
 * Notion returns a page's body as a list of blocks, one API call per level:
 * a block that reports has_children needs its own /blocks/{id}/children
 * request, so a deep page costs several round trips. Nesting is rendered as
 * indentation and capped at MAX_DEPTH to keep that bounded.
 *
 * Formatting is intentionally lossy — annotations (bold, colours, links) are
 * dropped and only plain_text survives, because the destination is a plain
 * text column, not a rich text editor.
 */
class NotionPageBodyRenderer
{
    /**
     * How many levels of nested blocks to follow. Enough for a list inside a
     * toggle inside a column, which is as deep as these notes realistically go.
     */
    private const int MAX_DEPTH = 4;

    private const string INDENT = '  ';

    public function __construct(
        private readonly NotionClient $client,
    ) {}

    /**
     * @throws NotionApiException
     */
    public function render(string $pageId): string
    {
        return trim(implode("\n", $this->renderChildren($pageId, depth: 0)));
    }

    /**
     * @return array<int, string>
     */
    private function renderChildren(string $blockId, int $depth): array
    {
        if ($depth >= self::MAX_DEPTH) {
            return [];
        }

        $lines = [];
        $ordinal = 0;

        foreach ($this->client->blockChildren($blockId) as $block) {
            $type = $block['type'] ?? null;

            if (! is_string($type)) {
                continue;
            }

            // Numbered items restart whenever a different block interrupts the run.
            $ordinal = $type === 'numbered_list_item' ? $ordinal + 1 : 0;

            $rendered = $this->renderBlock($block, $type, $ordinal);

            if ($rendered !== null) {
                $lines[] = $rendered;
            }

            $childId = $block['id'] ?? null;

            if (($block['has_children'] ?? false) !== true || ! is_string($childId)) {
                continue;
            }

            foreach ($this->renderChildren($childId, $depth + 1) as $childLine) {
                // Layout-only wrappers (columns, synced blocks) render nothing
                // themselves, so their children shouldn't be pushed inwards.
                $lines[] = $rendered === null ? $childLine : self::INDENT.$childLine;
            }
        }

        return $lines;
    }

    /**
     * @param  array<string, mixed>  $block
     */
    private function renderBlock(array $block, string $type, int $ordinal): ?string
    {
        $payload = $this->payload($block, $type);
        $text = $this->plainText($payload['rich_text'] ?? null);

        return match ($type) {
            'paragraph' => $this->line($text),
            'heading_1' => $this->line($text, '# '),
            'heading_2' => $this->line($text, '## '),
            'heading_3' => $this->line($text, '### '),
            'bulleted_list_item', 'toggle' => $this->line($text, '- '),
            'numbered_list_item' => $this->line($text, "{$ordinal}. "),
            'to_do' => $this->line($text, ($payload['checked'] ?? false) === true ? '- [x] ' : '- [ ] '),
            'quote', 'callout' => $this->line($text, '> '),
            'code' => $this->renderCode($payload, $text),
            'divider' => '---',
            'equation' => $this->line($this->string($payload['expression'] ?? null)),
            'child_page', 'child_database' => $this->line($this->string($payload['title'] ?? null), '- '),
            'bookmark', 'embed', 'link_preview' => $this->line($this->string($payload['url'] ?? null), '- '),
            'image', 'video', 'file', 'pdf', 'audio' => $this->renderFile($payload, $type),
            'table_row' => $this->renderTableRow($payload),
            // Pure containers: they carry no text, only children.
            'table', 'column_list', 'column', 'synced_block', 'table_of_contents', 'breadcrumb' => null,
            // Unknown or future block types still yield their text if they have any.
            default => $this->line($text),
        };
    }

    private function line(string $text, string $prefix = ''): ?string
    {
        return $text === '' ? null : $prefix.$text;
    }

    /**
     * @param  array<mixed, mixed>  $payload
     */
    private function renderCode(array $payload, string $text): ?string
    {
        if ($text === '') {
            return null;
        }

        $language = $this->string($payload['language'] ?? null);

        return "```{$language}\n{$text}\n```";
    }

    /**
     * @param  array<mixed, mixed>  $payload
     */
    private function renderFile(array $payload, string $type): ?string
    {
        $external = $this->payload($payload, 'external');
        $hosted = $this->payload($payload, 'file');

        $url = $this->string($external['url'] ?? $hosted['url'] ?? null);
        $caption = $this->plainText($payload['caption'] ?? null);

        if ($url === '') {
            return null;
        }

        $label = $caption !== '' ? $caption : Str::ucfirst($type);

        return "- {$label}: {$url}";
    }

    /**
     * @param  array<mixed, mixed>  $payload
     */
    private function renderTableRow(array $payload): ?string
    {
        $cells = $payload['cells'] ?? null;

        if (! is_array($cells)) {
            return null;
        }

        $rendered = array_map(fn (mixed $cell): string => $this->plainText($cell), $cells);

        return $this->line(trim(implode(' | ', $rendered), ' |'));
    }

    /**
     * The body of a typed Notion node — block['paragraph'], payload['external'],
     * etc. — narrowed down from the untyped decoded JSON.
     *
     * @param  array<mixed, mixed>  $node
     * @return array<mixed, mixed>
     */
    private function payload(array $node, string $key): array
    {
        $payload = $node[$key] ?? null;

        return is_array($payload) ? $payload : [];
    }

    /**
     * Concatenates the plain_text of a rich text array, dropping annotations.
     */
    private function plainText(mixed $richText): string
    {
        if (! is_array($richText)) {
            return '';
        }

        $text = '';

        foreach ($richText as $fragment) {
            if (! is_array($fragment)) {
                continue;
            }

            $plainText = $fragment['plain_text'] ?? null;

            if (is_string($plainText)) {
                $text .= $plainText;
            }
        }

        return trim($text);
    }

    private function string(mixed $value): string
    {
        return is_string($value) ? trim($value) : '';
    }
}
