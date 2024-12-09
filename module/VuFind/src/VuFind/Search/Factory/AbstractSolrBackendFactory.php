<?php

/**
 * Abstract factory for SOLR backends.
 *
 * PHP version 8
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

use Laminas\Config\Config;
use Psr\Container\ContainerInterface;
use VuFind\Search\Solr\CustomFilterListener;
use VuFind\Search\Solr\DeduplicationListener;
use VuFind\Search\Solr\DefaultParametersListener;
use VuFind\Search\Solr\FilterFieldConversionListener;
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
use VuFindSearch\Backend\Solr\Response\Json\RecordCollection;
use VuFindSearch\Backend\Solr\Response\Json\RecordCollectionFactory;
use VuFindSearch\Backend\Solr\SimilarBuilder;
use VuFindSearch\Response\RecordCollectionFactoryInterface;

use function count;
use function is_object;
use function sprintf;

/**
 * Abstract factory for SOLR backends.
 *
 * @category VuFind
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
abstract class AbstractSolrBackendFactory extends AbstractBackendFactory
{
    use SharedListenersTrait;

    /**
     * Logger.
     *
     * @var \Laminas\Log\LoggerInterface
     */
    protected $logger;

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
     * Name of index configuration setting to use to retrieve Solr index name
     * (core or collection).
     *
     * @var string
     */
    protected $indexNameSetting = 'default_core';

    /**
     * Solr index name (used as default if $this->indexNameSetting is unset in
     * the config).
     *
     * @var string
     */
    protected $defaultIndexName = '';

    /**
     * When looking up the Solr index name config setting, should we allow fallback
     * into the main configuration (true), or limit ourselves to the search
     * config (false)?
     *
     * @var bool
     */
    protected $allowFallbackForIndexName = false;

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
     * Record collection class for RecordCollectionFactory
     *
     * @var string
     */
    protected $recordCollectionClass = RecordCollection::class;

    /**
     * Record collection factory class
     *
     * @var string
     */
    protected $recordCollectionFactoryClass = RecordCollectionFactory::class;

    /**
     * Merged index configuration
     *
     * @var ?array
     */
    protected $mergedIndexConfig = null;

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
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
        $this->setup($sm);
        $this->config = $this->getService(\VuFind\Config\PluginManager::class);
        if ($this->serviceLocator->has(\VuFind\Log\Logger::class)) {
            $this->logger = $this->getService(\VuFind\Log\Logger::class);
        }
        $connector = $this->createConnector();
        $backend   = $this->createBackend($connector);
        $backend->setIdentifier($name);
        $this->createListeners($backend);
        return $backend;
    }

    /**
     * Return an ordered array of configurations to check for index configurations.
     *
     * @return string[]
     */
    protected function getPrioritizedConfigsForIndexSettings(): array
    {
        return array_unique([$this->searchConfig, $this->mainConfig]);
    }

    /**
     * Merge together the Index sections of all eligible configuration files and
     * return the result as an array.
     *
     * @return array
     */
    protected function getMergedIndexConfig(): array
    {
        if (null === $this->mergedIndexConfig) {
            $this->mergedIndexConfig = [];
            foreach ($this->getPrioritizedConfigsForIndexSettings() as $configName) {
                $config = $this->config->get($configName);
                $this->mergedIndexConfig += isset($config->Index)
                    ? $config->Index->toArray() : [];
            }
        }
        return $this->mergedIndexConfig;
    }

    /**
     * Get the Index section of the highest-priority configuration file (for use
     * in cases where fallback is not desired).
     *
     * @return array
     */
    protected function getFlatIndexConfig(): array
    {
        $configList = $this->getPrioritizedConfigsForIndexSettings();
        $configObj = $this->config->get($configList[0]);
        return isset($configObj->Index)
            ? $configObj->Index->toArray() : [];
    }

    /**
     * Get an index-related configuration setting.
     *
     * @param string $setting  Name of setting
     * @param mixed  $default  Default value if unset
     * @param bool   $fallback Should we fall back to main config if the
     * setting is absent from the search config file?
     *
     * @return mixed
     */
    protected function getIndexConfig(
        string $setting,
        $default = null,
        bool $fallback = true
    ) {
        $config = $fallback
            ? $this->getMergedIndexConfig() : $this->getFlatIndexConfig();
        return $config[$setting] ?? $default;
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
        $pageSize = $this->getIndexConfig('record_batch_size', 100);
        $maxClauses = $this->getIndexConfig('maxBooleanClauses', $pageSize);
        if ($pageSize > 0 && $maxClauses > 0) {
            $backend->setPageSize(min($pageSize, $maxClauses));
        }
        $backend->setQueryBuilder($this->createQueryBuilder());
        $backend->setSimilarBuilder($this->createSimilarBuilder());
        if ($this->logger) {
            $backend->setLogger($this->logger);
        }
        $backend->setRecordCollectionFactory($this->createRecordCollectionFactory());
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
        $events = $this->getService('SharedEventManager');

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
        if (
            isset($search->ConditionalHiddenFilters)
            && $search->ConditionalHiddenFilters->count() > 0
        ) {
            $this->getInjectConditionalFilterListener($backend, $search)->attach($events);
        }

        // Spellcheck
        if ($config->Spelling->enabled ?? true) {
            $dictionaries = $config->Spelling->dictionaries?->toArray() ?? [];
            if (empty($dictionaries)) {
                // Respect the deprecated 'simple' configuration setting.
                $dictionaries = ($config->Spelling->simple ?? false)
                    ? ['basicSpell'] : ['default', 'basicSpell'];
            }
            $spellingListener = new InjectSpellingListener(
                $backend,
                $dictionaries,
                $this->logger
            );
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
     * Get the name of the Solr index (core or collection).
     *
     * @return string
     */
    protected function getIndexName()
    {
        return $this->getIndexConfig(
            $this->indexNameSetting,
            $this->defaultIndexName,
            $this->allowFallbackForIndexName
        );
    }

    /**
     * Get the Solr base URL(s) (without the path to the specific index)
     *
     * @return string[]
     */
    protected function getSolrBaseUrls(): array
    {
        $urls = $this->getIndexConfig('url', []);
        return is_object($urls) ? $urls->toArray() : (array)$urls;
    }

    /**
     * Get the full Solr URL(s) (including index path part).
     *
     * @return string|array
     */
    protected function getSolrUrl()
    {
        $indexName = $this->getIndexName();
        $urls = array_map(
            function ($value) use ($indexName) {
                return "$value/$indexName";
            },
            $this->getSolrBaseUrls()
        );
        return count($urls) === 1 ? $urls[0] : $urls;
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
        $timeout = $this->getIndexConfig('timeout', 30);
        $searchConfig = $this->config->get($this->searchConfig);
        $defaultFields = $searchConfig->General->default_record_fields ?? '*';

        if (($searchConfig->Explain->enabled ?? false) && !str_contains($defaultFields, 'score')) {
            $defaultFields .= ',score';
        }

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

        $connector = new $this->connectorClass(
            $this->getSolrUrl(),
            new HandlerMap($handlers),
            function (string $url) use ($timeout) {
                return $this->createHttpClient(
                    $timeout,
                    $this->getHttpOptions($url),
                    $url
                );
            },
            $this->uniqueKey
        );

        if ($this->logger) {
            $connector->setLogger($this->logger);
        }

        if ($cache = $this->createConnectorCache($searchConfig)) {
            $connector->setCache($cache);
        }

        return $connector;
    }

    /**
     * Get HTTP options for the client
     *
     * @param string $url URL being requested
     *
     * @return array
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function getHttpOptions(string $url): array
    {
        return [];
    }

    /**
     * Create the query builder.
     *
     * @return QueryBuilder
     */
    protected function createQueryBuilder()
    {
        $specs   = $this->loadSpecs();
        $defaultDismax = $this->getIndexConfig('default_dismax_handler', 'dismax');
        $builder = new QueryBuilder($specs, $defaultDismax);

        // Configure builder:
        $builder->setLuceneHelper($this->createLuceneSyntaxHelper());

        return $builder;
    }

    /**
     * Create Lucene syntax helper.
     *
     * @return LuceneSyntaxHelper
     */
    protected function createLuceneSyntaxHelper()
    {
        $search = $this->config->get($this->searchConfig);
        $caseSensitiveBooleans = $search->General->case_sensitive_bools ?? true;
        $caseSensitiveRanges = $search->General->case_sensitive_ranges ?? true;
        return new LuceneSyntaxHelper($caseSensitiveBooleans, $caseSensitiveRanges);
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
     * Create the record collection factory.
     *
     * @return RecordCollectionFactoryInterface
     */
    protected function createRecordCollectionFactory(): RecordCollectionFactoryInterface
    {
        return new $this->recordCollectionFactoryClass(
            $this->getCreateRecordCallback(),
            $this->recordCollectionClass
        );
    }

    /**
     * Get the callback for creating a record.
     *
     * Returns a callable or null to use RecordCollectionFactory's default method.
     *
     * @return callable|null
     */
    protected function getCreateRecordCallback(): ?callable
    {
        return null;
    }

    /**
     * Load the search specs.
     *
     * @return array
     */
    protected function loadSpecs()
    {
        return $this->getService(\VuFind\Config\SearchSpecsReader::class)->get($this->searchYaml);
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
        $customField = $facet->CustomFilters->custom_filter_field ?? 'vufind';
        $normal = $inverted = [];

        foreach ($facet->CustomFilters->translated_filters ?? [] as $key => $val) {
            $normal[$customField . ':"' . $key . '"'] = $val;
        }
        foreach ($facet->CustomFilters->inverted_filters ?? [] as $key => $val) {
            $inverted[$customField . ':"' . $key . '"'] = $val;
        }
        return empty($normal) && empty($inverted)
            ? null
            : new CustomFilterListener($backend, $normal, $inverted);
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
        $extras = $search->General->extra_hl_params ?? [];
        return new InjectHighlightingListener($backend, $fl, $extras);
    }

    /**
     * Get a Conditional Filter Listener
     *
     * @param BackendInterface $backend Search backend
     * @param Config           $search  Search configuration
     *
     * @return InjectConditionalFilterListener
     */
    protected function getInjectConditionalFilterListener(BackendInterface $backend, Config $search)
    {
        $listener = new InjectConditionalFilterListener(
            $backend,
            $search->ConditionalHiddenFilters->toArray()
        );
        $listener->setAuthorizationService(
            $this->getService(\LmcRbacMvc\Service\AuthorizationService::class)
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
