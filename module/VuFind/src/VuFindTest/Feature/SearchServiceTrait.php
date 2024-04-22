<?php

/**
 * Mix-in for constructing the search service for tests.
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

use VuFind\Search\BackendManager;

/**
 * Mix-in for constructing the search service for tests.
 *
 * @category VuFind
 * @package  Tests
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:testing:unit_tests Wiki
 */
trait SearchServiceTrait
{
    /**
     * Create a search service to wrap the provided BackendManager instance
     *
     * @param BackendManager $bm BackendManager to wrap in service
     *
     * @return \VuFindSearch\Service
     */
    protected function getSearchService(BackendManager $bm)
    {
        $shared = new \Laminas\EventManager\SharedEventManager();
        $shared->attach(
            \VuFindSearch\Service::class,
            \VuFindSearch\Service::EVENT_RESOLVE,
            [$bm, 'onResolve']
        );
        $events = new \Laminas\EventManager\EventManager($shared);
        return new \VuFindSearch\Service($events);
    }
}
