<?php

namespace App\Support;

class DurationFormatter
{
    public static function hoursMinutesSeconds(int $seconds): string
    {
        $seconds = max(0, $seconds);

        return sprintf('%d:%02d:%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60), $seconds % 60);
    }
}
