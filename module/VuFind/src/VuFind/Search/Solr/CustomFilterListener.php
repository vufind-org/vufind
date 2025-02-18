<?php

/**
 * Solr custom filter listener.
 *
 * This can translate a simple filter into a complex set of filters, and it can
 * "invert" filters by applying Solr filters only when a VuFind filter is absent.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2022.
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
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Search\Solr;

use Laminas\EventManager\EventInterface;
use Laminas\EventManager\SharedEventManagerInterface;
use VuFindSearch\Backend\BackendInterface;
use VuFindSearch\Service;

/**
 * Solr custom filter listener.
 *
 * @category VuFind
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class CustomFilterListener
{
    /**
     * Backend.
     *
     * @var BackendInterface
     */
    protected $backend;

    /**
     * Normal filters
     *
     * @var array
     */
    protected $normalFilters;

    /**
     * Inverted filters
     *
     * @var array
     */
    protected $invertedFilters;

    /**
     * Name of parameter used to store filters
     *
     * @var string
     */
    protected $filterParam = 'fq';

    /**
     * Constructor.
     *
     * @param BackendInterface $backend  Backend
     * @param array            $normal   Normal custom filters (placeholder => full
     * filter)
     * @param array            $inverted Inverted custom filters (applied unless set)
     *
     * @return void
     */
    public function __construct(
        BackendInterface $backend,
        array $normal,
        array $inverted
    ) {
        $this->backend = $backend;
        $this->normalFilters = $normal;
        $this->invertedFilters = $inverted;
    }

    /**
     * Attach listener to shared event manager.
     *
     * @param SharedEventManagerInterface $manager Shared event manager
     *
     * @return void
     */
    public function attach(SharedEventManagerInterface $manager)
    {
        $manager->attach(
            Service::class,
            Service::EVENT_PRE,
            [$this, 'onSearchPre']
        );
    }

    /**
     * Apply/translate custom filters.
     *
     * @param EventInterface $event Event
     *
     * @return EventInterface
     */
    public function onSearchPre(EventInterface $event)
    {
        $command = $event->getParam('command');
        if (
            $command->getContext() === 'search'
            && $command->getTargetIdentifier() === $this->backend->getIdentifier()
            && ($params = $command->getSearchParameters())
        ) {
            $invertedFiltersMatched = [];
            $finalFilters = [];
            foreach ($params->get($this->filterParam) ?? [] as $filter) {
                if (isset($this->invertedFilters[$filter])) {
                    // Make note of matched inverted filters for later:
                    $invertedFiltersMatched[$filter] = true;
                } elseif (isset($this->normalFilters[$filter])) {
                    // Translate normal custom filters:
                    $finalFilters[] = $this->normalFilters[$filter];
                } else {
                    // Keep all unmatched filters:
                    $finalFilters[] = $filter;
                }
            }
            // Now apply any inverted filters that were not matched above:
            foreach ($this->invertedFilters as $placeholder => $result) {
                if (!($invertedFiltersMatched[$placeholder] ?? false)) {
                    $finalFilters[] = $result;
                }
            }
            $params->set($this->filterParam, $finalFilters);
        }
        return $event;
    }
}
