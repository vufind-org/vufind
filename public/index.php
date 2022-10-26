<?php

// If the profiler is enabled, set it up now:
$vufindProfiler = getenv('VUFIND_PROFILER_XHPROF');
if (!empty($vufindProfiler)) {
    include __DIR__ . '/../module/VuFind/functions/profiler.php';
    enableVuFindProfiling($vufindProfiler);
}

// Run the application!
$app = include __DIR__ . '/../config/application.php';
if (PHP_SAPI === 'cli') {
    return $app->getServiceManager()
        ->get(\VuFindConsole\ConsoleRunner::class)->run();
} else {
    $app->run();
}
