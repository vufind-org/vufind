<?php

/**
 * Database service for UserList.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2023.
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
 * @package  Database
 * @author   Sudharma Kellampalli <skellamp@villanova.edu>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */

namespace VuFind\Db\Service;

use Doctrine\ORM\EntityManager;
use Laminas\Log\LoggerAwareInterface;
use Laminas\Session\Container;
use VuFind\Db\Entity\PluginManager as EntityPluginManager;
use VuFind\Db\Entity\Resource;
use VuFind\Db\Entity\User;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\Entity\UserList;
use VuFind\Db\Entity\UserListEntityInterface;
use VuFind\Db\Entity\UserResource;
use VuFind\Exception\ListPermission as ListPermissionException;
use VuFind\Exception\MissingField as MissingFieldException;
use VuFind\Exception\RecordMissing as RecordMissingException;
use VuFind\Log\LoggerAwareTrait;
use VuFind\Tags;

/**
 * Database service for UserList.
 *
 * @category VuFind
 * @package  Database
 * @author   Sudharma Kellampalli <skellamp@villanova.edu>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
class UserListService extends AbstractDbService implements
    UserListServiceInterface,
    LoggerAwareInterface,
    DbServiceAwareInterface
{
    use LoggerAwareTrait;
    use DbServiceAwareTrait;

    /**
     * Tag parser.
     *
     * @var Tags
     */
    protected $tagParser;

    /**
     * Session container for last list information.
     *
     * @var Container
     */
    protected $session = null;

    /**
     * Constructor
     *
     * @param EntityManager       $entityManager       Doctrine ORM entity manager
     * @param EntityPluginManager $entityPluginManager VuFind entity plugin manager
     * @param Tags                $tagParser           Tag parser
     * @param Container           $session             Session container
     */
    public function __construct(
        EntityManager $entityManager,
        EntityPluginManager $entityPluginManager,
        Tags $tagParser,
        Container $session = null
    ) {
        parent::__construct($entityManager, $entityPluginManager);
        $this->tagParser = $tagParser;
        $this->session = $session;
    }

    /**
     * Get an array of resource tags associated with the list.
     *
     * @param UserList $list UserList object.
     *
     * @return array
     */
    public function getResourceTags($list)
    {
        $user = $list->getUser();
        $tags = $this->getDbService(TagService::class)
            ->getUserTagsFromFavorites($user, null, $list);
        return $tags;
    }

    /**
     * Create a UserList entity object.
     *
     * @return UserListEntityInterface
     */
    public function createEntity(): UserListEntityInterface
    {
        $class = $this->getEntityClass(UserList::class);
        return new $class();
    }

    /**
     * Retrieve a list object.
     *
     * @param int $id Numeric ID for existing list.
     *
     * @return UserListEntityInterface
     * @throws RecordMissingException
     */
    public function getUserListById(int $id): UserListEntityInterface
    {
        $result = $this->getEntityById(\VuFind\Db\Entity\UserList::class, $id);
        if (empty($result)) {
            throw new RecordMissingException('Cannot load list ' . $id);
        }
        return $result;
    }

    /**
     * Update and save the list object using a request object -- useful for
     * sharing form processing between multiple actions.
     *
     * @param User|int|bool              $user    Logged-in user (false if none)
     * @param UserList                   $list    User list that is being modified
     * @param \Laminas\Stdlib\Parameters $request Request to process
     *
     * @return int ID of newly created list
     * @throws ListPermissionException
     * @throws MissingFieldException
     */
    public function updateFromRequest($user, $list, $request)
    {
        $list->setTitle($request->get('title'))
            ->setDescription($request->get('desc'))
            ->setPublic((bool)$request->get('public'));

        $this->save($list, $user);

        if (null !== ($tags = $request->get('tags'))) {
            $linker = $this->getDbService(TagService::class);
            $linker->destroyListLinks($list, $user);
            foreach ($this->tagParser->parse($tags) as $tag) {
                $this->addListTag($tag, $user, $list);
            }
        }

        return $list->getId();
    }

    /**
     * Saves the properties to the database.
     *
     * This performs an intelligent insert/update, and reloads the
     * properties with fresh data from the table on success.
     *
     * @param UserList  $list UserList to be saved
     * @param User|bool $user Logged-in user (false if none)
     *
     * @return mixed The primary key value(s), as an associative array if the
     *     key is compound, or a scalar if the key is single-column.
     * @throws ListPermissionException
     * @throws MissingFieldException
     */
    public function save(UserList $list, $user = false)
    {
        if (!$list->editAllowed($user ?: null)) {
            throw new ListPermissionException('list_access_denied');
        }
        if (empty($list->getTitle())) {
            throw new MissingFieldException('list_edit_name_required');
        }
        try {
            $this->persistEntity($list);
        } catch (\Exception $e) {
            $this->logError('Could not save list: ' . $e->getMessage());
            return false;
        }
        $this->rememberLastUsed($list);
        return $list->getId();
    }

    /**
     * Add a tag to the list.
     *
     * @param string   $tagText The tag to save.
     * @param User|int $user    The user posting the tag.
     * @param UserList $list    The userlist to tag.
     *
     * @return void
     */
    public function addListTag($tagText, $user, $list)
    {
        $tagText = trim($tagText);
        if (!empty($tagText)) {
            $tagService = $this->getDbService(TagService::class);
            $tag = $tagService->getByText($tagText);
            $tagService->createLink(
                $tag,
                null,
                $user,
                $list
            );
        }
    }

    /**
     * Remember that this list was used so that it can become the default in
     * dialog boxes.
     *
     * @param UserList $list User list to be set as default
     *
     * @return void
     */
    public function rememberLastUsed($list)
    {
        if (null !== $this->session) {
            $this->session->lastUsed = $list->getId();
        }
    }

    /**
     * Get lists containing a specific record.
     *
     * @param string                       $recordId ID of record being checked.
     * @param string                       $source   Source of record to look up
     * @param int|UserEntityInterface|null $user     Optional user ID or entity object (to limit results
     * to a particular user).
     *
     * @return UserListEntityInterface[]
     */
    public function getListsContainingRecord(
        string $recordId,
        string $source = DEFAULT_SEARCH_BACKEND,
        int|UserEntityInterface|null $user = null
    ): array {
        $dql = 'SELECT ul FROM ' . $this->getEntityClass(UserList::class) . ' ul '
            . 'JOIN ' . $this->getEntityClass(UserResource::class) . ' ur WITH ur.list = ul.id '
            . 'JOIN ' . $this->getEntityClass(Resource::class) . ' r WITH r.id = ur.resource '
            . 'WHERE r.recordId = :recordId AND r.source = :source ';

        $parameters = compact('recordId', 'source');
        if (null !== $user) {
            $userId = $user instanceof UserEntityInterface ? $user->getId() : $user;
            $dql .= 'AND ur.user = :userId ';
            $parameters['userId'] = $userId;
        }

        $dql .= 'ORDER BY ul.title';
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters($parameters);
        $results = $query->getResult();
        return $results;
    }

    /**
     * Get public lists.
     *
     * @param array $includeFilter List of list ids to include in result.
     * @param array $excludeFilter List of list entities to exclude from result.
     *
     * @return array
     */
    public function getPublicLists($includeFilter = [], $excludeFilter = [])
    {
        $dql = 'SELECT ul FROM ' . $this->getEntityClass(UserList::class) . ' ul ';

        $parameters = [];
        $where = ["ul.public = '1'"];
        if (!empty($includeFilter)) {
            $where[] = 'ul.id IN (:includeFilter)';
            $parameters['includeFilter'] = $includeFilter;
        }
        if (!empty($excludeFilter)) {
            $where[] = 'ul NOT IN (:excludeFilter)';
            $parameters['excludeFilter'] = $excludeFilter;
        }
        $dql .= 'WHERE ' . implode(' AND ', $where);

        $query = $this->entityManager->createQuery($dql);
        $query->setParameters($parameters);
        $results = $query->getResult();
        return $results;
    }

    /**
     * Get all of the lists associated with this user.
     *
     * @param User|int $user User object or ID representing the user owning the list.
     *
     * @return array
     */
    public function getListsForUser($user)
    {
        $dql = 'SELECT ul, COUNT(DISTINCT(ur.resource)) AS cnt '
            . 'FROM ' . $this->getEntityClass(UserList::class) . ' ul '
            . 'LEFT JOIN ' . $this->getEntityClass(UserResource::class) . ' ur WITH ur.list = ul.id '
            . 'WHERE ul.user = :user '
            . 'GROUP BY ul '
            . 'ORDER BY ul.title';

        $parameters = compact('user');
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters($parameters);
        $results = $query->getResult();
        return $results;
    }

    /**
     * Given an array of item ids, remove them from all lists
     *
     * @param User|int|bool $user   Logged-in user (false if none)
     * @param UserList      $list   Userlist to remove records
     * @param array         $ids    IDs to remove from the list
     * @param string        $source Type of resource identified by IDs
     *
     * @return void
     */
    public function removeResourcesById(
        $user,
        UserList $list,
        array $ids,
        string $source = DEFAULT_SEARCH_BACKEND
    ): void {
        if ($user) {
            $user = $this->getDoctrineReference(User::class, $user);
        }
        if (!$list->editAllowed($user ?: null)) {
            throw new ListPermissionException('list_access_denied');
        }

        // Retrieve a list of resource IDs:
        $resources = $this->getDbService(ResourceServiceInterface::class)->getResourcesByRecordIds($ids, $source);

        $resourceIDs = [];
        foreach ($resources as $current) {
            $resourceIDs[] = $current->getId();
        }

        // Remove Resource (related tags are also removed implicitly)
        $userResourceService = $this->getDbService(UserResourceServiceInterface::class);
        $userResourceService->destroyLinks(
            $user,
            $resourceIDs,
            $list
        );
    }

    /**
     * Destroy the list.
     *
     * @param UserList      $list  Userlist to destroy
     * @param User|int|bool $user  Logged-in user (false if none)
     * @param bool          $force Should we force the delete without checking permissions?
     *
     * @return int The number of rows deleted.
     */
    public function delete($list, $user = false, $force = false)
    {
        if ($user) {
            $user = $this->getDoctrineReference(User::class, $user);
        }
        if (!$force && !$list->editAllowed($user ?: null)) {
            throw new ListPermissionException('list_access_denied');
        }

        // Remove user_resource and resource_tags rows:
        $userResourceService = $this->getDbService(UserResourceService::class);
        $userResourceService->destroyLinks(
            $user,
            null,
            $list
        );

        // Remove resource_tags rows for list tags:
        $linker = $this->getDbService(TagService::class);
        $linker->destroyListLinks($list, $user);

        // Remove the list itself:
        try {
            $this->deleteEntity($list);
        } catch (\Exception $e) {
            $this->logError('Could not delete UserCard: ' . $e->getMessage());
            return 0;
        }
        return 1;
    }

    /**
     * Retrieve a batch of list objects corresponding to the provided IDs
     *
     * @param array $ids List ids.
     *
     * @return array
     */
    public function getListsById($ids)
    {
        $dql = 'SELECT ul FROM ' . $this->getEntityClass(UserList::class) . ' ul '
            . 'WHERE ul.id IN (:ids)';
        $parameters = compact('ids');
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters($parameters);
        $results = $query->getResult();
        return $results;
    }
}
