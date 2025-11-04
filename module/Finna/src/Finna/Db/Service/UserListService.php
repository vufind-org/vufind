<?php

/**
 * Database service for UserList.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2024-2025.
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  Database
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */

namespace Finna\Db\Service;

use DateTime;
use Finna\Db\Entity\UserList;
use Finna\Db\Entity\UserListEntityInterface;
use Finna\Db\Entity\UserResourceEntityInterface;
use VuFind\Db\Entity\EntityInterface;
use VuFind\Db\Entity\UserEntityInterface;

use function assert;

/**
 * Database service for UserList.
 *
 * @category VuFind
 * @package  Database
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
class UserListService extends \VuFind\Db\Service\UserListService implements UserListServiceInterface
{
    /**
     * Check if custom favorite order is used in a list
     *
     * @param UserListEntityInterface $list List entity.
     *
     * @return bool
     */
    public function isCustomOrderAvailable(UserListEntityInterface $list)
    {
        $dql = 'SELECT ur FROM ' . UserResourceEntityInterface::class . ' ur'
            . ' WHERE ur.list = :list AND ur.finnaCustomOrderIndex IS NOT NULL';
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters(compact('list'));
        $query->setMaxResults(1);
        return $query->getOneOrNullResult() !== null;
    }

    /**
     * Get next available custom order index
     *
     * @param UserListEntityInterface $list List entity.
     *
     * @return int Next available index or zero if custom order is not used or list is empty
     */
    public function getNextAvailableCustomOrderIndex(UserListEntityInterface $list)
    {
        $dql = 'SELECT MAX(ur.finnaCustomOrderIndex) FROM ' . UserResourceEntityInterface::class . ' ur'
            . ' WHERE ur.list = :list';
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters(compact('list'));
        $result = $query->getOneOrNullResult();
        return null === $result ? 0 : $result + 1;
    }

    /**
     * Retrieve user's list object by title.
     *
     * @param UserEntityInterface|int $userOrId User entity or ID.
     * @param string                  $title    Title of the list to retrieve
     *
     * @return ?UserListEntityInterface
     */
    public function getListByTitle(UserEntityInterface|int $userOrId, string $title): ?UserListEntityInterface
    {
        $user = $this->getDoctrineReference(UserEntityInterface::class, $userOrId);
        return $this->entityManager->getRepository(UserListEntityInterface::class)->findOneBy(compact('user', 'title'));
    }

    /**
     * Get lists belonging to the user and their count. Returns an array of arrays with
     * list_entity and count keys.
     *
     * @param UserEntityInterface|int $userOrId User entity object or ID
     * @param string|string[]         $types    Types of user lists to get. Set to an empty array to get all.
     *
     * @return array
     * @throws Exception
     */
    public function getUserListsAndCountsByUser(
        UserEntityInterface|int $userOrId,
        string|array $types = [UserList::TYPE_DEFAULT]
    ): array {
        $lists = parent::getUserListsAndCountsByUser($userOrId, $types);

        // Sort lists by id
        $listsSorted = [];
        foreach ($lists as $l) {
            $listsSorted[$l['list_entity']->getId()] = $l;
        }
        ksort($listsSorted);

        return array_values($listsSorted);
    }

    /**
     * Update custom favorite list order
     *
     * @param UserEntityInterface $user        User id
     * @param int                 $listId      List id
     * @param array               $orderedList Ordered List of Resources
     *
     * @return void
     */
    public function saveCustomFavoriteOrder(UserEntityInterface $user, int $listId, array $orderedList): void
    {
        $recordIndex = array_flip(array_values($orderedList));
        $list = $this->getUserListById($listId);

        $dql = 'SELECT ur, r FROM ' . UserResourceEntityInterface::class . ' ur'
            . ' JOIN ur.resource r'
            . ' WHERE ur.user = :user AND ur.list = :list';

        $query = $this->entityManager->createQuery($dql);
        $query->setParameters(compact('user', 'list'));
        foreach ($query->getResult() as $userResource) {
            $recordId = $userResource->getResource()->getRecordId();
            $userResource->setFinnaCustomOrderIndex($recordIndex[$recordId] ?? null);
            $this->entityManager->persist($userResource);
        }
        $this->entityManager->flush();
    }

    /**
     * Retrieve protected lists.
     *
     * @return UserListEntityInterface[]
     */
    public function getProtectedLists(): array
    {
        return $this->entityManager->getRepository(UserListEntityInterface::class)->findBy(['finnaProtected' => true]);
    }

    /**
     * Persist an entity.
     *
     * @param EntityInterface $entity Entity to persist.
     *
     * @return void
     */
    public function persistEntity(EntityInterface $entity): void
    {
        assert($entity instanceof UserListEntityInterface);
        $entity->setFinnaUpdated(new DateTime());
        parent::persistEntity($entity);
    }
}
