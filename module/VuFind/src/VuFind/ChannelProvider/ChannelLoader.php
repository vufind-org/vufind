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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Channels
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\ChannelProvider;

use Laminas\Config\Config;
use VuFind\Cache\Manager as CacheManager;
use VuFind\ChannelProvider\PluginManager as ChannelManager;
use VuFind\Record\Loader as RecordLoader;
use VuFind\Search\Base\Results;
use VuFind\Search\SearchRunner;

use function in_array;

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
    /**
     * Cache manager
     *
     * @var CacheManager
     */
    protected $cacheManager;

    /**
     * Channel manager
     *
     * @var ChannelManager
     */
    protected $channelManager;

    /**
     * Channel configuration
     *
     * @var Config
     */
    protected $config;

    /**
     * Record loader
     *
     * @var RecordLoader
     */
    protected $recordLoader;

    /**
     * Search runner
     *
     * @var SearchRunner
     */
    protected $searchRunner;

    /**
     * Current locale (used for caching)
     *
     * @var string
     */
    protected $locale;

    /**
     * Constructor
     *
     * @param Config         $config Channels configuration
     * @param CacheManager   $cache  Cache manager
     * @param ChannelManager $cm     Channel manager
     * @param SearchRunner   $runner Search runner
     * @param RecordLoader   $loader Record loader
     * @param string         $locale Current locale (used for caching)
     */
    public function __construct(
        Config $config,
        CacheManager $cache,
        ChannelManager $cm,
        SearchRunner $runner,
        RecordLoader $loader,
        string $locale = ''
    ) {
        $this->config = $config;
        $this->cacheManager = $cache;
        $this->channelManager = $cm;
        $this->searchRunner = $runner;
        $this->recordLoader = $loader;
        $this->locale = $locale;
    }

    /**
     * Get a search results object configured by channel providers.
     *
     * @param array  $searchRequest Search request parameters
     * @param array  $providers     Array of channel providers
     * @param string $source        Backend to use
     *
     * @return Results
     */
    protected function performChannelSearch($searchRequest, $providers, $source)
    {
        // Perform search and configure providers:
        $callback = function ($runner, $params) use ($providers) {
            foreach ($providers as $provider) {
                $provider->configureSearchParams($params);
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
     * @param array  $configSection Configuration section to load ID list from
     * @param string $activeId      Currently selected channel ID (if any; used
     * when making an AJAX request for a single additional channel)
     *
     * @return array
     */
    protected function getChannelProviders($source, $configSection, $activeId = null)
    {
        $providerIds = isset($this->config->{"source.$source"}->$configSection)
            ? $this->config->{"source.$source"}->$configSection->toArray() : [];
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
        $options = isset($this->config->{$configSection})
            ? $this->config->{$configSection}->toArray() : [];

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
        $defaultSource = $this->config->General->default_home_source
            ?? DEFAULT_SEARCH_BACKEND;
        $source = $activeSource ?? $defaultSource;
        $providers = $this->getChannelProviders($source, 'home', $activeChannel);

        // Set up the cache, if appropriate:
        if ($this->config->General->cache_home_channels ?? false) {
            $providerIds = array_map('get_class', $providers);
            $parts = [implode(',', $providerIds), $source, $token, $this->locale];
            $cacheKey = md5(implode('-', $parts));
            $cache = $this->cacheManager->getCache('object', 'homeChannels');
        } else {
            $cacheKey = false;
            $cache = null;
        }

        // Fetch channel data from cache, or populate cache if necessary:
        if (!($channels = $cacheKey ? $cache->getItem($cacheKey) : false)) {
            $searchParams = [];
            if (isset($this->config->General->default_home_search)) {
                $searchParams['lookfor']
                    = $this->config->General->default_home_search;
            }
            $results = $this
                ->performChannelSearch($searchParams, $providers, $source);
            $channels = $this->getChannelsFromResults($providers, $results, $token);
            if ($cacheKey) {
                $cache->setItem($cacheKey, $channels);
            }
        }

        // Return context array:
        return compact('token', 'channels');
    }

    /**
     * Generates channels for a record.
     *
     * @param string $recordId      Record ID to load
     * @param string $token         Channel token (optional, used for AJAX fetching)
     * @param string $activeChannel Channel being requested (optional, used w/ token)
     * @param string $source        Search backend to use
     *
     * @return array
     */
    public function getRecordContext(
        $recordId,
        $token = null,
        $activeChannel = null,
        $source = DEFAULT_SEARCH_BACKEND
    ) {
        // Load record:
        $driver = $this->recordLoader->load($recordId, $source);

        // Load appropriate channel objects:
        $providers = $this->getChannelProviders($source, 'record', $activeChannel);

        // Collect details:
        $channels = [];
        foreach ($providers as $provider) {
            $channels = array_merge(
                $channels,
                $provider->getFromRecord($driver, $token)
            );
        }

        // Return context array:
        return compact('driver', 'channels', 'token');
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
        return compact('results', 'lookfor', 'channels', 'token');
    }
}
