<?php

namespace App\Filament\Widgets;

use App\Actions\TimeEntry\CreateTimeEntryAction;
use App\Actions\TimeEntry\UpdateTimeEntryAction;
use App\Filament\Resources\TimeEntries\Pages\CreateTimeEntry;
use App\Filament\Resources\TimeEntries\Schemas\TimeEntryForm;
use App\Models\TimeEntry;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Guava\Calendar\Attributes\CalendarSchema;
use Guava\Calendar\Enums\CalendarViewType;
use Guava\Calendar\Filament\Actions\CreateAction;
use Guava\Calendar\Filament\Actions\EditAction;
use Guava\Calendar\Filament\CalendarWidget;
use Guava\Calendar\ValueObjects\DateClickInfo;
use Guava\Calendar\ValueObjects\EventDropInfo;
use Guava\Calendar\ValueObjects\EventResizeInfo;
use Guava\Calendar\ValueObjects\FetchInfo;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;
use Override;

class TimeEntryCalendarWidget extends CalendarWidget
{
    protected CalendarViewType $calendarView = CalendarViewType::TimeGridWeek;

    #[Override]
    public function getHeading(): null|string|HtmlString
    {
        return null;
    }

    protected bool $dateClickEnabled = true;

    protected bool $eventClickEnabled = true;

    protected bool $eventDragEnabled = true;

    protected bool $eventResizeEnabled = true;

    protected ?string $defaultEventClickAction = 'edit';

    /**
     * @var array<string, mixed>
     */
    protected array $options = [
        'headerToolbar' => [
            'start' => 'timeGridWeek,timeGridDay',
            'center' => 'title',
            'end' => 'today prev,next',
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
                    'started_at' => $dateClick?->date?->format("H:i"),
                    'ended_at' => $dateClick?->date?->addHour()->format("H:i"),
                ]);
            })
            ->using(function (array $data) {
                return app(CreateTimeEntryAction::class)->handle(
                        $this->currentUser(), 
                        CreateTimeEntry::normalize($data)
                    );
            });
    }

    public function editAction(): EditAction
    {
        return parent::editAction()
            ->mutateRecordDataUsing(function (array $data, TimeEntry $record): array {
                $data['started_at'] = $record->started_at->format('H:i');
                $data['ended_at'] = $record->ended_at?->format('H:i');

                return $data;
            })
            ->using(fn (TimeEntry $record, array $data): TimeEntry => app(UpdateTimeEntryAction::class)
                ->handle($record, $data));
    }

    /**
     * Used for both the create and edit calendar actions (see HasSchema's
     * resolution order in the guava/calendar package) instead of
     * TimeEntryResource's own form: on a calendar, entries are always
     * created/edited by working directly with a time range on the grid, so
     * start/end must always be present, unlike the multi-mode Resource form.
     */
    #[CalendarSchema(TimeEntry::class)]
    public function timeEntrySchema(Schema $schema): Schema
    {
        return TimeEntryForm::configureRangeOnly($schema);
    }

    protected function onDateClick(DateClickInfo $info): void
    {
        $this->mountAction('createTimeEntry');
    }

    /**
     * @param  TimeEntry  $event
     */
    protected function onEventDrop(EventDropInfo $info, Model $event): bool
    {
        $result = $this->applyRescheduledTimes($event, $info->event->getStart(), $info->event->getEnd());

        Notification::make()
            ->success()
            ->title('Orario modificato')
            ->send();

        return $result;
    }

    /**
     * @param  TimeEntry  $event
     */
    protected function onEventResize(EventResizeInfo $info, Model $event): bool
    {
        $result = $this->applyRescheduledTimes($event, $info->event->getStart(), $info->event->getEnd());

        Notification::make()
            ->success()
            ->title('Orario modificato')
            ->send();

        return $result;
    }

    private function applyRescheduledTimes(TimeEntry $event, Carbon $start, Carbon $end): bool
    {
        try {
            app(UpdateTimeEntryAction::class)->handle($event, [
                'date' => $start->toDateString(),
                'started_at' => $start->toIso8601String(),
                'ended_at' => $end->toIso8601String(),
            ]);
        } catch (ValidationException $exception) {
            Notification::make()
                ->title('Spostamento non valido')
                ->body(collect($exception->errors())->flatten()->implode(' '))
                ->danger()
                ->send();

            return false;
        }

        return true;
    }

    private function currentUser(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
