<?php

return [
    // Daily income rates per object type (currency units per aggregate unit)
    // Example: 'house' => 1 means each aggregated unit from houses gives 1 balance per day.
    'daily_income_rates' => [
        'house' => 1,
        'hospital' => 0,
        'bank' => 2,
        // add other object types as needed
    ],
];
