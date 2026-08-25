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

$request = \Illuminate\Http\Request::create('/users/add-or-edit/add', 'GET');

try {
    $response = $app->handleRequest($request);
    $content = $response->getContent();
    
    if (str_contains($content, 'Class \'Crypt\' not found')) {
        echo "ERROR: Class Crypt not found in rendered output!\n";
    } elseif (str_contains($content, 'Enkripsi tidak valid')) {
        echo "ERROR: Decrypt exception in rendered output!\n";
    } else {
        echo "Render OK (status: " . $response->getStatusCode() . ")\n";
        echo "First 300 chars: " . substr($content, 0, 300) . "\n";
    }
} catch (\Throwable $e) {
    echo "EXCEPTION: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " Line: " . $e->getLine() . "\n";
}
