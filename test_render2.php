<?php
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = \App\Models\User::first();
if ($user) {
    auth()->login($user);
    echo "Logged in as: " . $user->email . "\n";
} else {
    echo "No user found\n";
    exit(1);
}

$encryptedId = 'eyJpdiI6IlZ4aUhza0ZMR25wU0pXTDduOUdlTHc9PSIsInZhbHVlIjoiNzViMFFNaWdubUg4a2dZQnhBZmc3dz09IiwibWFjIjoiYjFjYmE2YTEzMmU1ODBkOWFkNWY2ZWIzMGM4NmVjNjlhN2ZkYWQ4NTQwYTk4NjllY2JjYjJjMjU5ZGMzZGJlNCIsInRhZyI6IiJ9';

$request = \Illuminate\Http\Request::create('/users/add-or-edit/' . $encryptedId, 'GET');

try {
    $response = $app->handleRequest($request);
    $content = $response->getContent();
    
    if (str_contains($content, 'Class \'Crypt\' not found')) {
        echo "ERROR: Class Crypt not found in rendered output!\n";
    } elseif (str_contains($content, 'Enkripsi tidak valid')) {
        echo "ERROR: Decrypt exception in rendered output!\n";
    } else {
        echo "Render OK (status: " . $response->getStatusCode() . ")\n";
        // Check if edit form is shown (meaning decryption worked)
        if (str_contains($content, 'Edit User')) {
            echo "SUCCESS: Decryption worked - showing Edit User form\n";
        } else {
            echo "INFO: Decryption may have failed or user ID 1 doesn't exist\n";
        }
    }
} catch (\Throwable $e) {
    echo "EXCEPTION: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " Line: " . $e->getLine() . "\n";
}
