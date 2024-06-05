<?php

/**
 * Favorites service
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
 * @package  Favorites
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Favorites;

use DateTime;
use VuFind\Db\Entity\User;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\Entity\UserListEntityInterface;
use VuFind\Db\Service\DbServiceAwareInterface;
use VuFind\Db\Service\DbServiceAwareTrait;
use VuFind\Db\Service\UserListServiceInterface;
use VuFind\Exception\LoginRequired as LoginRequiredException;
use VuFind\Record\Cache as RecordCache;
use VuFind\Record\ResourcePopulator;
use VuFind\RecordDriver\AbstractBase as RecordDriver;

use function intval;

/**
 * Favorites service
 *
 * @category VuFind
 * @package  Favorites
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class FavoritesService implements \VuFind\I18n\Translator\TranslatorAwareInterface, DbServiceAwareInterface
{
    use \VuFind\I18n\Translator\TranslatorAwareTrait;
    use DbServiceAwareTrait;

    /**
     * Constructor
     *
     * @param UserListServiceInterface $userListService   UserList database service
     * @param ResourcePopulator        $resourcePopulator Resource populator service
     * @param ?RecordCache             $recordCache       Record cache (optional)
     */
    public function __construct(
        protected UserListServiceInterface $userListService,
        protected ResourcePopulator $resourcePopulator,
        protected ?RecordCache $recordCache = null
    ) {
    }

    /**
     * Create a new list object for the specified user.
     *
     * @param ?UserEntityInterface $user Logged in user (null if logged out)
     *
     * @return UserListEntityInterface
     * @throws LoginRequiredException
     */
    public function createListForUser(?UserEntityInterface $user): UserListEntityInterface
    {
        if (!$user) {
            throw new LoginRequiredException('Log in to create lists.');
        }

        return $this->userListService->createEntity()
            ->setCreated(new DateTime())
            // Stopgap until we've fully converted to Doctrine:
            ->setUser($this->userListService->getDoctrineReference(User::class, $user));
    }

    /**
     * Get a list object for the specified ID (or null to create a new list).
     * Ensure that the object is persisted to the database if it does not
     * already exist, and remember it as the user's last-accessed list.
     *
     * @param ?int                $listId List ID (or null to create a new list)
     * @param UserEntityInterface $user   The user saving the record
     *
     * @return UserListEntityInterface
     *
     * @throws \VuFind\Exception\ListPermission
     */
    public function getAndRememberListObject(?int $listId, UserEntityInterface $user): UserListEntityInterface
    {
        if (empty($listId)) {
            $list = $this->createListForUser($user)
                ->setTitle($this->translate('default_list_title'));
            $this->userListService->save($list, $user);
        } else {
            $list = $this->userListService->getUserListById($listId);
            // Validate incoming list ID:
            if (!$list->editAllowed($user->id)) {
                throw new \VuFind\Exception\ListPermission('Access denied.');
            }
            $this->userListService->rememberLastUsed($list); // handled by save() in other case
        }
        return $list;
    }

    /**
     * Given an array of parameters, extract a list ID if possible. Return null
     * if no valid ID is found or if a "NEW" record is requested.
     *
     * @param array $params Parameters to process
     *
     * @return ?int
     */
    public function getListIdFromParams(array $params): ?int
    {
        return intval($params['list'] ?? 'NEW') ?: null;
    }

    /**
     * Persist a resource to the record cache (if applicable).
     *
     * @param RecordDriver               $driver   Record driver to persist
     * @param \VuFind\Db\Entity\Resource $resource Resource object
     *
     * @return void
     */
    protected function persistToCache(
        RecordDriver $driver,
        \VuFind\Db\Entity\Resource $resource
    ) {
        if ($this->recordCache) {
            $this->recordCache->setContext(RecordCache::CONTEXT_FAVORITE);
            $this->recordCache->createOrUpdate(
                $resource->getRecordId(),
                $resource->getSource(),
                $driver->getRawData()
            );
        }
    }

    /**
     * Save this record to the user's favorites.
     *
     * @param array               $params Array with some or all of these keys:
     *  <ul>
     *    <li>mytags - Tag array to associate with record (optional)</li>
     *    <li>notes - Notes to associate with record (optional)</li>
     *    <li>list - ID of list to save record into (omit to create new list)</li>
     *  </ul>
     * @param \VuFind\Db\Row\User $user   The user saving the record
     * @param RecordDriver        $driver Record driver for record being saved
     *
     * @return array list information
     */
    public function save(
        array $params,
        \VuFind\Db\Row\User $user,
        RecordDriver $driver
    ) {
        // Validate incoming parameters:
        if (!$user) {
            throw new LoginRequiredException('You must be logged in first');
        }

        // Get or create a list object as needed:
        $list = $this->getAndRememberListObject($this->getListIdFromParams($params), $user);

        // Get or create a resource object as needed:
        $resource = $this->resourcePopulator->getOrCreateResourceForDriver($driver);

        // Persist record in the database for "offline" use
        $this->persistToCache($driver, $resource);

        // Add the information to the user's account:
        $userService = $this->getDbService(\VuFind\Db\Service\UserService::class);
        $userService->saveResource(
            $resource,
            $user->id,
            $list,
            $params['mytags'] ?? [],
            $params['notes'] ?? ''
        );
        return ['listId' => $list->getId()];
    }
}
