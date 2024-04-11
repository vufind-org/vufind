<?php

/**
 * Database service for user.
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
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */

namespace VuFind\Db\Service;

use Doctrine\ORM\EntityManager;
use Laminas\Log\LoggerAwareInterface;
use VuFind\Db\Entity\PluginManager as EntityPluginManager;
use VuFind\Db\Entity\Resource;
use VuFind\Db\Entity\User;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\Entity\UserList;
use VuFind\Log\LoggerAwareTrait;

/**
 * Database service for user.
 *
 * @category VuFind
 * @package  Database
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
class UserService extends AbstractDbService implements
    LoggerAwareInterface,
    DbServiceAwareInterface,
    UserServiceInterface
{
    use LoggerAwareTrait;
    use DbServiceAwareTrait;

    /**
     * Constructor
     *
     * @param EntityManager       $entityManager       Doctrine ORM entity manager
     * @param EntityPluginManager $entityPluginManager VuFind entity plugin manager
     */
    public function __construct(
        EntityManager $entityManager,
        EntityPluginManager $entityPluginManager
    ) {
        parent::__construct($entityManager, $entityPluginManager);
    }

    /**
     * Lookup and return a user.
     *
     * @param int $id ID value.
     *
     * @return ?UserEntityInterface
     */
    public function getUserById(int $id): ?UserEntityInterface
    {
        $user = $this->entityManager->find(
            $this->getEntityClass(\VuFind\Db\Entity\User::class),
            $id
        );
        return $user;
    }

    /**
     * Add/update a resource in the user's account.
     *
     * @param Resource|int $resource        The resource to add/update
     * @param User|int     $user            Logged in user
     * @param UserList|int $list            The list to store the resource in.
     * @param array        $tagArray        An array of tags to associate with the resource.
     * @param string       $notes           User notes about the resource.
     * @param bool         $replaceExisting Whether to replace all existing tags (true) or append to the
     * existing list (false).
     *
     * @return void
     */
    public function saveResource(
        $resource,
        $user,
        $list,
        $tagArray,
        $notes,
        $replaceExisting = true
    ) {
        // Create the resource link if it doesn't exist and update the notes in any case:
        $linkService = $this->getDbService(UserResourceService::class);
        $linkService->createOrUpdateLink($resource, $user, $list, $notes);

        // If we're replacing existing tags, delete the old ones before adding the new ones:
        if ($replaceExisting) {
            $unlinker = $this->getDbService(TagService::class);
            $resourceId = $resource instanceof Resource ? $resource->getId() : $resource;
            $unlinker->destroyResourceLinks($resourceId, $user, $list);
        }

        // Add the new tags:
        foreach ($tagArray as $tag) {
            $tagService = $this->getDbService(TagService::class);
            $tagService->addTag($resource, $tag, $user, $list);
        }
    }

    /**
     * Retrieve a user object from the database based on the given field.
     * Field name must be id, username or cat_id.
     *
     * @param string          $fieldName  Field name
     * @param int|null|string $fieldValue Field value
     *
     * @return ?UserEntityInterface
     */
    public function getUserByField(string $fieldName, int|null|string $fieldValue): ?UserEntityInterface
    {
        $legalFields = ['id', 'username', 'cat_id'];
        if (in_array($fieldName, $legalFields)) {
            $dql = 'SELECT U FROM ' . $this->getEntityClass(User::class) . ' U '
                . 'WHERE U.' . $fieldName . ' = :fieldValue';
            $parameters = compact('fieldValue');
            $query = $this->entityManager->createQuery($dql);
            $query->setParameters($parameters);
            $result = current($query->getResult());
            return $result ?: null;
        }
        throw new \InvalidArgumentException('Field name must be id, username or cat_id');
    }
}
