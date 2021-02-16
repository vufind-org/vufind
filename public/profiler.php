<?php
/**
 * PHP profiling support
 *
 * PHP version 7
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
function enableProfiler($profilerBaseUrl)
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

        // Handle final profiling details, if necessary:
        $shutdownFunc = function () use ($profilerBaseUrl, $profilerDisableFunc) {
            $xhprofData = $profilerDisableFunc();
            $xhprofRunId = uniqid();
            $suffix = 'vufind';
            $dir = ini_get('xhprof.output_dir');
            if (empty($dir)) {
                $dir = sys_get_temp_dir();
            }
            file_put_contents(
                "$dir/$xhprofRunId.$suffix.xhprof",
                serialize($xhprofData)
            );
            $url = "$profilerBaseUrl?run=$xhprofRunId&source=$suffix";

            // Try to detect how the script was executed to add output appropriately
            if ('cli' === php_sapi_name()) {
                echo PHP_EOL . "Profiler output: $url" . PHP_EOL;
                return;
            }
            $reqUri = $_SERVER['REQUEST_URI'] ?? '';
            if (strpos($reqUri, '/AJAX/') === false) {
                echo '<a href="' . htmlspecialchars($url) . '">Profiler output</a>';
            } else {
                error_log(
                    'Profiler output for ' . $_SERVER['REQUEST_URI'] . ": $url"
                );
            }
        };
        register_shutdown_function($shutdownFunc);
    }
}
