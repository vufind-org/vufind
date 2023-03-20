<?php

/**
 * "List items" channel provider.
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

use Laminas\Mvc\Controller\Plugin\Url;
use Laminas\Stdlib\Parameters;
use VuFind\RecordDriver\AbstractBase as RecordDriver;
use VuFind\Search\Base\Results;

/**
 * "List items" channel provider.
 *
 * @category VuFind
 * @package  Channels
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class ListItems extends AbstractChannelProvider
{
    use \VuFind\I18n\Translator\TranslatorAwareTrait;

    /**
     * IDs of lists to display
     *
     * @var array
     */
    protected $ids;

    /**
     * Tags of lists to display
     *
     * @var array
     */
    protected $tags;

    /**
     * Whether to use AND operator when filtering by tag.
     *
     * @var bool
     */
    protected $andTags;

    /**
     * Should we pull in public list results in addition to the inclusion list in
     * $ids?
     *
     * @var bool
     */
    protected $displayPublicLists;

    /**
     * How many lists should we display before switching over to tokens?
     *
     * @var int
     */
    protected $initialListsToDisplay;

    /**
     * UserList table
     *
     * @var \VuFind\Db\Table\UserList
     */
    protected $userList;

    /**
     * UserList table
     *
     * @var \VuFind\Db\Table\UserList
     */
    protected $resourceTags;

    /**
     * Results manager
     *
     * @var \VuFind\Search\Results\PluginManager
     */
    protected $resultsManager;

    /**
     * URL helper
     *
     * @var Url
     */
    protected $url;

    /**
     * Constructor
     *
     * @param \VuFind\Db\Table\UserList            $userList       UserList table
     * @param \VuFind\Db\Table\ResourceTags        $resourceTags   ResourceTags table
     * @param Url                                  $url            URL helper
     * @param \VuFind\Search\Results\PluginManager $resultsManager Results manager
     * @param array                                $options        Settings
     * (optional)
     */
    public function __construct(
        \VuFind\Db\Table\UserList $userList,
        \VuFind\Db\Table\ResourceTags $resourceTags,
        Url $url,
        \VuFind\Search\Results\PluginManager $resultsManager,
        array $options = []
    ) {
        $this->userList = $userList;
        $this->resourceTags = $resourceTags;
        $this->url = $url;
        $this->resultsManager = $resultsManager;
        $this->setOptions($options);
    }

    /**
     * Set the options for the provider.
     *
     * @param array $options Options
     *
     * @return void
     */
    public function setOptions(array $options)
    {
        $this->ids = $options['ids'] ?? [];
        $this->tags = $options['tags'] ?? [];
        $this->andTags
            = 'or' !== trim(strtolower($options['tagsOperator'] ?? 'AND'));

        $this->displayPublicLists = isset($options['displayPublicLists'])
            ? (bool)$options['displayPublicLists'] : true;
        $this->initialListsToDisplay = $options['initialListsToDisplay'] ?? 2;
    }

    /**
     * Return channel information derived from a record driver object.
     *
     * @param RecordDriver $driver       Record driver
     * @param string       $channelToken Token identifying a single specific channel
     * to load (if omitted, all channels will be loaded)
     *
     * @return array
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getFromRecord(RecordDriver $driver, $channelToken = null)
    {
        return $this->buildListChannels($channelToken);
    }

    /**
     * Return channel information derived from a search results object.
     *
     * @param Results $results      Search results
     * @param string  $channelToken Token identifying a single specific channel
     * to load (if omitted, all channels will be loaded)
     *
     * @return array
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getFromSearch(Results $results, $channelToken = null)
    {
        return $this->buildListChannels($channelToken);
    }

    /**
     * Build all of the channel data.
     *
     * @param string $channelToken Token identifying a single specific channel
     * to load (if omitted, all channels will be loaded)
     *
     * @return array
     */
    protected function buildListChannels($channelToken)
    {
        $channels = [];
        $lists = $channelToken
            ? $this->getListsById([$channelToken]) : $this->getLists();
        foreach ($lists as $list) {
            $tokenOnly = (count($channels) >= $this->initialListsToDisplay);
            $channel = $this->getChannelFromList($list, $tokenOnly);
            if ($tokenOnly || count($channel['contents']) > 0) {
                $channels[] = $channel;
            }
        }
        return $channels;
    }

    /**
     * Get a list of lists, identified by ID; filter to public lists only.
     *
     * @param array $ids IDs to retrieve
     *
     * @return array
     */
    protected function getListsById($ids)
    {
        $lists = [];
        foreach ($ids as $id) {
            $list = $this->userList->getExisting($id);
            if ($list->public) {
                $lists[] = $list;
            }
        }
        return $lists;
    }

    /**
     * Given an array of lists, add public lists if configured to do so.
     *
     * @param array $lists List to expand.
     *
     * @return array
     */
    protected function addPublicLists($lists)
    {
        if ($this->displayPublicLists) {
            $resultIds = [];
            foreach ($lists as $list) {
                $resultIds[] = $list->id;
            }
            $callback = function ($select) use ($resultIds) {
                $select->where->equalTo('public', 1);
                if (!empty($resultIds)) {
                    $select->where->notIn('id', $resultIds);
                }
            };
            foreach ($this->userList->select($callback) as $list) {
                $lists[] = $list;
            }
        }
        return $lists;
    }

    /**
     * Get a list of public lists to display:
     *
     * @return array
     */
    protected function getLists()
    {
        // Depending on whether tags are configured, we use different methods to
        // fetch the base list of lists...
        $baseLists = $this->tags
            ? $this->getListsByTagAndId()
            : $this->getListsById($this->ids);

        // Next, we add other public lists if necessary:
        return $this->addPublicLists($baseLists);
    }

    /**
     * Get a list of public lists, identified by ID and tag.
     *
     * @return array
     */
    protected function getListsByTagAndId()
    {
        // Get public lists by search criteria
        $lists = $this->resourceTags->getListsForTag(
            $this->tags,
            $this->ids,
            true,
            $this->andTags
        );

        // Format result set into an array:
        $result = $resultIds = [];
        if ($lists->count()) {
            foreach ($lists as $list) {
                $resultIds[] = $list->list_id;
            }

            $callback = function ($select) use ($resultIds) {
                $select->where->in('id', $resultIds);
            };

            foreach ($this->userList->select($callback) as $list) {
                $result[] = $list;
            }
        }

        // Sort lists by ID list, if necessary:
        if (!empty($result) && $this->ids) {
            $orderIds = (array)$this->ids;
            $sortFn = function ($left, $right) use ($orderIds) {
                return
                    array_search($left->id, $orderIds)
                    <=> array_search($right->id, $orderIds);
            };
            usort($result, $sortFn);
        }

        return $result;
    }

    /**
     * Given a list object, return a channel array.
     *
     * @param \VuFind\Db\Row\UserList $list      User list
     * @param bool                    $tokenOnly Return only token information?
     *
     * @return array
     */
    protected function getChannelFromList($list, $tokenOnly)
    {
        $retVal = [
            'title' => $list->title,
            'providerId' => $this->providerId,
            'token' => $list->id,
            'links' => [],
        ];
        if ($tokenOnly) {
            return $retVal;
        }
        $results = $this->resultsManager->get('Favorites');
        $results->getParams()->initFromRequest(new Parameters(['id' => $list->id]));
        $retVal['contents'] = $this->summarizeRecordDrivers($results->getResults());
        $retVal['links'][] = [
            'label' => 'channel_search',
            'icon' => 'fa-list',
            'url' => $this->url->fromRoute('userList', ['id' => $list->id]),
        ];
        return $retVal;
    }
}
