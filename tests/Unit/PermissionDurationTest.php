<?php

use App\Models\Permission;
use Carbon\Carbon;

test('permission total hari counts the start and end dates inclusively', function () {
    $permission = new Permission();
    $permission->setRawAttributes([
        'start_date' => Carbon::parse('2026-07-01'),
        'end_date' => Carbon::parse('2026-07-03'),
    ], true);

    expect($permission->total_hari)->toBe(3);
});

test('permission total hari is one for a same day permission', function () {
    $permission = new Permission();
    $permission->setRawAttributes([
        'start_date' => Carbon::parse('2026-07-12'),
        'end_date' => Carbon::parse('2026-07-12'),
    ], true);

    expect($permission->total_hari)->toBe(1);
});
