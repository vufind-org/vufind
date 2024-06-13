<?php

/**
 * "List items" channel provider.
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

use Laminas\Mvc\Controller\Plugin\Url;
use Laminas\Stdlib\Parameters;
use VuFind\Db\Entity\UserListEntityInterface;
use VuFind\Db\Service\UserListServiceInterface;
use VuFind\RecordDriver\AbstractBase as RecordDriver;
use VuFind\Search\Base\Results;
use VuFind\Tags\TagsService;

use function count;

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
     * Constructor
     *
     * @param UserListServiceInterface             $userListService UserList database service
     * @param Url                                  $url             URL helper
     * @param \VuFind\Search\Results\PluginManager $resultsManager  Results manager
     * @param TagsService                          $tagsService     Tags service
     * @param array                                $options         Settings (optional)
     */
    public function __construct(
        protected UserListServiceInterface $userListService,
        protected Url $url,
        protected \VuFind\Search\Results\PluginManager $resultsManager,
        protected TagsService $tagsService,
        array $options = []
    ) {
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
     * @param int[] $ids IDs to retrieve
     *
     * @return UserListEntityInterface[]
     */
    protected function getListsById(array $ids): array
    {
        return $this->userListService->getPublicLists($ids);
    }

    /**
     * Given an array of lists, add public lists if configured to do so.
     *
     * @param UserListEntityInterface[] $lists List to expand.
     *
     * @return UserListEntityInterface[]
     */
    protected function addPublicLists(array $lists): array
    {
        return $this->displayPublicLists
            ? array_merge($lists, $this->userListService->getPublicLists([], $lists))
            : $lists;
    }

    /**
     * Get a list of public lists to display:
     *
     * @return UserListEntityInterface[]
     */
    protected function getLists(): array
    {
        // Depending on whether tags are configured, we use different methods to
        // fetch the base list of lists...
        $baseLists = $this->tags
            ? $this->getListsByTagAndId()
            : $this->getListsById($this->ids);

        // Sort lists by ID list, if necessary:
        if (!empty($baseLists) && $this->ids) {
            $orderIds = (array)$this->ids;
            $sortFn = function (UserListEntityInterface $left, UserListEntityInterface $right) use ($orderIds) {
                return
                    array_search($left->getId(), $orderIds)
                    <=> array_search($right->getId(), $orderIds);
            };
            usort($baseLists, $sortFn);
        }

        // Next, we add other public lists if necessary:
        return $this->addPublicLists($baseLists);
    }

    /**
     * Get a list of public lists, identified by ID and tag.
     *
     * @return UserListEntityInterface[]
     */
    protected function getListsByTagAndId(): array
    {
        // Get public lists by search criteria
        return $this->tagsService->getUserListsByTagAndId(
            $this->tags,
            $this->ids,
            true,
            $this->andTags
        );
    }

    /**
     * Given a list object, return a channel array.
     *
     * @param UserListEntityInterface $list      User list
     * @param bool                    $tokenOnly Return only token information?
     *
     * @return array
     */
    protected function getChannelFromList(UserListEntityInterface $list, bool $tokenOnly): array
    {
        $retVal = [
            'title' => $list->getTitle(),
            'providerId' => $this->providerId,
            'token' => $list->getId(),
            'links' => [],
        ];
        if ($tokenOnly) {
            return $retVal;
        }
        $results = $this->resultsManager->get('Favorites');
        $results->getParams()->initFromRequest(new Parameters(['id' => $list->getId()]));
        $retVal['contents'] = $this->summarizeRecordDrivers($results->getResults());
        $retVal['links'][] = [
            'label' => 'channel_search',
            'icon' => 'fa-list',
            'url' => $this->url->fromRoute('userList', ['id' => $list->getId()]),
        ];
        return $retVal;
    }
}
