<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$user = \App\Models\User::where('nip', '198106202009041003')->first();
if ($user) {
    echo "NIP ditemukan: " . $user->nip . "\n";
    echo "Nama: " . $user->name . "\n";
    echo "Role: " . $user->role . "\n";
    
    // Coba verifikasi password
    $passwordMatch = \Illuminate\Support\Facades\Hash::check('kepsek123', $user->password);
    echo "Password 'kepsek123' match? " . ($passwordMatch ? 'YA' : 'TIDAK') . "\n";
    
    // Test Auth Attempt (login web)
    $authWeb = \Illuminate\Support\Facades\Auth::attempt(['nip' => '198106202009041003', 'password' => 'kepsek123']);
    echo "Auth Attempt Web success? " . ($authWeb ? 'YA' : 'TIDAK') . "\n";
} else {
    echo "USER DENGAN NIP 198106202009041003 TIDAK DITEMUKAN!\n";
}
