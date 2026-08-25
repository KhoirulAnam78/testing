<?php
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$blade = new Illuminate\View\Compilers\BladeCompiler(
    new Illuminate\Filesystem\Filesystem(__DIR__ . '/resources/views'),
    __DIR__ . '/storage/framework/views'
);

$files = [
    'resources/views/pages/users/add_edit.blade.php',
    'resources/views/pages/roles/add_edit.blade.php',
    'resources/views/pages/menu/add_edit.blade.php',
];

foreach ($files as $file) {
    $content = file_get_contents(__DIR__ . '/' . $file);
    try {
        $blade->compileString($content);
        echo "$file: Compiled OK\n";
    } catch (\Throwable $e) {
        echo "$file: ERROR - " . $e->getMessage() . "\n";
    }
}
