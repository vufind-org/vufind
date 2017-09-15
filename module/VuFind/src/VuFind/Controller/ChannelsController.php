<?php
/**
 * Channels Controller
 *
 * PHP Version 5
 *
 * Copyright (C) Villanova University 2016.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.    See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/indexing:alphabetical_heading_browse Wiki
 */
namespace VuFind\Controller;
use Zend\Config\Config;

/**
 * Channels Class
 *
 * Controls the alphabetical browsing feature
 *
 * @category VuFind
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/indexing:alphabetical_heading_browse Wiki
 */
class ChannelsController extends AbstractBase
{
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
        $runner = $this->serviceLocator->get('VuFind\SearchRunner');
        $results = $runner->run([], $searchClassId, $callback);

        $channels = [];
        foreach ($providers as $provider) {
            $channels = array_merge(
                $channels, $provider->getFromSearch($results, $token)
            );
        }
        return $channels;
    }

    /**
     * Generates static front page of channels.
     *
     * @return \Zend\View\Model\ViewModel
     */
    public function homeAction()
    {
        $config = $this->getConfig('channels');
        $defaultSearchClassId = isset($config->General->default_home_source)
            ? $config->General->default_home_source : DEFAULT_SEARCH_BACKEND;
        $searchClassId = $this->params()->fromQuery('source', $defaultSearchClassId);
        $providerIds = isset($config->{"source.$searchClassId"}->home)
            ? $config->{"source.$searchClassId"}->home->toArray() : [];
        $providers = $this->getChannelProviderArray($providerIds, $config);

        $token = $this->params()->fromQuery('channelToken');
        if (isset($config->General->cache_home_channels)
            && $config->General->cache_home_channels
        ) {
            $parts = [implode(',', $providerIds), $searchClassId, $token];
            $cacheKey = 'homeChannels-' . md5(implode('-', $parts));
            $cache = $this->serviceLocator->get('VuFind\CacheManager')
                ->getCache('object');
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
        return $this->createViewModel(compact('token', 'channels'));
    }

    /**
     * Generates channels for a record.
     *
     * @return \Zend\View\Model\ViewModel
     */
    public function recordAction()
    {
        $view = $this->createViewModel();

        $loader = $this->getRecordLoader();
        $source = $this->params()->fromQuery('source', DEFAULT_SEARCH_BACKEND);
        $view->driver = $loader->load($this->params()->fromQuery('id'), $source);

        $config = $this->getConfig('channels');
        $providerIds = isset($config->{"source.$source"}->record)
            ? $config->{"source.$source"}->record->toArray() : [];
        $view->channels = [];
        $view->token = $this->params()->fromQuery('channelToken');
        $providers = $this->getChannelProviderArray($providerIds, $config);
        foreach ($providers as $provider) {
            $view->channels = array_merge(
                $view->channels,
                $provider->getFromRecord($view->driver, $view->token)
            );
        }
        return $view;
    }

    /**
     * Generates channels for a search.
     *
     * @return \Zend\View\Model\ViewModel
     */
    public function searchAction()
    {
        $view = $this->createViewModel();

        $runner = $this->serviceLocator->get('VuFind\SearchRunner');

        // Send both GET and POST variables to search class:
        $request = $this->getRequest()->getQuery()->toArray()
            + $this->getRequest()->getPost()->toArray();
        $searchClassId = $this->params()
            ->fromQuery('source', DEFAULT_SEARCH_BACKEND);

        $config = $this->getConfig('channels');
        $providerIds = isset($config->{"source.$searchClassId"}->search)
            ? $config->{"source.$searchClassId"}->search->toArray() : [];
        $providers = $this->getChannelProviderArray($providerIds, $config);

        $callback = function ($runner, $params, $searchClassId) use ($providers) {
            foreach ($providers as $provider) {
                $provider->configureSearchParams($params);
            }
        };
        $view->results = $runner->run($request, $searchClassId, $callback);

        $view->channels = [];
        $view->lookfor = $this->params()->fromQuery('lookfor');
        $view->token = $this->params()->fromQuery('channelToken');
        foreach ($providers as $provider) {
            $view->channels = array_merge(
                $view->channels,
                $provider->getFromSearch($view->results, $view->token)
            );
        }
        return $view;
    }

    /**
     * Get an array of channel providers matching the provided IDs (or just one,
     * if the channelProvider GET parameter is set).
     *
     * @param array  $providerIds Array of IDs to load
     * @param Config $config      Channel configuration
     *
     * @return array
     */
    protected function getChannelProviderArray($providerIds, $config)
    {
        $id = $this->params()->fromQuery('channelProvider');
        if (!empty($id) && in_array($id, $providerIds)) {
            return [$this->getChannelProvider($id, $config)];
        }
        $results = [];
        foreach ($providerIds as $id) {
            $results[] = $this->getChannelProvider($id, $config);
        }
        return $results;
    }

    /**
     * Convenience method to retrieve a channel provider.
     *
     * @param string $providerId Channel provider name and optional config
     * (colon-delimited)
     * @param Config $config     Channel configuration
     *
     * @return \VuFind\ChannelProvider\ChannelProviderInterface
     */
    protected function getChannelProvider($providerId, Config $config)
    {
        // The provider ID consists of a service name and an optional config
        // section -- break out the relevant parts:
        list($serviceName, $configSection) = explode(':', $providerId . ':');

        // Load configuration, using default value if necessary:
        if (empty($configSection)) {
            $configSection = "provider.$serviceName";
        }
        $options = isset($config->{$configSection})
            ? $config->{$configSection}->toArray() : [];

        // Load the service, and configure appropriately:
        $provider = $this->serviceLocator
            ->get('VuFind\ChannelProviderPluginManager')->get($serviceName);
        $provider->setProviderId($providerId);
        $provider->setOptions($options);
        return $provider;
    }
}
