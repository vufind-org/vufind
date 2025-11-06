<?php

/**
 * Finna Solr extensions listener.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2013-2024.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
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
use VuFindSearch\Query\Query;

use function count;
use function in_array;
use function is_array;
use function sprintf;

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
     * Terms filter prefix for a filter (%s is the field name)
     *
     * @var string
     */
    public const TERMS_FILTER_PREFIX = "{!terms f=%s separator=\"\u{001f}\" method=docValuesTermsFilter}";

    /**
     * Terms filter prefix for a source filter
     *
     * @var string
     */
    public const TERMS_FILTER_PREFIX_SOURCE
        = "{!terms f=source_str_mv separator=\"\u{001f}\" method=docValuesTermsFilter}";

    /**
     * Backend identifier.
     *
     * @var string
     */
    protected $backendId;

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
     * @param string                  $backendId        Search backend identifier
     * @param ServiceLocatorInterface $serviceLocator   Service locator
     * @param string                  $searchConfig     Search config file id
     * @param string                  $facetConfig      Facet config file id
     * @param string                  $dataSourceConfig Data source file id
     *
     * @return void
     */
    public function __construct(
        string $backendId,
        ServiceLocatorInterface $serviceLocator,
        $searchConfig,
        $facetConfig,
        $dataSourceConfig = 'datasources'
    ) {
        $this->backendId = $backendId;
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
     * Customize Solr request.
     *
     * @param EventInterface $event Event
     *
     * @return EventInterface
     */
    public function onSearchPre(EventInterface $event)
    {
        $command = $event->getParam('command');
        if ($command->getTargetIdentifier() === $this->backendId) {
            $this->addDataSourceFilter($event);
            $context = $command->getContext();
            if (in_array($context, ['search', 'getids', 'workExpressions'])) {
                $this->handleHiddenComponentPartFilter($event);
                $this->handleAvailabilityFilters($event);
            }
            if ('search' === $context) {
                $this->addGeoFilterBoost($event);
                $this->addAuthorIdAlternatives($event);
            }
        }
        return $event;
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
        $command = $event->getParam('command');
        if ($command->getTargetIdentifier() === $this->backendId) {
            if ($command->getContext() == 'search') {
                $this->displayDebugInfo($event);
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
        $command = $event->getParam('command');
        $params = $command->getSearchParameters();
        // Don't add the filter if requested so (e.g. AIPA encapsulated records) or we're fetching a single record (to
        // be able to link to encapsulated records):
        if ($params->get('finna.ignore_source_filter') || $command->getContext() === 'retrieve') {
            $params->remove('finna.ignore_source_filter');
            return;
        }
        if ($recordSources = $this->getActiveSources($event)) {
            $params->add('fq', static::TERMS_FILTER_PREFIX_SOURCE . implode("\u{001f}", $recordSources));
        }
    }

    /**
     * Get a list of active sources
     *
     * @param EventInterface $event Event
     *
     * @return array
     */
    protected function getActiveSources(EventInterface $event): array
    {
        $sources = null;
        // Check for a filter in params first:
        $params = $event->getParam('command')->getSearchParameters();
        if ($fq = $params->get('fq')) {
            foreach ($fq as $key => $filter) {
                $parts = explode(':', $filter);
                if ('finna.sources' === $parts[0]) {
                    if ($sources = $parts[1] ?? null) {
                        $sources = trim($sources, '"');
                    }
                    unset($fq[$key]);
                    $params->set('fq', $fq);
                    break;
                }
            }
        }
        // Not in params, check config:
        if (null === $sources) {
            $configManager = $this->serviceLocator->get(\VuFind\Config\ConfigManagerInterface::class);
            $searchConfig = $configManager->getConfigObject($this->searchConfig);
            $sources = $searchConfig->Records->sources ?? null;
        }

        if (!$sources) {
            return [];
        }

        $sources = explode(',', $sources);

        // Finally, check for an API exclusion list:
        if (
            getenv('VUFIND_API_CALL')
            && isset($searchConfig->Records->apiExcludedSources)
        ) {
            $sources = array_diff(
                $sources,
                explode(',', $searchConfig->Records->apiExcludedSources)
            );
        }

        return $sources;
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
        $params = $event->getParam('command')->getSearchParameters();
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
                                $filter,
                                'score=recipDistance ',
                                10,
                                0
                            );
                            $boosts[] = $bq;
                            // Add a separate boost for the centroid
                            $bq = preg_replace(
                                '/sfield=\w+/',
                                'sfield=center_coords',
                                $bq
                            );
                            $boosts[] = $bq;
                        }
                        $params->set('bq', $boosts);
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
     * Add alternative authority IDs to authority search
     *
     * @param EventInterface $event Event
     *
     * @return void
     */
    protected function addAuthorIdAlternatives(EventInterface $event)
    {
        $params = $event->getParam('command')->getSearchParameters();
        if ($params) {
            $filters = $params->get('fq');
            if (null !== $filters) {
                $loader = null;
                $helper = null;
                $newFilters = [];
                foreach ($filters as $filter) {
                    $parts = explode(':', $filter, 2);
                    $field = $parts[0];
                    $value = $parts[1] ?? null;
                    if (AuthorityHelper::AUTHOR2_ID_FACET === $field && $value) {
                        $loader ??= $this->serviceLocator->get(\VuFind\Record\Loader::class);
                        $helper ??= $this->serviceLocator->get(\Finna\Search\Solr\AuthorityHelper::class);
                        $record = $loader->load(trim($value, '"'), 'SolrAuth', true);
                        $identifiers = $helper->getIdentifiersForAuthority($record);
                        if (count($identifiers) > 1) {
                            $newFilters[] = $helper->getRecordsByAuthorityQuery(
                                $identifiers,
                                AuthorityHelper::AUTHOR2_ID_FACET
                            );
                            continue;
                        }
                    }
                    $newFilters[] = $filter;
                }
                $params->set('fq', $newFilters);
            }
        }
    }

    /**
     * Handle hidden component part filter (finna.include_hidden_parts) per search config.
     *
     * @param EventInterface $event Event
     *
     * @return void
     */
    protected function handleHiddenComponentPartFilter(EventInterface $event)
    {
        $hideHiddenComponentsPart = null;
        $command = $event->getParam('command');
        $params = $command->getSearchParameters();
        if (!$params) {
            return;
        }

        // Remove finna.include_hidden_parts from any facet fields:
        if ($facetFields = $params->get('facet.field')) {
            $newFields = [];
            foreach ($facetFields as $field) {
                if (strstr($field, 'finna.include_hidden_parts') === false) {
                    $newFields[] = $field;
                }
            }
            $params->set('facet.field', $newFields);
        }

        // Check that search is not for a known record id
        $query = method_exists($command, 'getQuery') ? $command->getQuery() : null;
        if ($query instanceof Query && $query->getHandler() === 'id') {
            return;
        }

        if ($fq = $params->get('fq')) {
            // Check for a filter parameter:
            $optionMappings = [
                'finna.include_hidden_parts:"1"' => false,
                'finna.include_hidden_parts:"0"' => true,
            ];
            foreach ($optionMappings as $filter => $value) {
                if (false !== ($key = array_search($filter, $fq))) {
                    $hideHiddenComponentsPart = $value;
                    unset($fq[$key]);
                    $params->set('fq', $fq);
                }
            }
        }

        // Check for config:
        $configManager = $this->serviceLocator->get(\VuFind\Config\ConfigManagerInterface::class);
        $searchConfig = $configManager->getConfigObject($this->searchConfig);
        if (null === $hideHiddenComponentsPart) {
            $hideHiddenComponentsPart = $searchConfig->General->hide_component_parts ?? false;
        }

        // Add the parameter if needed:
        if ($hideHiddenComponentsPart) {
            if ($componentPartFilter = $searchConfig->General->displayable_component_part_filter ?? null) {
                $params->add(
                    'fq',
                    $componentPartFilter . ' OR (*:* -hidden_component_boolean:true)'
                );
            } else {
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
        $command = $event->getParam('command');
        $params = $command->getSearchParameters();
        if (!$params->get('debugQuery')) {
            return;
        }
        $collection = $command->getResult();
        $debugInfo = $collection->getDebugInformation();
        echo "<!--\n";
        echo 'Raw query string: ' . $debugInfo['rawquerystring'] . "\n\n";
        echo 'Query string: ' . $debugInfo['querystring'] . "\n\n";
        echo 'Parsed query: ' . var_export($debugInfo['parsedquery'], true) . "\n\n";
        echo 'Query parser: ' . $debugInfo['QParser'] . "\n\n";
        if (!empty($debugInfo['altquerystring'])) {
            echo 'Alt query string: ' . $debugInfo['altquerystring'] . "\n\n";
        }
        echo "Boost functions:\n";
        if (!empty($debugInfo['boostfuncs'])) {
            echo '  ' . implode("\n  ", $debugInfo['boostfuncs']);
        }
        echo "\n\n";
        echo "Filter queries:\n";
        if (!empty($debugInfo['filter_queries'])) {
            echo '  ' . implode("\n  ", $debugInfo['filter_queries']);
        }
        echo "\n\n";
        echo "Parsed filter queries:\n";
        if (!empty($debugInfo['parsed_filter_queries'])) {
            echo '  ' . implode("\n  ", $debugInfo['parsed_filter_queries']);
        }
        echo "\n\n";
        echo "Timing:\n";
        echo '  Total: ' . $debugInfo['timing']['time'] . "\n";
        echo "  Prepare:\n";
        foreach ($debugInfo['timing']['prepare'] ?? [] as $key => $value) {
            echo "    $key: ";
            echo is_array($value) ? $value['time'] : $value;
            echo "\n";
        }
        echo "  Process:\n";
        foreach ($debugInfo['timing']['process'] ?? [] as $key => $value) {
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
     * Process availability checkbox filters
     *
     * Changes the following filters if deduplication is enabled:
     *
     *  - online_boolean            to online_str_mv
     *  - free_online_boolean       to free_online_str_mv
     *  - hires_image_boolean       to hires_image_str_mv
     *  - source_available_str_mv:* to source_available_str_mv:(...) or
     *                                 building_available_str_mv:(...) if a building
     *                                 filter is active
     *
     * @param EventInterface $event Event
     *
     * @return void
     */
    protected function handleAvailabilityFilters(EventInterface $event)
    {
        $configManager = $this->serviceLocator->get(\VuFind\Config\ConfigManagerInterface::class);
        $searchConfig = $configManager->getConfigObject($this->searchConfig);
        if (!empty($searchConfig->Records->sources)) {
            $params = $event->getParam('command')->getSearchParameters();
            $filters = $params->get('fq');
            if (null !== $filters) {
                $sources = explode(',', $searchConfig->Records->sources);

                if (!empty($searchConfig->Records->deduplication)) {
                    $prefixes = [
                        'online', 'free_online', 'hires_images',
                    ];
                    foreach ($prefixes as $prefix) {
                        foreach ($filters as $key => $value) {
                            if ($value === $prefix . '_boolean:"1"') {
                                unset($filters[$key]);
                                $filters[] = sprintf(
                                    static::TERMS_FILTER_PREFIX,
                                    $prefix . '_str_mv'
                                ) . implode("\u{001f}", $sources);
                                $params->set('fq', $filters);
                                break;
                            }
                        }
                    }
                }

                foreach ($filters as $key => $value) {
                    if ($value === 'source_available_str_mv:*') {
                        $buildings = [];
                        $buildingRegExp = '/\{!tag=building_filter\}building:\(building:"(.*)"/';
                        foreach ($filters as $value2) {
                            if (preg_match($buildingRegExp, $value2, $matches)) {
                                $buildings[] = $matches[1];
                            }
                        }
                        unset($filters[$key]);
                        if ($buildings) {
                            $filter = sprintf(
                                static::TERMS_FILTER_PREFIX,
                                'building_available_str_mv'
                            ) . implode("\u{001f}", $buildings);
                        } else {
                            $filter = sprintf(
                                static::TERMS_FILTER_PREFIX,
                                'source_available_str_mv'
                            ) . implode("\u{001f}", $sources);
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
