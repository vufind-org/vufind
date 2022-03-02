<?php

/**
 * Abstract factory for SOLR backends.
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2013.
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
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
namespace VuFind\Search\Factory;

use Interop\Container\ContainerInterface;

use Laminas\Config\Config;
use Laminas\ServiceManager\Factory\FactoryInterface;
use VuFind\Search\Solr\CustomFilterListener;
use VuFind\Search\Solr\DeduplicationListener;
use VuFind\Search\Solr\DefaultParametersListener;
use VuFind\Search\Solr\FilterFieldConversionListener;
use VuFind\Search\Solr\HideFacetValueListener;
use VuFind\Search\Solr\HierarchicalFacetListener;
use VuFind\Search\Solr\InjectConditionalFilterListener;
use VuFind\Search\Solr\InjectHighlightingListener;
use VuFind\Search\Solr\InjectSpellingListener;
use VuFind\Search\Solr\MultiIndexListener;

use VuFind\Search\Solr\V3\ErrorListener as LegacyErrorListener;
use VuFind\Search\Solr\V4\ErrorListener;
use VuFindSearch\Backend\BackendInterface;
use VuFindSearch\Backend\Solr\Backend;
use VuFindSearch\Backend\Solr\Connector;
use VuFindSearch\Backend\Solr\HandlerMap;
use VuFindSearch\Backend\Solr\LuceneSyntaxHelper;

use VuFindSearch\Backend\Solr\QueryBuilder;

use VuFindSearch\Backend\Solr\SimilarBuilder;

/**
 * Abstract factory for SOLR backends.
 *
 * @category VuFind
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
abstract class AbstractSolrBackendFactory implements FactoryInterface
{
    /**
     * Logger.
     *
     * @var \Laminas\Log\LoggerInterface
     */
    protected $logger;

    /**
     * Superior service manager.
     *
     * @var ContainerInterface
     */
    protected $serviceLocator;

    /**
     * Primary configuration file identifier.
     *
     * @var string
     */
    protected $mainConfig = 'config';

    /**
     * Search configuration file identifier.
     *
     * @var string
     */
    protected $searchConfig;

    /**
     * Facet configuration file identifier.
     *
     * @var string
     */
    protected $facetConfig;

    /**
     * YAML searchspecs filename.
     *
     * @var string
     */
    protected $searchYaml;

    /**
     * VuFind configuration reader
     *
     * @var \VuFind\Config\PluginManager
     */
    protected $config;

    /**
     * Solr core name
     *
     * @var string
     */
    protected $solrCore = '';

    /**
     * Solr field used to store unique identifiers
     *
     * @var string
     */
    protected $uniqueKey = 'id';

    /**
     * Solr connector class
     *
     * @var string
     */
    protected $connectorClass = Connector::class;

    /**
     * Solr backend class
     *
     * @var string
     */
    protected $backendClass = Backend::class;

    /**
     * Constructor
     */
    public function __construct()
    {
    }

    /**
     * Create service
     *
     * @param ContainerInterface $sm      Service manager
     * @param string             $name    Requested service name
     * @param array              $options Extra options (unused)
     *
     * @return Backend
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function __invoke(ContainerInterface $sm, $name, array $options = null)
    {
        $this->serviceLocator = $sm;
        $this->config = $this->serviceLocator
            ->get(\VuFind\Config\PluginManager::class);
        if ($this->serviceLocator->has(\VuFind\Log\Logger::class)) {
            $this->logger = $this->serviceLocator->get(\VuFind\Log\Logger::class);
        }
        $connector = $this->createConnector();
        $backend   = $this->createBackend($connector);
        $backend->setIdentifier($name);
        $this->createListeners($backend);
        return $backend;
    }

    /**
     * Create the SOLR backend.
     *
     * @param Connector $connector Connector
     *
     * @return Backend
     */
    protected function createBackend(Connector $connector)
    {
        $backend = new $this->backendClass($connector);
        $config = $this->config->get($this->mainConfig);
        $pageSize = $config->Index->record_batch_size ?? 100;
        if ($pageSize > $config->Index->maxBooleanClauses ?? $pageSize) {
            $pageSize = $config->Index->maxBooleanClauses;
        }
        if ($pageSize > 0) {
            $backend->setPageSize($pageSize);
        }
        $backend->setQueryBuilder($this->createQueryBuilder());
        $backend->setSimilarBuilder($this->createSimilarBuilder());
        if ($this->logger) {
            $backend->setLogger($this->logger);
        }
        return $backend;
    }

    /**
     * Create listeners.
     *
     * @param Backend $backend Backend
     *
     * @return void
     */
    protected function createListeners(Backend $backend)
    {
        $events = $this->serviceLocator->get('SharedEventManager');

        // Load configurations:
        $config = $this->config->get($this->mainConfig);
        $search = $this->config->get($this->searchConfig);
        $facet = $this->config->get($this->facetConfig);

        // Attach default parameters listener first so that any other listeners can
        // override the parameters as necessary:
        if (!empty($search->General->default_parameters)) {
            $this->getDefaultParametersListener(
                $backend,
                $search->General->default_parameters->toArray()
            )->attach($events);
        }

        // Highlighting
        $this->getInjectHighlightingListener($backend, $search)->attach($events);

        // Conditional Filters
        if (isset($search->ConditionalHiddenFilters)
            && $search->ConditionalHiddenFilters->count() > 0
        ) {
            $this->getInjectConditionalFilterListener($search)->attach($events);
        }

        // Spellcheck
        if ($config->Spelling->enabled ?? true) {
            $dictionaries = ($config->Spelling->simple ?? false)
                ? ['basicSpell'] : ['default', 'basicSpell'];
            $spellingListener = new InjectSpellingListener($backend, $dictionaries);
            $spellingListener->attach($events);
        }

        // Apply field stripping if applicable:
        if (isset($search->StripFields) && isset($search->IndexShards)) {
            $strip = $search->StripFields->toArray();
            foreach ($strip as $k => $v) {
                $strip[$k] = array_map('trim', explode(',', $v));
            }
            $mindexListener = new MultiIndexListener(
                $backend,
                $search->IndexShards->toArray(),
                $strip,
                $this->loadSpecs()
            );
            $mindexListener->attach($events);
        }

        // Apply deduplication if applicable:
        if (isset($search->Records->deduplication)) {
            $this->getDeduplicationListener(
                $backend,
                $search->Records->deduplication
            )->attach($events);
        }

        // Attach hierarchical facet listener:
        $this->getHierarchicalFacetListener($backend)->attach($events);

        // Apply legacy filter conversion if necessary:
        $facets = $this->config->get($this->facetConfig);
        if (!empty($facets->LegacyFields)) {
            $filterFieldConversionListener = new FilterFieldConversionListener(
                $facets->LegacyFields->toArray()
            );
            $filterFieldConversionListener->attach($events);
        }

        // Attach custom filter listener if needed:
        if ($cfListener = $this->getCustomFilterListener($backend, $facets)) {
            $cfListener->attach($events);
        }
    
        // Attach hide facet value listener:
        if ($hfvListener = $this->getHideFacetValueListener($backend, $facet)) {
            $hfvListener->attach($events);
        }

        // Attach error listeners for Solr 3.x and Solr 4.x (for backward
        // compatibility with VuFind 1.x instances).
        $legacyErrorListener = new LegacyErrorListener($backend->getIdentifier());
        $legacyErrorListener->attach($events);
        $errorListener = new ErrorListener($backend->getIdentifier());
        $errorListener->attach($events);
    }

    /**
     * Get the Solr core.
     *
     * @return string
     */
    protected function getSolrCore()
    {
        return $this->solrCore;
    }

    /**
     * Get the Solr URL.
     *
     * @param string $config name of configuration file (null for default)
     *
     * @return string|array
     */
    protected function getSolrUrl($config = null)
    {
        $url = $this->config->get($config ?? $this->mainConfig)->Index->url;
        $core = $this->getSolrCore();
        if (is_object($url)) {
            return array_map(
                function ($value) use ($core) {
                    return "$value/$core";
                },
                $url->toArray()
            );
        }
        return "$url/$core";
    }

    /**
     * Get all hidden filter settings.
     *
     * @return array
     */
    protected function getHiddenFilters()
    {
        $search = $this->config->get($this->searchConfig);
        $hf = [];

        // Hidden filters
        if (isset($search->HiddenFilters)) {
            foreach ($search->HiddenFilters as $field => $value) {
                $hf[] = sprintf('%s:"%s"', $field, $value);
            }
        }

        // Raw hidden filters
        if (isset($search->RawHiddenFilters)) {
            foreach ($search->RawHiddenFilters as $filter) {
                $hf[] = $filter;
            }
        }

        return $hf;
    }

    /**
     * Create the SOLR connector.
     *
     * @return Connector
     */
    protected function createConnector()
    {
        $config = $this->config->get($this->mainConfig);
        $searchConfig = $this->config->get($this->searchConfig);
        $defaultFields = $searchConfig->General->default_record_fields ?? '*';

        $handlers = [
            'select' => [
                'fallback' => true,
                'defaults' => ['fl' => $defaultFields],
                'appends'  => ['fq' => []],
            ],
            'terms' => [
                'functions' => ['terms'],
            ],
        ];

        foreach ($this->getHiddenFilters() as $filter) {
            array_push($handlers['select']['appends']['fq'], $filter);
        }

        $httpService = $this->serviceLocator->get(\VuFindHttp\HttpService::class);
        $client = $httpService->createClient();

        $connector = new $this->connectorClass(
            $this->getSolrUrl(),
            new HandlerMap($handlers),
            $this->uniqueKey,
            $client
        );
        $connector->setTimeout($config->Index->timeout ?? 30);

        if ($this->logger) {
            $connector->setLogger($this->logger);
        }

        if (!empty($searchConfig->SearchCache->adapter)) {
            $cacheConfig = $searchConfig->SearchCache->toArray();
            $options = $cacheConfig['options'] ?? [];
            if (empty($options['namespace'])) {
                $options['namespace'] = 'Index';
            }
            if (empty($options['ttl'])) {
                $options['ttl'] = 300;
            }
            $settings = [
                'name' => $cacheConfig['adapter'],
                'options' => $options,
            ];
            $cache = $this->serviceLocator
                ->get(\Laminas\Cache\Service\StorageAdapterFactory::class)
                ->createFromArrayConfiguration($settings);
            $connector->setCache($cache);
        }
        return $connector;
    }

    /**
     * Create the query builder.
     *
     * @return QueryBuilder
     */
    protected function createQueryBuilder()
    {
        $specs   = $this->loadSpecs();
        $config = $this->config->get($this->mainConfig);
        $defaultDismax = $config->Index->default_dismax_handler ?? 'dismax';
        $builder = new QueryBuilder($specs, $defaultDismax);

        // Configure builder:
        $search = $this->config->get($this->searchConfig);
        $caseSensitiveBooleans
            = $search->General->case_sensitive_bools ?? true;
        $caseSensitiveRanges
            = $search->General->case_sensitive_ranges ?? true;
        $helper = new LuceneSyntaxHelper(
            $caseSensitiveBooleans,
            $caseSensitiveRanges
        );
        $builder->setLuceneHelper($helper);

        return $builder;
    }

    /**
     * Create the similar records query builder.
     *
     * @return SimilarBuilder
     */
    protected function createSimilarBuilder()
    {
        return new SimilarBuilder(
            $this->config->get($this->searchConfig),
            $this->uniqueKey
        );
    }

    /**
     * Load the search specs.
     *
     * @return array
     */
    protected function loadSpecs()
    {
        return $this->serviceLocator->get(\VuFind\Config\SearchSpecsReader::class)
            ->get($this->searchYaml);
    }

    /**
     * Get a deduplication listener for the backend
     *
     * @param Backend $backend Search backend
     * @param bool    $enabled Whether deduplication is enabled
     *
     * @return DeduplicationListener
     */
    protected function getDeduplicationListener(Backend $backend, $enabled)
    {
        return new DeduplicationListener(
            $backend,
            $this->serviceLocator,
            $this->searchConfig,
            'datasources',
            $enabled
        );
    }

    /**
     * Get a custom filter listener for the backend (or null if not needed).
     *
     * @param BackendInterface $backend Search backend
     * @param Config           $facet   Configuration of facets
     *
     * @return mixed null|CustomFilterListener
     */
    protected function getCustomFilterListener(
        BackendInterface $backend,
        Config $facet
    ) {
        $customField = $facet->CustomFilters->custom_filter_field ?? '__custom__';
        $normal = $inverted = [];
        foreach ($facet->CustomFilters->translated_filters as $value => $filter) {
            $normal[$customField . ':"' . $value . '"'] = $filter;
        }
        foreach ($facet->CustomFilters->inverted_filters as $value => $filter) {
            $inverted[$customField . ':"' . $value . '"'] = $filter;
        }
        return empty($normal) && empty($inverted)
            ? null
            : new CustomFilterListener($backend, $normal, $inverted);
    }

    /**
     * Get a hide facet value listener for the backend
     *
     * @param BackendInterface $backend Search backend
     * @param Config           $facet   Configuration of facets
     *
     * @return mixed null|HideFacetValueListener
     */
    protected function getHideFacetValueListener(
        BackendInterface $backend,
        Config $facet
    ) {
        $hideFacetValue = isset($facet->HideFacetValue)
            ? $facet->HideFacetValue->toArray() : [];
        $showFacetValue = isset($facet->ShowFacetValue)
            ? $facet->ShowFacetValue->toArray() : [];
        if (empty($hideFacetValue) && empty($showFacetValue)) {
            return null;
        }
        return new HideFacetValueListener(
            $backend,
            $hideFacetValue,
            $showFacetValue
        );
    }

    /**
     * Get a hierarchical facet listener for the backend
     *
     * @param BackendInterface $backend Search backend
     *
     * @return HierarchicalFacetListener
     */
    protected function getHierarchicalFacetListener(BackendInterface $backend)
    {
        return new HierarchicalFacetListener(
            $backend,
            $this->serviceLocator,
            $this->facetConfig
        );
    }

    /**
     * Get a highlighting listener for the backend
     *
     * @param BackendInterface $backend Search backend
     * @param Config           $search  Search configuration
     *
     * @return InjectHighlightingListener
     */
    protected function getInjectHighlightingListener(
        BackendInterface $backend,
        Config $search
    ) {
        $fl = $search->General->highlighting_fields ?? '*';
        return new InjectHighlightingListener($backend, $fl);
    }

    /**
     * Get a Conditional Filter Listener
     *
     * @param Config $search Search configuration
     *
     * @return InjectConditionalFilterListener
     */
    protected function getInjectConditionalFilterListener(Config $search)
    {
        $listener = new InjectConditionalFilterListener(
            $search->ConditionalHiddenFilters->toArray()
        );
        $listener->setAuthorizationService(
            $this->serviceLocator
                ->get(\LmcRbacMvc\Service\AuthorizationService::class)
        );
        return $listener;
    }

    /**
     * Get a default parameters listener for the backend
     *
     * @param Backend $backend Search backend
     * @param array   $params  Default parameters
     *
     * @return DeduplicationListener
     */
    protected function getDefaultParametersListener(Backend $backend, array $params)
    {
        return new DefaultParametersListener($backend, $params);
    }
}
