<?php

namespace App\Services\Billing;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\TimeEntry;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Aggregations behind the Billing Dashboard. Distinguishes three figures on
 * purpose, since conflating them is exactly what a financial-control view
 * needs to avoid: value already logged via time entries ("prodotto", from
 * TimeEntry.total_amount — work done, whether or not it's been invoiced
 * yet), value invoiced (Invoice.amount for Sent + Collected — Draft isn't
 * "billed" yet, it's still editable), and value actually collected
 * (Invoice.amount for Collected only). The gap between produced and
 * invoiced is the backlog still to be billed.
 */
class InvoiceReportService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Invoice>
     */
    public function query(Workspace $workspace, array $filters = []): Builder
    {
        return Invoice::query()
            ->where('invoices.workspace_id', $workspace->id)
            ->when($filters['year'] ?? null, fn (Builder $query, $year) => $query->where('invoices.year', $year))
            ->when($filters['client_id'] ?? null, fn (Builder $query, $clientId) => $query->where('invoices.client_id', $clientId));
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function totalByStatus(Workspace $workspace, array $filters, InvoiceStatus $status): string
    {
        return number_format((float) $this->query($workspace, $filters)->where('status', $status)->sum('amount'), 2, '.', '');
    }

    /**
     * Issued value: Sent + Collected. Draft is excluded — it isn't billed
     * yet, and Cancelled never was.
     *
     * @param  array<string, mixed>  $filters
     */
    public function totalInvoiced(Workspace $workspace, array $filters = []): string
    {
        return number_format(
            (float) $this->query($workspace, $filters)
                ->whereIn('status', [InvoiceStatus::Sent, InvoiceStatus::Collected])
                ->sum('amount'),
            2,
            '.',
            '',
        );
    }

    /**
     * Value logged via time entries in the filtered period/client, regardless
     * of whether it's been invoiced — the "produced" side of the gap.
     *
     * @param  array<string, mixed>  $filters
     */
    public function totalProduced(Workspace $workspace, array $filters = []): string
    {
        $query = TimeEntry::query()
            ->join('projects', 'projects.id', '=', 'time_entries.project_id')
            ->where('projects.workspace_id', $workspace->id)
            ->when($filters['year'] ?? null, fn (Builder $query, $year) => $query->whereYear('time_entries.date', $year))
            ->when($filters['client_id'] ?? null, fn (Builder $query, $clientId) => $query->where('projects.client_id', $clientId));

        return number_format((float) $query->sum('time_entries.total_amount'), 2, '.', '');
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function collectionRate(Workspace $workspace, array $filters = []): ?float
    {
        $sentAndCollected = (float) $this->query($workspace, $filters)
            ->whereIn('status', [InvoiceStatus::Sent, InvoiceStatus::Collected])
            ->sum('amount');

        if ($sentAndCollected <= 0.0) {
            return null;
        }

        $collected = (float) $this->query($workspace, $filters)->where('status', InvoiceStatus::Collected)->sum('amount');

        return round(($collected / $sentAndCollected) * 100, 1);
    }

    /**
     * Monthly invoiced totals for the filtered year, split by status, so the
     * chart can show the Draft/Sent/Collected mix per month rather than a
     * single opaque total. Bucketed by the invoice's own sent_at (falling
     * back to created_at for Draft invoices that were never sent) — an
     * Invoice has no other notion of "which month it belongs to".
     *
     * @param  array<string, mixed>  $filters
     * @return array{labels: array<int, string>, series: array<string, array<int, float>>}
     */
    public function monthlyInvoicedTotals(Workspace $workspace, array $filters = []): array
    {
        $statuses = [InvoiceStatus::Draft, InvoiceStatus::Sent, InvoiceStatus::Collected];

        $series = [];

        foreach ($statuses as $status) {
            $series[$status->value] = array_fill(1, 12, 0.0);
        }

        $invoices = $this->query($workspace, $filters)
            ->whereIn('status', $statuses)
            ->get(['status', 'amount', 'sent_at', 'created_at']);

        foreach ($invoices as $invoice) {
            $month = ($invoice->sent_at ?? $invoice->created_at)->month;
            $series[$invoice->status->value][$month] += (float) $invoice->amount;
        }

        return [
            'labels' => collect(range(1, 12))->map(fn (int $month): string => Carbon::create()->month($month)->translatedFormat('M'))->all(),
            'series' => $series,
        ];
    }

    /**
     * Per-client breakdown of produced vs. invoiced vs. collected, so the
     * "who's behind on invoicing" question can be answered at a glance.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, array{client_id: int, client_name: string, produced: string, invoiced: string, collected: string, gap: string}>
     */
    public function totalsByClient(Workspace $workspace, array $filters = []): Collection
    {
        $invoiceRows = $this->query($workspace, $filters)
            ->whereIn('status', [InvoiceStatus::Sent, InvoiceStatus::Collected])
            ->join('clients', 'clients.id', '=', 'invoices.client_id')
            ->selectRaw('clients.id as client_id, clients.name as client_name, invoices.status as status, SUM(invoices.amount) as total')
            ->groupBy('clients.id', 'clients.name', 'invoices.status')
            ->get()
            ->groupBy('client_id');

        $producedRows = TimeEntry::query()
            ->join('projects', 'projects.id', '=', 'time_entries.project_id')
            ->join('clients', 'clients.id', '=', 'projects.client_id')
            ->where('projects.workspace_id', $workspace->id)
            ->when($filters['year'] ?? null, fn (Builder $query, $year) => $query->whereYear('time_entries.date', $year))
            ->when($filters['client_id'] ?? null, fn (Builder $query, $clientId) => $query->where('projects.client_id', $clientId))
            ->selectRaw('clients.id as client_id, clients.name as client_name, SUM(time_entries.total_amount) as total')
            ->groupBy('clients.id', 'clients.name')
            ->get()
            ->keyBy('client_id');

        $clientIds = $invoiceRows->keys()->merge($producedRows->keys())->unique();

        return $clientIds
            ->map(function (int $clientId) use ($invoiceRows, $producedRows): array {
                $statusRows = $invoiceRows->get($clientId, collect());
                $producedRow = $producedRows->get($clientId);

                $clientName = $statusRows->first()->client_name ?? $producedRow->client_name ?? '—';
                $invoiced = (float) $statusRows->sum('total');
                $collected = (float) $statusRows->firstWhere('status', InvoiceStatus::Collected)?->total;
                $produced = (float) ($producedRow->total ?? 0);

                return [
                    'client_id' => $clientId,
                    'client_name' => $clientName,
                    'produced' => number_format($produced, 2, '.', ''),
                    'invoiced' => number_format($invoiced, 2, '.', ''),
                    'collected' => number_format($collected, 2, '.', ''),
                    'gap' => number_format($produced - $invoiced, 2, '.', ''),
                ];
            })
            ->sortByDesc(fn (array $row): float => (float) $row['produced'])
            ->values();
    }

    /**
     * Invoices sent but not yet collected, oldest first — the "waiting to be
     * paid" list a financial-control view needs front and center.
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<Invoice>
     */
    public function outstandingInvoicesQuery(Workspace $workspace, array $filters = []): Builder
    {
        return $this->query($workspace, $filters)
            ->where('status', InvoiceStatus::Sent)
            ->orderBy('sent_at');
    }
}
