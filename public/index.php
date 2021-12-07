<?php

// Run the application!
$app = include __DIR__ . '/../config/application.php';
if (PHP_SAPI === 'cli') {
    return $app->getServiceManager()
        ->get(\VuFindConsole\ConsoleRunner::class)->run();
} else {
    $app->run();
}
