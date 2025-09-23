<?php

/**
 * Setup remote code coverage support if requested
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2023.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Profiling
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */

use SebastianBergmann\CodeCoverage\CodeCoverage;
use SebastianBergmann\CodeCoverage\Driver\Selector;
use SebastianBergmann\CodeCoverage\Filter;
use SebastianBergmann\CodeCoverage\Report\PHP as PHPReport;

/**
 * Setup remote code coverage support if requested
 *
 * @param array $modules Active modules
 *
 * @return void
 */
function setupVuFindRemoteCodeCoverage(array $modules): void
{
    if (!($coverageHeader = $_SERVER['HTTP_X_VUFIND_REMOTE_COVERAGE'] ?? null)) {
        return;
    }

    $error = function ($msg) {
        error_log("setupVuFindRemoteCodeCoverage: $msg");
        throw new \Exception($msg);
    };

    if (!($command = json_decode($coverageHeader, true))) {
        $error('Cannot decode remote coverage header');
    }
    $action = $command['action'] ?? null;
    $testName = $command['testName'] ?? null;
    $outputDir = $command['outputDir'] ?? null;
    if ('record' !== $action || !$testName || !$outputDir) {
        $error('Invalid remote coverage command');
    }
    if (!is_dir($outputDir)) {
        $error("setupVuFindRemoteCodeCoverage: Bad output directory $outputDir");
    }
    // Ensure that a cache directory for static analysis exists:
    $cacheDir = LOCAL_CACHE_DIR . '/coverage';
    if (!is_dir($cacheDir)) {
        if (!mkdir($cacheDir)) {
            $error("Failed to create cache directory $cacheDir");
        }
        chmod($cacheDir, 0o775);
    }

    try {
        $filter = new Filter();
        foreach ($modules as $module) {
            $moduleDir = __DIR__ . "/../../$module";
            if (!str_contains($module, '\\') && is_dir($moduleDir)) {
                foreach (
                    (new \SebastianBergmann\FileIterator\Facade())->getFilesAsArray("$moduleDir/src/", '.php') as $file
                ) {
                    $filter->includeFile($file);
                }
            }
        }

        $coverage = new CodeCoverage(
            (new Selector())->forLineCoverage($filter),
            $filter
        );
        $coverage->cacheStaticAnalysis($cacheDir);
    } catch (\Exception $e) {
        $error('Failed to create collector: ' . (string)$e);
    }

    $outputDir .= '/' . urlencode($testName);
    if (!is_dir($outputDir)) {
        if (!mkdir($outputDir)) {
            $error("Failed to create output directory $outputDir");
        }
        chmod($outputDir, 0o775);
    }
    $outputFile = $outputDir . '/coverage-' . time() . '-' . getmypid() . '.cov';
    header('X-VuFind-Coverage: ' . basename($outputFile));

    $coverage->start($testName);

    // Write coverage report on shutdown:
    $shutdownFunc = function () use ($coverage, $outputFile, $cacheDir): void {
        $coverage->stop();
        $reporter = new PHPReport();
        $result = $reporter->process($coverage);
        file_put_contents($outputFile, $result);
        chmod($outputFile, 0o664);
        // Reset permissions of static analysis cache files:
        foreach (glob("$cacheDir/*") as $file) {
            chmod($file, 0o664);
        }
    };
    register_shutdown_function($shutdownFunc);
}
