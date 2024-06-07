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
use Laminas\Session\Container;
use Laminas\Stdlib\Parameters;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\Entity\UserListEntityInterface;
use VuFind\Db\Service\ResourceServiceInterface;
use VuFind\Db\Service\UserListServiceInterface;
use VuFind\Db\Table\DbTableAwareInterface;
use VuFind\Db\Table\DbTableAwareTrait;
use VuFind\Exception\ListPermission as ListPermissionException;
use VuFind\Exception\LoginRequired as LoginRequiredException;
use VuFind\Exception\MissingField as MissingFieldException;
use VuFind\Record\Cache as RecordCache;
use VuFind\Record\ResourcePopulator;
use VuFind\RecordDriver\AbstractBase as RecordDriver;
use VuFind\Tags;

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
class FavoritesService implements \VuFind\I18n\Translator\TranslatorAwareInterface, DbTableAwareInterface
{
    use \VuFind\I18n\Translator\TranslatorAwareTrait;
    use DbTableAwareTrait;

    /**
     * Constructor
     *
     * @param ResourceServiceInterface $resourceService   Resource database service
     * @param UserListServiceInterface $userListService   UserList database service
     * @param ResourcePopulator        $resourcePopulator Resource populator service
     * @param Tags                     $tagHelper         Tag helper service
     * @param ?RecordCache             $recordCache       Record cache (optional)
     * @param ?Container               $session           Session container for remembering state (optional)
     */
    public function __construct(
        protected ResourceServiceInterface $resourceService,
        protected UserListServiceInterface $userListService,
        protected ResourcePopulator $resourcePopulator,
        protected Tags $tagHelper,
        protected ?RecordCache $recordCache = null,
        protected ?Container $session = null
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
            ->setUser($user);
    }

    /**
     * Remember that this list was used so that it can become the default in
     * dialog boxes.
     *
     * @param UserListEntityInterface $list List to remember
     *
     * @return void
     */
    public function rememberLastUsedList(UserListEntityInterface $list): void
    {
        if (null !== $this->session) {
            $this->session->lastUsed = $list->getId();
        }
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
            $this->saveListForUser($list, $user);
        } else {
            $list = $this->userListService->getUserListById($listId);
            // Validate incoming list ID:
            if (!$list->editAllowed($user)) {
                throw new \VuFind\Exception\ListPermission('Access denied.');
            }
            $this->rememberLastUsedList($list); // handled by saveListForUser() in other case
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
     * Retrieve the ID of the last list that was accessed, if any.
     *
     * @return ?int User_list ID (if set) or null (if not available).
     */
    public function getLastUsedList(): ?int
    {
        return $this->session->lastUsed ?? null;
    }

    /**
     * Persist a resource to the record cache (if applicable).
     *
     * @param RecordDriver            $driver   Record driver to persist
     * @param \VuFind\Db\Row\Resource $resource Resource row
     *
     * @return void
     */
    protected function persistToCache(
        RecordDriver $driver,
        \VuFind\Db\Row\Resource $resource
    ) {
        if ($this->recordCache) {
            $this->recordCache->setContext(RecordCache::CONTEXT_FAVORITE);
            $this->recordCache->createOrUpdate(
                $resource->record_id,
                $resource->source,
                $driver->getRawData()
            );
        }
    }

    /**
     * Given an array of item ids, remove them from the specified list.
     *
     * @param UserListEntityInterface $list   List being updated
     * @param ?UserEntityInterface    $user   Logged-in user (null if none)
     * @param string[]                $ids    IDs to remove from the list
     * @param string                  $source Type of resource identified by IDs
     *
     * @return void
     */
    public function removeListResourcesById(
        UserListEntityInterface $list,
        ?UserEntityInterface $user,
        array $ids,
        string $source = DEFAULT_SEARCH_BACKEND
    ): void {
        if (!$list->editAllowed($user)) {
            throw new ListPermissionException('list_access_denied');
        }

        // Retrieve a list of resource IDs:
        $resources = $this->resourceService->getResourcesByRecordIds($ids, $source);

        $resourceIDs = [];
        foreach ($resources as $current) {
            $resourceIDs[] = $current->getId();
        }

        // Remove Resource (related tags are also removed implicitly)
        $userResourceTable = $this->getDbTable('UserResource');
        $userResourceTable->destroyLinks(
            $resourceIDs,
            $list->getUser()->getId(),
            $list->getId()
        );
    }

    /**
     * Given an array of item ids, remove them from all of the specified user's lists
     *
     * @param UserEntityInterface $user   User owning lists
     * @param string[]            $ids    IDs to remove from the list
     * @param string              $source Type of resource identified by IDs
     *
     * @return void
     */
    public function removeUserResourcesById(
        UserEntityInterface $user,
        array $ids,
        $source = DEFAULT_SEARCH_BACKEND
    ): void {
        // Retrieve a list of resource IDs:
        $resources = $this->resourceService->getResourcesByRecordIds($ids, $source);

        $resourceIDs = [];
        foreach ($resources as $current) {
            $resourceIDs[] = $current->getId();
        }

        // Remove Resource (related tags are also removed implicitly)
        $userResourceTable = $this->getDbTable('UserResource');
        // true here makes sure that only tags in lists are deleted
        $userResourceTable->destroyLinks($resourceIDs, $user->getId(), true);
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
        $user->saveResource(
            $resource,
            $list,
            $params['mytags'] ?? [],
            $params['notes'] ?? ''
        );
        return ['listId' => $list->id];
    }

    /**
     * Saves the provided list to the database and remembers it in the session if it is valid;
     * throws an exception otherwise.
     *
     * @param UserListEntityInterface $list List to save
     * @param ?UserEntityInterface    $user Logged-in user (null if none)
     *
     * @return void
     * @throws ListPermissionException
     * @throws MissingFieldException
     */
    public function saveListForUser(UserListEntityInterface $list, ?UserEntityInterface $user): void
    {
        if (!$list->editAllowed($user ?: null)) {
            throw new ListPermissionException('list_access_denied');
        }
        if (!$list->getTitle()) {
            throw new MissingFieldException('list_edit_name_required');
        }

        $this->userListService->persistEntity($list);
        $this->rememberLastUsedList($list);
    }

    /**
     * Update and save the list object using a request object -- useful for
     * sharing form processing between multiple actions.
     *
     * @param UserListEntityInterface $list    List to update
     * @param ?UserEntityInterface    $user    Logged-in user (false if none)
     * @param Parameters              $request Request to process
     *
     * @return int ID of newly created row
     * @throws ListPermissionException
     * @throws MissingFieldException
     */
    public function updateListFromRequest(
        UserListEntityInterface $list,
        ?UserEntityInterface $user,
        Parameters $request
    ): int {
        $list->setTitle($request->get('title'));
        $list->setDescription($request->get('desc'));
        $list->setPublic((bool)$request->get('public'));
        $this->saveListForUser($list, $user);

        if (null !== ($tags = $request->get('tags'))) {
            $linker = $this->getDbTable('resourcetags');
            $linker->destroyListLinks($list->getId(), $user->getId());
            foreach ($this->tagHelper->parse($tags) as $tag) {
                $list->addListTag($tag, $user);
            }
        }

        return $list->getId();
    }
}
