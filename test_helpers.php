<?php
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

if (function_exists('encrypt') && function_exists('decrypt')) {
    $encrypted = encrypt('test');
    $decrypted = decrypt($encrypted);
    echo "Global encrypt/decrypt helpers work: $decrypted\n";
} else {
    echo "Global encrypt/decrypt helpers NOT FOUND\n";
}
