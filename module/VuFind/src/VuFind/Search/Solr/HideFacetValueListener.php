<?php
/**
 * Hide values of facet for displaying
 *
 * PHP version 5
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
namespace VuFind\Search\Solr;

use VuFindSearch\Backend\BackendInterface;
use Zend\EventManager\SharedEventManagerInterface;
use Zend\EventManager\EventInterface;

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
     * List of facets to hide.
     *
     * @var array
     */
    protected $hideFacets = [];

    /**
     * Constructor.
     *
     * @param BackendInterface $backend         Search backend
     * @param array            $hideFacetValues Associative array of field name
     * to array of facet values to hide.
     */
    public function __construct(
        BackendInterface $backend,
        array $hideFacetValues
    ) {
        $this->backend = $backend;
        $this->hideFacets = $hideFacetValues;
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
        $manager->attach('VuFind\Search', 'post', [$this, 'onSearchPost']);
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
        $backend = $event->getParam('backend');

        if ($backend != $this->backend->getIdentifier()) {
            return $event;
        }
        $context = $event->getParam('context');
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
        $result = $event->getTarget();
        $facets = $result->getFacets()->getFieldFacets();

        foreach ($this->hideFacets as $facet => $value) {
            if (isset($facets[$facet])) {
                $facets[$facet]->removeKeys((array)$value);
            }
        }
        return null;
    }
}
