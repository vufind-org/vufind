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
 * @category VuFind
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
 * @category VuFind
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
        $manager->attach('VuFind\Search', 'post', [$this, 'onSearchPost']);
    }

    /**
     * Customize Solr response.
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

        if ($event->getParam('context') == 'search') {
            $this->displayDebugInfo($event);
        }
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
     * Display debug information about the query
     *
     * @param EventInterface $event Event
     *
     * @return void
     */
    protected function displayDebugInfo(EventInterface $event)
    {
        $params = $event->getParam('params');
        if (!$params->get('debugQuery')) {
            return;
        }
        $collection = $event->getTarget();
        $debugInfo = $collection->getDebugInformation();
        echo "<!--\n";
        echo 'Raw query string: ' . $debugInfo['rawquerystring'] . "\n\n";
        echo 'Query string: ' . $debugInfo['querystring'] . "\n\n";
        echo 'Parsed query: ' . $debugInfo['parsedquery'] . "\n\n";
        echo 'Query parser: ' . $debugInfo['QParser'] . "\n\n";
        echo 'Alt query string: ' . $debugInfo['altquerystring'] . "\n\n";
        echo "Boost functions:\n";
        if ($debugInfo['boostfuncs']) {
            echo '  ' . implode("\n  ", $debugInfo['boostfuncs']);
        }
        echo "\n\n";
        echo "Filter queries:\n";
        if ($debugInfo['filter_queries']) {
            echo '  ' . implode("\n  ", $debugInfo['filter_queries']);
        }
        echo "\n\n";
        echo "Parsed filter queries:\n";
        if ($debugInfo['parsed_filter_queries']) {
            echo '  ' . implode("\n  ", $debugInfo['parsed_filter_queries']);
        }
        echo "\n\n";
        echo "Timing:\n";
        echo "  Total: " . $debugInfo['timing']['time'] . "\n";
        echo "  Prepare:\n";
        foreach ($debugInfo['timing']['prepare'] as $key => $value) {
            echo "    $key: ";
            echo is_array($value) ? $value['time'] : $value;
            echo "\n";
        }
        echo "  Process:\n";
        foreach ($debugInfo['timing']['process'] as $key => $value) {
            echo "    $key: ";
            echo is_array($value) ? $value['time'] : $value;
            echo "\n";
        }

        echo "\n\n";

        if (!empty($debugInfo['explain'])) {
            echo "Record weights:\n\n";
            $explain = array_values($debugInfo['explain']);
            $i = -1;
            foreach ($collection->getRecords() as $record) {
                ++$i;
                $id = $record->getUniqueID();
                echo "$id";
                $dedupData = $record->getDedupData();
                if ($dedupData) {
                    echo ' (duplicates: ';
                    $ids = [];
                    foreach ($dedupData as $item) {
                        if ($item['id'] != $id) {
                            $ids[] = $item['id'];
                        }
                    }
                    echo implode(', ', $ids) . ')';
                }
                echo ':';
                if (isset($explain[$i])) {
                    print_r($explain[$i]);
                }

                echo "\n";
            }
        }
        echo "-->\n";
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
