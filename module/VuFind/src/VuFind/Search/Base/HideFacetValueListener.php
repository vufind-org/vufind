<?php

/**
 * Hide values of facet for displaying
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2014.
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
 * @author   Frank Morgner <morgnerf@ub.uni-leipzig.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Search\Base;

use Laminas\EventManager\EventInterface;
use Laminas\EventManager\SharedEventManagerInterface;
use VuFindSearch\Backend\BackendInterface;
use VuFindSearch\Service;

use function is_callable;

/**
 * Hide single facet values from displaying.
 *
 * @category VuFind
 * @package  Search
 * @author   Frank Morgner <morgnerf@ub.uni-leipzig.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class HideFacetValueListener
{
    /**
     * Backend.
     *
     * @var BackendInterface
     */
    protected $backend;

    /**
     * List of facet values to show, indexed by facet field. All other facets are
     * hidden.
     *
     * @var array
     */
    protected $showFacets = [];

    /**
     * List of facet values to hide, indexed by facet field.
     *
     * @var array
     */
    protected $hideFacets = [];

    /**
     * Constructor.
     *
     * @param BackendInterface $backend         Search backend
     * @param array            $hideFacetValues Assoc. array of field
     * name => values to exclude from display.
     * @param array            $showFacetValues Assoc. array of field
     * name => values to exclusively show in display.
     */
    public function __construct(
        BackendInterface $backend,
        array $hideFacetValues,
        array $showFacetValues = []
    ) {
        $this->backend = $backend;
        $this->hideFacets = $hideFacetValues;
        $this->showFacets = $showFacetValues;
    }

    /**
     * Attach listener to shared event manager.
     *
     * @param SharedEventManagerInterface $manager Shared event manager
     *
     * @return void
     */
    public function attach(
        SharedEventManagerInterface $manager
    ) {
        $manager->attach(
            Service::class,
            Service::EVENT_POST,
            [$this, 'onSearchPost']
        );
    }

    /**
     * Hide facet values from display
     *
     * @param EventInterface $event Event
     *
     * @return EventInterface
     */
    public function onSearchPost(EventInterface $event)
    {
        $command = $event->getParam('command');

        if ($command->getTargetIdentifier() !== $this->backend->getIdentifier()) {
            return $event;
        }
        $context = $command->getContext();
        if ($context == 'search' || $context == 'retrieve') {
            $this->processHideFacetValue($event);
        }
        return $event;
    }

    /**
     * Process hide facet value
     *
     * @param EventInterface $event Event
     *
     * @return void
     */
    protected function processHideFacetValue($event)
    {
        $result = $event->getParam('command')->getResult();
        if (!$result) {
            return;
        }
        $facets = $result->getFacets();

        // Count how many values have been filtered as we go:
        $filteredFacetCounts = [];

        foreach ($this->hideFacets as $facet => $values) {
            foreach ((array)$values as $value) {
                if (isset($facets[$facet][$value])) {
                    unset($facets[$facet][$value]);
                    $filteredFacetCounts[$facet] = ($filteredFacetCounts[$facet] ?? 0) + 1;
                }
            }
        }
        foreach ($this->showFacets as $facet => $values) {
            if (isset($facets[$facet])) {
                $valuesToHide = array_diff(
                    array_keys($facets[$facet]),
                    (array)$values
                );
                foreach ($valuesToHide as $valueToHide) {
                    if (isset($facets[$facet][$valueToHide])) {
                        unset($facets[$facet][$valueToHide]);
                        $filteredFacetCounts[$facet] = ($filteredFacetCounts[$facet] ?? 0) + 1;
                    }
                }
            }
        }

        // If the result object is capable of receiving filter counts, send the data:
        if (is_callable([$result, 'setFilteredFacetCounts'])) {
            $result->setFilteredFacetCounts($filteredFacetCounts);
        }

        $result->setFacets($facets);
    }
}
