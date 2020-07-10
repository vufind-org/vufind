<?php

/**
 * Finna Solr extensions listener.
 *
 * PHP version 7
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Search
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace Finna\Search\Solr;

use Laminas\EventManager\EventInterface;

use Laminas\EventManager\SharedEventManagerInterface;
use Laminas\ServiceManager\ServiceLocatorInterface;
use VuFindSearch\Backend\BackendInterface;

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
            $context = $event->getParam('context');
            if (in_array($context, ['search', 'getids'])) {
                $this->addHiddenComponentPartFilter($event);
                $this->handleAvailabilityFilters($event);
            }
            if ('search' === $context) {
                $this->addGeoFilterBoost($event);
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
        $config = $this->serviceLocator->get(\VuFind\Config\PluginManager::class);
        $searchConfig = $config->get($this->searchConfig);
        if (isset($searchConfig->Records->sources)
            && $searchConfig->Records->sources
        ) {
            $sources = explode(',', $searchConfig->Records->sources);
            if (isset($_ENV['VUFIND_API_CALL']) && $_ENV['VUFIND_API_CALL']
                && isset($searchConfig->Records->apiExcludedSources)
            ) {
                $sources = array_diff(
                    $sources,
                    explode(',', $searchConfig->Records->apiExcludedSources)
                );
            }

            $sources = array_map(
                function ($input) {
                    return '"' . addcslashes($input, '"') . '"';
                },
                $sources
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
     * Add a boost query for boosting the geo filter
     *
     * @param EventInterface $event Event
     *
     * @return void
     */
    protected function addGeoFilterBoost(EventInterface $event)
    {
        $params = $event->getParam('params');
        if ($params) {
            $filters = $params->get('fq');
            if (null !== $filters) {
                foreach ($filters as $value) {
                    if (strncmp($value, '{!geofilt ', 10) == 0) {
                        // There may be multiple filters. Add bq for all.
                        $boosts = $params->get('bq');
                        if (null === $boosts) {
                            $boosts = [];
                        }
                        foreach (preg_split('/\s+OR\s+/', $value) as $filter) {
                            $bq = substr_replace(
                                $filter, 'score=recipDistance ', 10, 0
                            );
                            $boosts[] = $bq;
                            // Add a separate boost for the centroid
                            $bq = preg_replace(
                                '/sfield=\w+/', 'sfield=center_coords', $bq
                            );
                            $boosts[] = $bq;
                        }
                        $params->set('bq', $boosts);

                        // Set also default query type since bq only works with
                        // DisMax and eDisMax.
                        $params->set('defType', 'edismax');
                    }
                }
            }
            $sort = $params->get('sort');
            if (empty($sort) || $sort[0] == 'score desc') {
                $params->set('sort', 'score desc, first_indexed desc');
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
        $config = $this->serviceLocator->get(\VuFind\Config\PluginManager::class);
        $searchConfig = $config->get($this->searchConfig);
        if (isset($searchConfig->General->hide_component_parts)
            && $searchConfig->General->hide_component_parts
        ) {
            $params = $event->getParam('params');
            if ($params) {
                // Check that search is not for a known record id
                $query = $event->getParam('query');
                if (!$query || $query->getHandler() !== 'id') {
                    $params->add('fq', '-hidden_component_boolean:true');
                }
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
     * Change the online_boolean filter to online_str_mv filter or
     * free_online_boolean to free_online_str_mv filter if deduplication is enabled.
     * Combine that with source_available_str_mv if no building filter is active
     * or building_available_str_mv if building filter is active.
     *
     * @param EventInterface $event Event
     *
     * @return void
     */
    protected function handleAvailabilityFilters(EventInterface $event)
    {
        $config = $this->serviceLocator->get(\VuFind\Config\PluginManager::class);
        $searchConfig = $config->get($this->searchConfig);
        if (!empty($searchConfig->Records->sources)) {
            $params = $event->getParam('params');
            $filters = $params->get('fq');
            if (null !== $filters) {
                $sources = explode(',', $searchConfig->Records->sources);
                $sources = array_map(
                    function ($s) {
                        return "\"$s\"";
                    },
                    $sources
                );

                if (!empty($searchConfig->Records->deduplication)) {
                    foreach ($filters as $key => $value) {
                        if ($value === 'online_boolean:"1"'
                            || $value === 'free_online_boolean:"1"'
                        ) {
                            unset($filters[$key]);
                            $filter = $value == 'online_boolean:"1"'
                                ? 'online_str_mv' : 'free_online_str_mv';
                            $filter .= ':(' . implode(' OR ', $sources) . ')';
                            $filters[] = $filter;
                            $params->set('fq', $filters);
                            break;
                        }
                    }
                }

                foreach ($filters as $key => $value) {
                    if ($value === 'source_available_str_mv:*') {
                        $buildings = [];
                        $buildingRegExp
                            = '/\{!tag=building_filter\}building:\(building:(".*")/';
                        foreach ($filters as $value2) {
                            if (preg_match($buildingRegExp, $value2, $matches)) {
                                $buildings[] = $matches[1];
                            }
                        }
                        unset($filters[$key]);
                        if ($buildings) {
                            $filter = 'building_available_str_mv:('
                                . implode(' OR ', $buildings) . ')';
                        } else {
                            $filter = 'source_available_str_mv:('
                                . implode(' OR ', $sources) . ')';
                        }
                        $filters[] = $filter;
                        $params->set('fq', $filters);
                        break;
                    }
                }
            }
        }
    }
}
