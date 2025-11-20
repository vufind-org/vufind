<?php

/**
 * Channel loader
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2016.
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
 * @package  Channels
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\ChannelProvider;

use VuFind\Cache\Manager as CacheManager;
use VuFind\ChannelProvider\PluginManager as ChannelManager;
use VuFind\Http\PhpEnvironment\Request as HttpRequest;
use VuFind\Record\Loader as RecordLoader;
use VuFind\Search\Base\Results;
use VuFind\Search\SearchRunner;

use function count;
use function in_array;
use function intval;

/**
 * Channel loader
 *
 * @category VuFind
 * @package  Channels
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class ChannelLoader
{
    use BatchTrait;

    /**
     * Constructor
     *
     * @param array          $config         Channels configuration
     * @param CacheManager   $cacheManager   Cache manager
     * @param ChannelManager $channelManager Channel manager
     * @param SearchRunner   $searchRunner   Search runner
     * @param RecordLoader   $recordLoader   Record loader
     * @param HttpRequest    $request        HTTP request
     * @param string         $locale         Current locale (used for caching)
     */
    public function __construct(
        protected array $config,
        protected CacheManager $cacheManager,
        protected ChannelManager $channelManager,
        protected SearchRunner $searchRunner,
        protected RecordLoader $recordLoader,
        protected HttpRequest $request,
        protected string $locale = ''
    ) {
    }

    /**
     * Add configuration values needed by the templates to the view context
     *
     * @param array $context String-keyed map of values for the View
     *
     * @return array
     */
    protected function addConfigToContext($context)
    {
        $channels = [];
        $relatedTokens = [];
        for ($i = 0; $i < count($context['channels'] ?? []); $i++) {
            $current = $context['channels'][$i];
            if (isset($current['contents'])) {
                [, $configSection] = explode(':', $context['channels'][$i]['providerId'] . ':');
                $config = $this->config[$configSection] ?? [];

                // Calculate batch size
                $itemsPerRow = $config['itemsPerRow'] ?? 6;
                $rowsPerPage = $config['rowsPerPage'] ?? 1;
                $pageSize = $itemsPerRow * $rowsPerPage;
                $batchSize = self::calcBatchSize($itemsPerRow, $rowsPerPage);

                // Pass to view
                $current['config'] = [
                    'batchSize' => $batchSize,
                    'pageSize' => $pageSize,
                    'rowSize' => $itemsPerRow,
                ];
                $channels[] = $current;
            } elseif (isset($current['token'])) {
                // Add token to related tokens map
                $group = $current['groupId'] ?? $current['providerId'];
                if (!isset($relatedTokens[$group])) {
                    $relatedTokens[$group] = [];
                }
                $relatedTokens[$group][] = $current;
            }
        }

        $context['channels'] = $channels;
        $context['relatedTokens'] = $relatedTokens;

        return $context;
    }

    /**
     * Get a search results object configured by channel providers.
     *
     * @param array  $searchRequest Search request parameters
     * @param array  $providers     Array of channel providers
     * @param string $source        Backend to use
     *
     * @return Results
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function performChannelSearch($searchRequest, $providers, $source)
    {
        // Perform search and configure providers:
        $callback = function ($runner, $params) use ($providers): void {
            foreach ($providers as $provider) {
                $provider->configureSearchParams($params, $this->request);
            }
        };
        return $this->searchRunner->run($searchRequest, $source, $callback);
    }

    /**
     * Get channel details using an array of providers and a populated search
     * results object.
     *
     * @param array   $providers Array of channel providers
     * @param Results $results   Search results object from performChannelSearch
     * @param string  $token     Optional channel token
     *
     * @return array
     */
    protected function getChannelsFromResults($providers, Results $results, $token)
    {
        // Collect details:
        $channels = [];
        foreach ($providers as $provider) {
            $channels = array_merge(
                $channels,
                $provider->getFromSearch($results, $token)
            );
        }
        return $channels;
    }

    /**
     * Get an array of channel providers matching the provided IDs (or just one,
     * if the channelProvider GET parameter is set).
     *
     * @param string $source        Search backend ID
     * @param string $configSection Configuration section to load ID list from
     * @param string $activeId      Currently selected channel ID (if any; used
     * when making an AJAX request for a single additional channel)
     *
     * @return array
     */
    protected function getChannelProviders($source, $configSection, $activeId = null)
    {
        $providerIds = $this->config["source.$source"][$configSection] ?? [];
        $finalIds = (!empty($activeId) && in_array($activeId, $providerIds))
            ? [$activeId] : $providerIds;
        return array_map([$this, 'getChannelProvider'], $finalIds);
    }

    /**
     * Convenience method to retrieve a channel provider.
     *
     * @param string $providerId Channel provider name and optional config
     * (colon-delimited)
     *
     * @return ChannelProviderInterface
     */
    protected function getChannelProvider($providerId)
    {
        // The provider ID consists of a service name and an optional config
        // section -- break out the relevant parts:
        [$serviceName, $configSection] = explode(':', $providerId . ':');

        // Load configuration, using default value if necessary:
        if (empty($configSection)) {
            $configSection = "provider.$serviceName";
        }
        $options = $this->config[$configSection] ?? [];

        // Load the service, and configure appropriately:
        $provider = $this->channelManager->get($serviceName);
        $provider->setProviderId($providerId);
        $provider->setOptions($options);
        return $provider;
    }

    /**
     * Generates static front page of channels.
     *
     * @param string $token         Channel token (optional, used for AJAX fetching)
     * @param string $activeChannel Channel being requested (optional, used w/ token)
     * @param string $activeSource  Search backend to use (null to use configured
     * default).
     *
     * @return array
     */
    public function getHomeContext(
        $token = null,
        $activeChannel = null,
        $activeSource = null
    ) {
        // Load appropriate channel objects:
        $defaultSource = $this->config['General']['default_home_source']
            ?? DEFAULT_SEARCH_BACKEND;
        $source = $activeSource ?? $defaultSource;
        $providers = $this->getChannelProviders($source, 'home', $activeChannel);

        // Set up the cache, if appropriate:
        if ($this->config['General']['cache_home_channels'] ?? false) {
            $providerIds = array_map('get_class', $providers);
            $parts = [implode(',', $providerIds), $source, $token, $this->locale];
            $cacheKey = md5(implode('-', $parts));
            $cache = $this->cacheManager->getCache('object', 'homeChannels');
        } else {
            $cacheKey = false;
            $cache = null;
        }

        // Only use the cache for the first page of results:
        $page = intval($this->request->getQuery('page', 1));
        $useCache = ($cacheKey && $page === 1);

        // Fetch channel data from cache, or populate cache if necessary:
        if (!($channels = $useCache ? $cache->getItem($cacheKey) : false)) {
            $searchParams = [];
            if (isset($this->config['General']['default_home_search'])) {
                $searchParams['lookfor'] = $this->config['General']['default_home_search'];
            }
            $results = $this->performChannelSearch($searchParams, $providers, $source);
            $channels = $this->getChannelsFromResults($providers, $results, $token);
            if ($useCache) {
                $cache->setItem($cacheKey, $channels);
            }
        }

        // Return context array:
        return $this->addConfigToContext(compact('token', 'channels'));
    }

    /**
     * Generates channels for a record.
     *
     * @param string $recordId       Record ID to load
     * @param string $token          Channel token (optional, used for AJAX fetching)
     * @param string $activeChannel  Channel being requested (optional, used w/ token)
     * @param string $source         Search backend to use
     * @param array  $configSections Prioritized list of configuration sections to check
     *
     * @return array
     */
    public function getRecordContext(
        $recordId,
        $token = null,
        $activeChannel = null,
        $source = DEFAULT_SEARCH_BACKEND,
        array $configSections = ['record']
    ) {
        // Load record:
        $driver = $this->recordLoader->load($recordId, $source);

        // Load appropriate channel objects:
        $providers = [];
        foreach ($configSections as $section) {
            $providers = $this->getChannelProviders($source, $section, $activeChannel);
            if (!empty($providers)) {
                break;
            }
        }

        // Collect details:
        $channels = [];
        foreach ($providers as $provider) {
            $channels = array_merge(
                $channels,
                $provider->getFromRecord($driver, $token)
            );
        }

        // Return context array:
        return $this->addConfigToContext(compact('driver', 'channels', 'token'));
    }

    /**
     * Generates channels for a search.
     *
     * @param array  $searchRequest Request parameters
     * @param string $token         Channel token (optional, used for AJAX fetching)
     * @param string $activeChannel Channel being requested (optional, used w/ token)
     * @param string $source        Search backend to use
     *
     * @return array
     */
    public function getSearchContext(
        $searchRequest = [],
        $token = null,
        $activeChannel = null,
        $source = DEFAULT_SEARCH_BACKEND
    ) {
        // Load appropriate channel objects:
        $providers = $this->getChannelProviders($source, 'search', $activeChannel);

        // Perform search:
        $results = $this->performChannelSearch($searchRequest, $providers, $source);

        // Collect details:
        $lookfor = $searchRequest['lookfor'] ?? null;
        $channels = $this->getChannelsFromResults($providers, $results, $token);

        // Return context array:
        return $this->addConfigToContext(compact('results', 'lookfor', 'channels', 'token'));
    }
}
