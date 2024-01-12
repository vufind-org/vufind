<?php

/**
 * PHP profiling support
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2012-2017.
 * Copyright (C) The National Library of Finland 2017-2021.
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:record_drivers Wiki
 */

/**
 * Enable profiler (XHProf or Tideways)
 *
 * @param string $profilerBaseUrl Profiler base url to display
 *
 * @return void
 */
function enableVuFindProfiling($profilerBaseUrl)
{
    $profilerEnableFunc = false;
    $profilerDisableFunc = false;
    if (extension_loaded('xhprof')) {
        $profilerEnableFunc = 'xhprof_enable';
        $profilerDisableFunc = 'xhprof_disable';
    } elseif (extension_loaded('tideways_xhprof')) {
        $profilerEnableFunc = 'tideways_xhprof_enable';
        $profilerDisableFunc = 'tideways_xhprof_disable';
    }

    if ($profilerEnableFunc && $profilerDisableFunc) {
        $profilerEnableFunc();

        $xhprofRunId = uniqid();
        $suffix = 'vufind';
        $profileUrl = "$profilerBaseUrl?run=$xhprofRunId&source=$suffix";
        // Set the header now as headers may get sent before the shutdown function is
        // reached:
        header("X-VuFind-Profiler-Results: $profileUrl");

        // Handle final profiling details:
        $shutdownFunc = function () use (
            $profileUrl,
            $xhprofRunId,
            $suffix,
            $profilerDisableFunc
        ) {
            $xhprofData = $profilerDisableFunc();
            $dir = ini_get('xhprof.output_dir');
            if (empty($dir)) {
                $dir = sys_get_temp_dir();
            }
            file_put_contents(
                "$dir/$xhprofRunId.$suffix.xhprof",
                serialize($xhprofData)
            );

            // Try to detect how the script was executed to add output appropriately:
            if (PHP_SAPI === 'cli') {
                echo PHP_EOL . "Profiler output: $profileUrl" . PHP_EOL;
                return;
            }
            $contentType = 'text/html';
            foreach (headers_list() as $header) {
                $parts = explode(': ', $header, 2);
                if (isset($parts[1]) && strtolower($parts[0]) === 'content-type') {
                    [$contentType] = explode('; ', $parts[1]);
                    break;
                }
            }
            if ('text/html' === $contentType) {
                echo '<a href="' . htmlspecialchars($profileUrl)
                    . '">Profiler output</a>';
            } else {
                error_log(
                    'Profiler output for ' . $_SERVER['REQUEST_URI']
                    . ": $profileUrl"
                );
            }
        };
        register_shutdown_function($shutdownFunc);
    }
}
