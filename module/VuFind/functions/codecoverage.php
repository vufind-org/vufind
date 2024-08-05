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

    try {
        $filter = new Filter();
        foreach ($modules as $module) {
            $moduleDir = __DIR__ . "/../../$module";
            if (!str_contains($module, '\\') && is_dir($moduleDir)) {
                $filter->includeDirectory("$moduleDir/src/");
            }
        }

        $coverage = new CodeCoverage(
            (new Selector())->forLineCoverage($filter),
            $filter
        );
    } catch (\Exception $e) {
        $error('Failed to create collector: ' . (string)$e);
    }

    $outputDir .= '/' . urlencode($testName);
    if (!is_dir($outputDir)) {
        if (!mkdir($outputDir)) {
            $error("Failed to create output directory $outputDir");
        }
    }
    $outputFile = $outputDir . '/coverage-' . time() . '-' . getmypid() . '.cov';
    header('X-VuFind-Coverage: ' . basename($outputFile));

    $coverage->start($testName);

    // Write coverage report on shutdown:
    $shutdownFunc = function () use ($coverage, $outputFile): void {
        $coverage->stop();
        $reporter = new PHPReport();
        $result = $reporter->process($coverage);
        file_put_contents($outputFile, $result);
    };
    register_shutdown_function($shutdownFunc);
}
