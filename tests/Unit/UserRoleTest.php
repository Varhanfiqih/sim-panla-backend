<?php

use App\Models\User;

test('user role helper methods return the correct role status', function () {
    $superAdmin = new User(['role' => User::ROLE_SUPER_ADMIN]);
    $adminIt = new User(['role' => User::ROLE_ADMIN_IT]);
    $teacher = new User(['role' => User::ROLE_GURU]);
    $bkTeacher = new User(['role' => User::ROLE_GURU_BK]);

    expect($superAdmin->isSuperAdmin())->toBeTrue()
        ->and($superAdmin->isStaff())->toBeTrue()
        ->and($adminIt->isAdminIT())->toBeTrue()
        ->and($adminIt->isStaff())->toBeTrue()
        ->and($teacher->isGuru())->toBeTrue()
        ->and($teacher->isStaff())->toBeFalse()
        ->and($bkTeacher->isGuroBK())->toBeTrue();
});
