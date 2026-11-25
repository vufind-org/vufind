<?php

// If the profiler is enabled, set it up now:
$vufindProfiler = getenv('VUFIND_PROFILER_XHPROF');
if (!empty($vufindProfiler)) {
    include __DIR__ . '/../module/VuFind/functions/profiler.php';
    enableVuFindProfiling($vufindProfiler);
}

// Run the application!
$serviceManager = require __DIR__ . '/../config/container.php';
if (PHP_SAPI === 'cli') {
    return $serviceManager->get(\VuFindConsole\ConsoleRunner::class)->run();
} else {
    // Setup remote code coverage if enabled:
    if (getenv('VUFIND_CODE_COVERAGE')) {
        $modules = $serviceManager->get(\VuFind\ModuleManager\ModuleManager::class)->getModules();
        include __DIR__ . '/../module/VuFind/functions/codecoverage.php';
        setupVuFindRemoteCodeCoverage($modules);
    }
    $serviceManager->get(\VuFind\Application::class)->run();
}
