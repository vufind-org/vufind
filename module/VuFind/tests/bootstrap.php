<?php

/**
 * Bootstrap logic for PHPUnit
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2023.
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
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */

require __DIR__ . '/bootstrap_constants.php';
require __DIR__ . '/../../../config/constants.config.php';

chdir(APPLICATION_PATH);

// Composer autoloading
if (file_exists('vendor/autoload.php')) {
    include 'vendor/autoload.php';
    $loader = new Composer\Autoload\ClassLoader();
    $loader->addClassMap(['minSO' => __DIR__ . '/../src/VuFind/Search/minSO.php']);
    $loader->add('VuFindTest', __DIR__ . '/unit-tests/src');
    $loader->add('VuFindTest', __DIR__ . '/../src');
    // Dynamically discover all module src directories:
    $modules = opendir(__DIR__ . '/../..');
    while ($mod = readdir($modules)) {
        $mod = trim($mod, '.'); // ignore . and ..
        $dir = empty($mod) ? false : realpath(__DIR__ . "/../../{$mod}/src");
        if (!empty($dir) && is_dir($dir . '/' . $mod)) {
            $loader->add($mod, $dir);
        }
    }
    $loader->register();
}

// Make sure local config dir exists:
if (!defined('LOCAL_OVERRIDE_DIR')) {
    throw new \Exception('LOCAL_OVERRIDE_DIR must be defined');
}
if (!file_exists(LOCAL_OVERRIDE_DIR)) {
    mkdir(LOCAL_OVERRIDE_DIR, 0o777, true);
}
