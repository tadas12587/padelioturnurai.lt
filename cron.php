<?php

// Host CRON entry point: the freehosting.lt panel only accepts a path to a
// .php file without arguments, so this is `php artisan schedule:run`.

use Symfony\Component\Console\Input\ArgvInput;

define('LARAVEL_START', microtime(true));

chdir(__DIR__);

require __DIR__.'/vendor/autoload.php';

/** @var \Illuminate\Foundation\Application $app */
$app = require_once __DIR__.'/bootstrap/app.php';

exit($app->handleCommand(new ArgvInput(['artisan', 'schedule:run'])));
