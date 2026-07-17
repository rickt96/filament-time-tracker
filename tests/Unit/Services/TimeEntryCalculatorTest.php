<?php

use App\Services\TimeEntryCalculator;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->calculator = new TimeEntryCalculator;
});

test('resolveTimes uses the explicit range when started_at and ended_at are given', function () {
    [$start, $end] = $this->calculator->resolveTimes([
        'date' => '2026-07-16',
        'started_at' => '09:00',
        'ended_at' => '11:30',
    ]);

    expect($start->format('Y-m-d H:i'))->toBe('2026-07-16 09:00')
        ->and($end->format('Y-m-d H:i'))->toBe('2026-07-16 11:30');
});

test('resolveTimes falls back to midnight plus duration_minutes when no range is given', function () {
    [$start, $end] = $this->calculator->resolveTimes([
        'date' => '2026-07-16',
        'duration_minutes' => 90,
    ]);

    expect($start->format('Y-m-d H:i'))->toBe('2026-07-16 00:00')
        ->and($end->format('Y-m-d H:i'))->toBe('2026-07-16 01:30');
});

test('durationInSeconds computes the difference between two instants', function () {
    $start = Carbon::parse('2026-07-16 09:00');
    $end = Carbon::parse('2026-07-16 10:15');

    expect($this->calculator->durationInSeconds($start, $end))->toBe(75 * 60);
});

test('endFromDuration adds seconds to the start instant', function () {
    $start = Carbon::parse('2026-07-16 09:00');

    $end = $this->calculator->endFromDuration($start, 1800);

    expect($end->format('H:i'))->toBe('09:30');
});

test('amount returns null when there is no hourly rate', function () {
    expect($this->calculator->amount(3600, null))->toBeNull();
});

test('amount multiplies duration in hours by the hourly rate', function () {
    expect($this->calculator->amount(3600 * 2, '50.00'))->toBe('100.00');
});
