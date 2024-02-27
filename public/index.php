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
    // Setup remote code coverage if enabled:
    if (getenv('VUFIND_CODE_COVERAGE')) {
        $modules = $app->getServiceManager()
            ->get(\Laminas\ModuleManager\ModuleManager::class)->getModules();
        include __DIR__ . '/../module/VuFind/functions/codecoverage.php';
        setupVuFindRemoteCodeCoverage($modules);
    }
    $app->run();
}
