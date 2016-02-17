<?php

/**
 * Finna Solr extensions listener.
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2013-2016.
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Search
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace Finna\Search\Solr;

use VuFindSearch\Backend\BackendInterface;

use Zend\EventManager\EventInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\EventManager\SharedEventManagerInterface;

/**
 * Finna Solr extensions listener.
 *
 * @category VuFind2
 * @package  Finna
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class SolrExtensionsListener
{
    /**
     * Backend.
     *
     * @var BackendInterface
     */
    protected $backend;

    /**
     * Superior service manager.
     *
     * @var ServiceLocatorInterface
     */
    protected $serviceLocator;

    /**
     * Search configuration file identifier.
     *
     * @var string
     */
    protected $searchConfig;

    /**
     * Data source configuration file identifier.
     *
     * @var string
     */
    protected $dataSourceConfig;

    /**
     * Facet configuration file identifier.
     *
     * @var string
     */
    protected $facetConfig;

    /**
     * Constructor.
     *
     * @param BackendInterface        $backend          Search backend
     * @param ServiceLocatorInterface $serviceLocator   Service locator
     * @param string                  $searchConfig     Search config file id
     * @param string                  $facetConfig      Facet config file id
     * @param string                  $dataSourceConfig Data source file id
     *
     * @return void
     */
    public function __construct(
        BackendInterface $backend,
        ServiceLocatorInterface $serviceLocator,
        $searchConfig, $facetConfig, $dataSourceConfig = 'datasources'
    ) {
        $this->backend = $backend;
        $this->serviceLocator = $serviceLocator;
        $this->searchConfig = $searchConfig;
        $this->facetConfig = $facetConfig;
        $this->dataSourceConfig = $dataSourceConfig;
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
        $manager->attach('VuFind\Search', 'pre', [$this, 'onSearchPre']);
    }

    /**
     * Customize Solr request.
     *
     * @param EventInterface $event Event
     *
     * @return EventInterface
     */
    public function onSearchPre(EventInterface $event)
    {
        $backend = $event->getTarget();
        if ($backend === $this->backend) {
            $this->addDataSourceFilter($event);
            if ($event->getParam('context') == 'search') {
                $this->limitHierarchicalFacets($event);
                $this->addHiddenComponentPartFilter($event);
                $this->handleOnlineBoolean($event);
            }
        }
        return $event;
    }

    /**
     * Add data source filter per search config.
     *
     * @param EventInterface $event Event
     *
     * @return void
     */
    protected function addDataSourceFilter(EventInterface $event)
    {
        $config = $this->serviceLocator->get('VuFind\Config');
        $searchConfig = $config->get($this->searchConfig);
        if (isset($searchConfig->Records->sources)
            && $searchConfig->Records->sources
        ) {
            $sources = array_map(
                function ($input) {
                    return '"' . addcslashes($input, '"') . '"';
                },
                explode(',', $searchConfig->Records->sources)
            );
            $params = $event->getParam('params');
            if ($params) {
                $params->add(
                    'fq',
                    'source_str_mv:(' . implode(' OR ', $sources) . ')'
                );
            }
        }
    }

    /**
     * Add hidden component part filter per search config.
     *
     * @param EventInterface $event Event
     *
     * @return void
     */
    protected function addHiddenComponentPartFilter(EventInterface $event)
    {
        $config = $this->serviceLocator->get('VuFind\Config');
        $searchConfig = $config->get($this->searchConfig);
        if (isset($searchConfig->General->hide_component_parts)
            && $searchConfig->General->hide_component_parts
        ) {
            $params = $event->getParam('params');
            if ($params) {
                $params->add('fq', '-hidden_component_boolean:true');
            }
        }
    }

    /**
     * Change the online_boolean filter to online_str_mv filter if deduplication is
     * enabled
     *
     * @param EventInterface $event Event
     *
     * @return void
     */
    protected function handleOnlineBoolean(EventInterface $event)
    {
        $config = $this->serviceLocator->get('VuFind\Config');
        $searchConfig = $config->get($this->searchConfig);
        if (isset($searchConfig->Records->deduplication)
            && $searchConfig->Records->deduplication
            && isset($searchConfig->Records->sources)
            && !empty($searchConfig->Records->sources)
        ) {
            $params = $event->getParam('params');
            $filters = $params->get('fq');
            if (null !== $filters) {
                foreach ($filters as $key => $value) {
                    if ($value == 'online_boolean:"1"') {
                        unset($filters[$key]);
                        $sources = explode(',', $searchConfig->Records->sources);
                        $sources = array_map(
                            function ($s) {
                                return "\"$s\"";
                            },
                            $sources
                        );
                        $filters[] = 'online_str_mv:(' . implode(' OR ', $sources)
                            . ')';
                        $params->set('fq', $filters);
                        break;
                    }
                }
            }
            $facets = $params->get('facet.field');
            if (null !== $facets) {
                foreach ($facets as $key => $value) {
                    if (substr($value, -14) == 'online_boolean') {
                        unset($facets[$key]);
                        $params->set('facet.field', $facets);
                        break;
                    }
                }
            }
        }
    }

    /**
     * Since we don't support non-JS hierarchical facets, limit them to one entry
     * that's needed for checking whether there's something to display.
     *
     * @param EventInterface $event Event
     *
     * @return void
     */
    protected function limitHierarchicalFacets(EventInterface $event)
    {
        $params = $event->getParam('params');
        // Check if facets are requested at all
        $fields = $params->get('facet.field');
        if ($fields === null) {
            return;
        }
        $config = $this->serviceLocator->get('VuFind\Config');
        $facetConfig = $config->get($this->facetConfig);
        if (empty($facetConfig->SpecialFacets->hierarchical)) {
            return;
        }
        // Check if we're retrieving the complete list or something else than records
        // (limit=0, e.g. facets for search screen)
        $limit = $params->get('limit');
        $facetLimit = $params->get('facet.limit');
        if ($facetLimit === null || $facetLimit[0] == -1 || $limit === 0) {
            return;
        }
        $hierarchical = $facetConfig->SpecialFacets->hierarchical->toArray();
        foreach ($hierarchical as $facet) {
            $params->set("f.$facet.facet.limit", 1);
        }
    }
}
