<?php

namespace App\Filament\Widgets;

use App\Actions\TimeEntry\CreateTimeEntryAction;
use App\Actions\TimeEntry\UpdateTimeEntryAction;
use App\Filament\Resources\TimeEntries\Pages\CreateTimeEntry;
use App\Models\TimeEntry;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Schemas\Schema;
use Guava\Calendar\Enums\CalendarViewType;
use Guava\Calendar\Filament\Actions\CreateAction;
use Guava\Calendar\Filament\Actions\EditAction;
use Guava\Calendar\Filament\CalendarWidget;
use Guava\Calendar\ValueObjects\DateClickInfo;
use Guava\Calendar\ValueObjects\FetchInfo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

class TimeEntryCalendarWidget extends CalendarWidget
{
    protected CalendarViewType $calendarView = CalendarViewType::DayGridMonth;

    protected bool $dateClickEnabled = true;

    protected bool $eventClickEnabled = true;

    protected ?string $defaultEventClickAction = 'edit';

    /**
     * @var array<string, mixed>
     */
    protected array $options = [
        'headerToolbar' => [
            'start' => 'title',
            'center' => '',
            'end' => 'today prev,next dayGridMonth,timeGridWeek,timeGridDay',
        ],
    ];

    /**
     * @return Collection<int, TimeEntry>|array<int, TimeEntry>|Builder<TimeEntry>
     */
    protected function getEvents(FetchInfo $info): Collection|array|Builder
    {
        return TimeEntry::query()
            ->where('user_id', $this->currentUser()->id)
            ->whereHas('project', fn (Builder $query) => $query->where('workspace_id', Filament::getTenant()?->getKey()))
            ->whereDate('date', '>=', $info->start)
            ->whereDate('date', '<=', $info->end);
    }

    public function createTimeEntryAction(): CreateAction
    {
        return $this->createAction(TimeEntry::class)
            ->mountUsing(function (Schema $schema, ?DateClickInfo $dateClick): void {
                $schema->fill([
                    'date' => $dateClick?->date->toDateString() ?? now()->toDateString(),
                ]);
            })
            ->using(fn (array $data): TimeEntry => app(CreateTimeEntryAction::class)
                ->handle($this->currentUser(), CreateTimeEntry::normalize($data)));
    }

    public function editAction(): EditAction
    {
        return parent::editAction()
            ->mutateRecordDataUsing(function (array $data, TimeEntry $record): array {
                $data['entry_mode'] = 'range';
                $data['started_at'] = $record->started_at->format('H:i');
                $data['ended_at'] = $record->ended_at?->format('H:i');

                return $data;
            })
            ->using(fn (TimeEntry $record, array $data): TimeEntry => app(UpdateTimeEntryAction::class)
                ->handle($record, $data));
    }

    protected function onDateClick(DateClickInfo $info): void
    {
        $this->mountAction('createTimeEntry');
    }

    private function currentUser(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
