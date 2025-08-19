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
use VuFind\Db\Entity\ResourceEntityInterface;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\Entity\UserListEntityInterface;
use VuFind\Db\Service\Feature\TransactionInterface;
use VuFind\Db\Service\ResourceServiceInterface;
use VuFind\Db\Service\ResourceTagsServiceInterface;
use VuFind\Db\Service\UserListServiceInterface;
use VuFind\Db\Service\UserResourceServiceInterface;
use VuFind\Db\Service\UserServiceInterface;
use VuFind\Exception\ListPermission as ListPermissionException;
use VuFind\Exception\LoginRequired as LoginRequiredException;
use VuFind\Exception\MissingField as MissingFieldException;
use VuFind\Exception\RecordMissing;
use VuFind\I18n\Translator\TranslatorAwareInterface;
use VuFind\Record\Cache as RecordCache;
use VuFind\Record\Loader as RecordLoader;
use VuFind\Record\ResourcePopulator;
use VuFind\RecordDriver\AbstractBase as RecordDriver;
use VuFind\Tags\TagsService;

use function count;
use function func_get_args;
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
class FavoritesService implements TranslatorAwareInterface
{
    use \VuFind\I18n\Translator\TranslatorAwareTrait;

    /**
     * Constructor
     *
     * @param ResourceServiceInterface                          $resourceService     Resource database service
     * @param ResourceTagsServiceInterface&TransactionInterface $resourceTagsService Resource tags database service
     * @param UserListServiceInterface                          $userListService     UserList database service
     * @param UserResourceServiceInterface                      $userResourceService UserResource database service
     * @param UserServiceInterface                              $userService         User database service
     * @param ResourcePopulator                                 $resourcePopulator   Resource populator service
     * @param TagsService                                       $tagsService         Tags service
     * @param RecordLoader                                      $recordLoader        Record loader
     * @param ?RecordCache                                      $recordCache         Record cache (optional)
     * @param ?Container                                        $session             Session container for remembering
     * state (optional)
     */
    public function __construct(
        protected ResourceServiceInterface $resourceService,
        protected ResourceTagsServiceInterface&TransactionInterface $resourceTagsService,
        protected UserListServiceInterface $userListService,
        protected UserResourceServiceInterface $userResourceService,
        protected UserServiceInterface $userService,
        protected ResourcePopulator $resourcePopulator,
        protected TagsService $tagsService,
        protected RecordLoader $recordLoader,
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
     * Destroy a list.
     *
     * @param UserListEntityInterface $list  List to destroy
     * @param ?UserEntityInterface    $user  Logged-in user (null if none)
     * @param bool                    $force Should we force the delete without checking permissions?
     *
     * @return void
     * @throws ListPermissionException
     */
    public function destroyList(
        UserListEntityInterface $list,
        ?UserEntityInterface $user = null,
        bool $force = false
    ): void {
        if (!$force && !$this->userCanEditList($user, $list)) {
            throw new ListPermissionException('list_access_denied');
        }

        // Remove user_resource and resource_tags rows for favorites tags:
        $listUser = $list->getUser();
        $this->resourceTagsService->destroyResourceTagsLinksForUser(null, $listUser, $list);
        $this->userResourceService->unlinkFavorites(null, $listUser, $list);

        // Remove resource_tags rows for list tags:
        $this->resourceTagsService->destroyUserListLinks($list, $user);

        // Clean up orphaned tags:
        $this->tagsService->deleteOrphanedTags();

        $this->userListService->deleteUserList($list);
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
            if (!$this->userCanEditList($user, $list)) {
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
     * @return ?int Identifier value of a UserListEntityInterface object (if set) or null (if not available).
     */
    public function getLastUsedList(): ?int
    {
        return $this->session->lastUsed ?? null;
    }

    /**
     * Persist a resource to the record cache (if applicable).
     *
     * @param RecordDriver            $driver   Record driver to persist
     * @param ResourceEntityInterface $resource Resource row
     *
     * @return void
     */
    protected function persistToCache(
        RecordDriver $driver,
        ResourceEntityInterface $resource
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
        if (!$this->userCanEditList($user, $list)) {
            throw new ListPermissionException('list_access_denied');
        }

        // Retrieve a list of resource IDs:
        $resources = $this->resourceService->getResourcesByRecordIds($ids, $source);

        $resourceIDs = [];
        foreach ($resources as $current) {
            $resourceIDs[] = $current->getId();
        }

        // Remove Resource and related tags:
        $listUser = $list->getUser();
        $this->resourceTagsService->destroyResourceTagsLinksForUser($resourceIDs, $listUser, $list);
        $this->userResourceService->unlinkFavorites($resourceIDs, $listUser, $list);
        $this->tagsService->deleteOrphanedTags();
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

        // Remove resource and related tags:
        $this->resourceTagsService->destroyAllListResourceTagsLinksForUser($resourceIDs, $user);
        $this->userResourceService->unlinkFavorites($resourceIDs, $user->getId(), null);
        $this->tagsService->deleteOrphanedTags();
    }

    /**
     * Legacy name for saveRecordToFavorites()
     *
     * @return array
     *
     * @deprecated Use saveRecordToFavorites()
     */
    public function save()
    {
        return $this->saveRecordToFavorites(...func_get_args());
    }

    /**
     * Add/update a resource in the user's account.
     *
     * @param UserEntityInterface|int     $userOrId        The user entity or ID saving the favorites
     * @param ResourceEntityInterface|int $resourceOrId    The resource entity or ID to add/update
     * @param UserListEntityInterface|int $listOrId        The list entity or ID to store the resource in.
     * @param array                       $tagArray        An array of tags to associate with the resource.
     * @param string                      $notes           User notes about the resource.
     * @param bool                        $replaceExisting Whether to replace all existing tags (true) or
     * append to the existing list (false).
     *
     * @return void
     */
    public function saveResourceToFavorites(
        UserEntityInterface|int $userOrId,
        ResourceEntityInterface|int $resourceOrId,
        UserListEntityInterface|int $listOrId,
        array $tagArray,
        string $notes,
        bool $replaceExisting = true
    ): void {
        $user = $userOrId instanceof UserEntityInterface
            ? $userOrId
            : $this->userService->getUserById($userOrId);
        $resource = $resourceOrId instanceof ResourceEntityInterface
            ? $resourceOrId
            : $this->resourceService->getResourceById($resourceOrId);
        $list = $listOrId instanceof UserListEntityInterface
            ? $listOrId
            : $this->userListService->getUserListById($listOrId);

        // Create the resource link if it doesn't exist and update the notes in any
        // case:
        $this->userResourceService->createOrUpdateLink($resource, $user, $list, $notes);

        // If we're replacing existing tags, delete the old ones before adding the new ones:
        if ($replaceExisting) {
            $this->resourceTagsService->destroyResourceTagsLinksForUser($resource->getId(), $user, $list);
        }

        // Add the new tags:
        foreach ($tagArray as $tag) {
            $this->tagsService->linkTagToResource($tag, $resource, $user, $list);
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
     * @param UserEntityInterface $user   The user saving the record
     * @param RecordDriver        $driver Record driver for record being saved
     *
     * @return array list information
     */
    public function saveRecordToFavorites(
        array $params,
        UserEntityInterface $user,
        RecordDriver $driver
    ): array {
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
        $this->saveResourceToFavorites(
            $user,
            $resource,
            $list,
            $params['mytags'] ?? [],
            $params['notes'] ?? ''
        );
        return ['listId' => $list->getId()];
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
        if (!$this->userCanEditList($user, $list)) {
            throw new ListPermissionException('list_access_denied');
        }
        if (!$list->getTitle()) {
            throw new MissingFieldException('list_edit_name_required');
        }

        $this->userListService->persistEntity($list);
        $this->rememberLastUsedList($list);
    }

    /**
     * Add a tag to a list.
     *
     * @param string                  $tagText The tag to save.
     * @param UserListEntityInterface $list    The list being tagged.
     * @param UserEntityInterface     $user    The user posting the tag.
     *
     * @return void
     */
    public function addListTag(string $tagText, UserListEntityInterface $list, UserEntityInterface $user): void
    {
        $tagText = trim($tagText);
        if (!empty($tagText)) {
            // Create and link tag in a transaction so we don't accidentally purge it as an orphan:
            $this->resourceTagsService->beginTransaction();
            $tag = $this->tagsService->getOrCreateTagByText($tagText);
            $this->resourceTagsService->createLink(null, $tag, $user, $list);
            $this->resourceTagsService->commitTransaction();
        }
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
        $list->setTitle($request->get('title'))
            ->setDescription($request->get('desc'))
            ->setPublic((bool)$request->get('public'));
        $this->saveListForUser($list, $user);

        if (null !== ($tags = $request->get('tags'))) {
            $this->resourceTagsService->destroyUserListLinks($list, $user);
            foreach ($this->tagsService->parse($tags) as $tag) {
                $this->addListTag($tag, $list, $user);
            }
        }

        return $list->getId();
    }

    /**
     * Is the provided user allowed to edit the provided list?
     *
     * @param ?UserEntityInterface    $user Logged-in user (null if none)
     * @param UserListEntityInterface $list List to check
     *
     * @return bool
     */
    public function userCanEditList(?UserEntityInterface $user, UserListEntityInterface $list): bool
    {
        return $user && $user->getId() === $list->getUser()?->getId();
    }

    /**
     * Support method for saveBulk() -- save a batch of records to the cache.
     *
     * @param array $cacheRecordIds Array of IDs in source|id format
     *
     * @return void
     */
    protected function cacheBatch(array $cacheRecordIds)
    {
        if ($cacheRecordIds && $this->recordCache) {
            // Disable the cache so that we fetch latest versions, not cached ones:
            $this->recordLoader->setCacheContext(RecordCache::CONTEXT_DISABLED);
            $records = $this->recordLoader->loadBatch($cacheRecordIds);
            // Re-enable the cache so that we actually save the records:
            $this->recordLoader->setCacheContext(RecordCache::CONTEXT_FAVORITE);
            foreach ($records as $record) {
                $this->recordCache->createOrUpdate(
                    $record->getUniqueID(),
                    $record->getSourceIdentifier(),
                    $record->getRawData()
                );
            }
        }
    }

    /**
     * Save a group of records to the user's favorites.
     *
     * @param array               $params Array with some or all of these keys:
     *                                    <ul> <li>ids - Array of IDs in
     *                                    source|id format</li> <li>mytags -
     *                                    Unparsed tag string to associate with
     *                                    record (optional)</li> <li>list - ID
     *                                    of list to save record into (omit to
     *                                    create new list)</li> </ul>
     * @param UserEntityInterface $user   The user saving the record
     *
     * @return array list information
     */
    public function saveRecordsToFavorites(array $params, UserEntityInterface $user): array
    {
        // Load helper objects needed for the saving process:
        $list = $this->getAndRememberListObject($this->getListIdFromParams($params), $user);
        $this->recordCache?->setContext(RecordCache::CONTEXT_FAVORITE);

        $cacheRecordIds = [];   // list of record IDs to save to cache
        foreach ($params['ids'] as $current) {
            // Break apart components of ID:
            [$source, $id] = explode('|', $current, 2);

            // Get or create a resource object as needed:
            try {
                $resource = $this->resourcePopulator->getOrCreateResourceForRecordId($id, $source);

                // Add the information to the user's account:
                $tags = isset($params['mytags']) ? $this->tagsService->parse($params['mytags']) : [];
                $this->saveResourceToFavorites($user, $resource, $list, $tags, '', false);

                // Collect record IDs for caching
                if ($this->recordCache?->isCachable($resource->getSource())) {
                    $cacheRecordIds[] = $current;
                }
            } catch (RecordMissing $e) {
                // Record missing, silently skip it.
            }
        }

        $this->cacheBatch($cacheRecordIds);
        return ['listId' => $list->getId()];
    }

    /**
     * Delete a group of favorites.
     *
     * @param string[]            $ids    Array of IDs in source|id format.
     * @param ?int                $listID ID of list to delete from (null for all lists)
     * @param UserEntityInterface $user   Logged in user
     *
     * @return void
     */
    public function deleteFavorites(array $ids, ?int $listID, UserEntityInterface $user): void
    {
        // Sort $ids into useful array:
        $sorted = [];
        foreach ($ids as $current) {
            [$source, $id] = explode('|', $current, 2);
            if (!isset($sorted[$source])) {
                $sorted[$source] = [];
            }
            $sorted[$source][] = $id;
        }

        // Delete favorites one source at a time, using a different object depending
        // on whether we are working with a list or user favorites.
        if (empty($listID)) {
            foreach ($sorted as $source => $ids) {
                $this->removeUserResourcesById($user, $ids, $source);
            }
        } else {
            $list = $this->userListService->getUserListById($listID);
            foreach ($sorted as $source => $ids) {
                $this->removeListResourcesById($list, $user, $ids, $source);
            }
        }
    }

    /**
     * Call TagsService::getUserTagsFromFavorites() and format the results for editing.
     *
     * @param UserEntityInterface|int          $userOrId User ID to look up.
     * @param UserListEntityInterface|int|null $listOrId Filter for tags tied to a specific list (null for no
     * filter).
     * @param ?string                          $recordId Filter for tags tied to a specific resource (null for no
     * filter).
     * @param ?string                          $source   Filter for tags tied to a specific record source (null for
     * no filter).
     *
     * @return string
     */
    public function getTagStringForEditing(
        UserEntityInterface|int $userOrId,
        UserListEntityInterface|int|null $listOrId = null,
        ?string $recordId = null,
        ?string $source = null
    ): string {
        return $this->formatTagStringForEditing(
            $this->tagsService->getUserTagsFromFavorites(
                $userOrId,
                $listOrId,
                $recordId,
                $source
            )
        );
    }

    /**
     * Convert an array representing tags into a string for an edit form
     *
     * @param array $tags Tags
     *
     * @return string
     */
    public function formatTagStringForEditing($tags): string
    {
        $tagStr = '';
        if (count($tags) > 0) {
            foreach ($tags as $tag) {
                if (strstr($tag['tag'], ' ')) {
                    $tagStr .= "\"{$tag['tag']}\" ";
                } else {
                    $tagStr .= "{$tag['tag']} ";
                }
            }
        }
        return trim($tagStr);
    }
}
