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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA    02111-1307    USA
 *
 * @category VuFind
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/indexing:alphabetical_heading_browse Wiki
 */
namespace VuFind\Controller;

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
     * Generates channels for a record.
     *
     * @return \Zend\View\Model\ViewModel
     */
    public function recordAction()
    {
        $view = $this->createViewModel();

        $loader = $this->getRecordLoader();
        $record = $loader->load(
            $this->params()->fromQuery('id'),
            $this->params()->fromQuery('source', DEFAULT_SEARCH_BACKEND)
        );

        $providerIds = ['similaritems', 'facets'];
        $view->channels = [];
        $token = $this->params()->fromQuery('channelToken');
        foreach ($this->getChannelProviderArray($providerIds) as $provider) {
            $view->channels = array_merge(
                $view->channels, $provider->getFromRecord($record, $token)
            );
        }
        $view->setTemplate('channels/search');
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

        $runner = $this->getServiceLocator()->get('VuFind\SearchRunner');

        // Send both GET and POST variables to search class:
        $request = $this->getRequest()->getQuery()->toArray()
            + $this->getRequest()->getPost()->toArray();
        $searchClassId = $this->params()
            ->fromQuery('source', DEFAULT_SEARCH_BACKEND);

        $providerIds = ['facets', 'similaritems'];
        $providers = $this->getChannelProviderArray($providerIds);

        $callback = function ($runner, $params, $searchClassId) use ($providers) {
            foreach ($providers as $provider) {
                $provider->configureSearchParams($params);
            }
        };
        $results = $runner->run($request, $searchClassId, $callback);

        $view->channels = [];
        $token = $this->params()->fromQuery('channelToken');
        foreach ($providers as $provider) {
            $view->channels = array_merge(
                $view->channels, $provider->getFromSearch($results, $token)
            );
        }
        return $view;
    }

    /**
     * Get an array of channel providers matching the provided IDs (or just one,
     * if the channelProvider GET parameter is set).
     *
     * @param array $ids Array of IDs to load
     *
     * @return array
     */
    protected function getChannelProviderArray($ids)
    {
        $id = $this->params()->fromQuery('channelProvider');
        return (!empty($id) && in_array($id, $ids))
            ? [$this->getChannelProvider($id)]
            : array_map([$this, 'getChannelProvider'], $ids);
    }

    /**
     * Convenience method to retrieve a channel provider.
     *
     * @param string $id Service name for channel provider.
     *
     * @return \VuFind\ChannelProvider\ChannelProviderInterface
     */
    protected function getChannelProvider($id)
    {
        return $this->getServiceLocator()
            ->get('VuFind\ChannelProviderPluginManager')->get($id);
    }
}
