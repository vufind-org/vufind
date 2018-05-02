<?php
/**
 * Channel loader
 *
 * PHP version 7
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

use VuFind\Cache\Manager as CacheManager;
use VuFind\ChannelProvider\PluginManager as ChannelManager;
use VuFind\Record\Loader as RecordLoader;
use VuFind\Search\SearchRunner;
use Zend\Config\Config;
use Zend\Http\PhpEnvironment\Request;

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
     * HTTP request object
     *
     * @var Request
     */
    protected $request;

    /**
     * Search runner
     *
     * @var SearchRunner
     */
    protected $searchRunner;

    /**
     * Constructor
     *
     * @param Config         $config  Channels configuration
     * @param Request        $request HTTP request
     * @param CacheManager   $cache   Cache manager
     * @param ChannelManager $cm      Channel manager
     * @param SearchRunner   $runner  Search runner
     * @param RecordLoader   $loader  Record loader
     */
    public function __construct(Config $config, Request $request,
        CacheManager $cache, ChannelManager $cm, SearchRunner $runner,
        RecordLoader $loader
    ) {
        $this->config = $config;
        $this->request = $request;
        $this->cacheManager = $cache;
        $this->channelManager = $cm;
        $this->searchRunner = $runner;
        $this->recordLoader = $loader;
    }

    /**
     * Retrieve channel information for the Channels/Home page.
     *
     * @param array  $providers     Array of channel providers
     * @param string $searchClassId Search class ID
     * @param string $token         Channel token
     *
     * @return array
     */
    protected function getHomeChannels($providers, $searchClassId, $token)
    {
        $callback = function ($runner, $params, $searchClassId) use ($providers) {
            foreach ($providers as $provider) {
                $provider->configureSearchParams($params);
            }
        };
        $results = $this->searchRunner->run([], $searchClassId, $callback);

        $channels = [];
        foreach ($providers as $provider) {
            $channels = array_merge(
                $channels, $provider->getFromSearch($results, $token)
            );
        }
        return $channels;
    }

    /**
     * Get an array of channel providers matching the provided IDs (or just one,
     * if the channelProvider GET parameter is set).
     *
     * @param array  $providerIds Array of IDs to load
     *
     * @return array
     */
    protected function getChannelProviderArray($providerIds)
    {
        $id = $this->request->getQuery()->get('channelProvider');
        $finalIds = (!empty($id) && in_array($id, $providerIds))
            ? [$id] : $providerIds;
        return array_map([$this, 'getChannelProvider'], $finalIds);
    }

    /**
     * Convenience method to retrieve a channel provider.
     *
     * @param string $providerId Channel provider name and optional config
     * (colon-delimited)
     *
     * @return \VuFind\ChannelProvider\ChannelProviderInterface
     */
    protected function getChannelProvider($providerId)
    {
        // The provider ID consists of a service name and an optional config
        // section -- break out the relevant parts:
        list($serviceName, $configSection) = explode(':', $providerId . ':');

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
     * @return array
     */
    public function getHomeContext()
    {
        $defaultSearchClassId
            = $this->config->General->default_home_source ?? DEFAULT_SEARCH_BACKEND;
        $searchClassId = $this->request->getQuery()
            ->get('source', $defaultSearchClassId);
        $providerIds = isset($this->config->{"source.$searchClassId"}->home)
            ? $this->config->{"source.$searchClassId"}->home->toArray() : [];
        $providers = $this->getChannelProviderArray($providerIds);

        $token = $this->request->getQuery()->get('channelToken');
        if ($this->config->General->cache_home_channels ?? false) {
            $parts = [implode(',', $providerIds), $searchClassId, $token];
            $cacheKey = md5(implode('-', $parts));
            $cache = $this->cacheManager->getCache('object', 'homeChannels');
        } else {
            $cacheKey = false;
        }
        $channels = $cacheKey ? $cache->getItem($cacheKey) : false;
        if (!$channels) {
            $channels = $this->getHomeChannels($providers, $searchClassId, $token);
            if ($cacheKey) {
                $cache->setItem($cacheKey, $channels);
            }
        }
        return compact('token', 'channels');
    }

    /**
     * Generates channels for a record.
     *
     * @return array
     */
    public function getRecordContext()
    {
        $source = $this->request->getQuery()->get('source', DEFAULT_SEARCH_BACKEND);
        $driver = $this->recordLoader
            ->load($this->request->getQuery()->get('id'), $source);

        $providerIds = isset($this->config->{"source.$source"}->record)
            ? $this->config->{"source.$source"}->record->toArray() : [];
        $channels = [];
        $token = $this->request->getQuery()->get('channelToken');
        $providers = $this->getChannelProviderArray($providerIds);
        foreach ($providers as $provider) {
            $channels = array_merge(
                $channels, $provider->getFromRecord($driver, $token)
            );
        }
        return compact('driver', 'channels', 'token');
    }

    /**
     * Generates channels for a search.
     *
     * @return array
     */
    public function getSearchContext()
    {
        // Send both GET and POST variables to search class:
        $request = $this->request->getQuery()->toArray()
            + $this->request->getPost()->toArray();
        $searchClassId = $this->request->getQuery()
            ->get('source', DEFAULT_SEARCH_BACKEND);

        $providerIds = isset($this->config->{"source.$searchClassId"}->search)
            ? $this->config->{"source.$searchClassId"}->search->toArray() : [];
        $providers = $this->getChannelProviderArray($providerIds);

        $callback = function ($runner, $params, $searchClassId) use ($providers) {
            foreach ($providers as $provider) {
                $provider->configureSearchParams($params);
            }
        };
        $results = $this->searchRunner->run($request, $searchClassId, $callback);

        $channels = [];
        $lookfor = $this->request->getQuery()->get('lookfor');
        $token = $this->request->getQuery()->get('channelToken');
        foreach ($providers as $provider) {
            $channels = array_merge(
                $channels, $provider->getFromSearch($results, $token)
            );
        }
        return compact('results', 'lookfor', 'channels', 'token');
    }
}
