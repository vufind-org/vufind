<?php

/**
 * Mix-in for detecting whether a live test environment is currently running.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2021.
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

namespace VuFindTest\Feature;

/**
 * Mix-in for detecting whether a live test environment is currently running.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
trait LiveDetectionTrait
{
    /**
     * Flag to allow other traits to test for the presence of this one (to enforce
     * dependencies).
     *
     * @var bool
     */
    public $hasLiveDetectionTrait = true;

    /**
     * Is this test running in a continuous integration context?
     *
     * @return bool
     */
    public function continuousIntegrationRunning()
    {
        // We'll assume that if the CI Solr PID is present, then CI is active:
        $port = getenv('SOLR_PORT') ?? '8983';
        return file_exists(__DIR__ . "/../../../../../local/solr-$port.pid");
    }
}
