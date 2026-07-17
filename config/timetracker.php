<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Budget utilization thresholds
    |--------------------------------------------------------------------------
    |
    | Percentages at which budget dashboards switch their visual indicator
    | (e.g. from "success" to "warning" to "danger"). Values are evaluated
    | in ascending order: below the first threshold is "success", between
    | thresholds is "warning", at or above the last threshold is "danger".
    |
    */

    'budget_thresholds' => [80, 90, 100],

];
