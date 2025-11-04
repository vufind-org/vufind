<?php

/**
 * Database service for UserResource.
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
use Finna\Db\Entity\UserEntityInterface;
use Finna\Db\Entity\UserListEntityInterface;
use Finna\Db\Entity\UserResourceEntityInterface;
use VuFind\Db\Entity\ResourceEntityInterface;
use VuFind\Db\Entity\UserEntityInterface as VuFindUserEntityInterface;
use VuFind\Db\Entity\UserListEntityInterface as VuFindUserListEntityInterface;

use function assert;

/**
 * Database service for UserResource.
 *
 * @category VuFind
 * @package  Database
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
class UserResourceService extends \VuFind\Db\Service\UserResourceService implements UserResourceServiceInterface
{
    /**
     * Get total resource count for a user
     *
     * @param UserEntityInterface $user User
     *
     * @return int
     */
    public function getTotalResourceCount(UserEntityInterface $user): int
    {
        $dql = 'SELECT COUNT(DISTINCT(ur.resource)) FROM ' . UserResourceEntityInterface::class . ' ur'
            . ' WHERE ur.user = :user';

        $query = $this->entityManager->createQuery($dql);
        $query->setParameters(compact('user'));
        return $query->getSingleScalarResult();
    }

    /**
     * Create user/resource/list link if one does not exist; update notes if one does.
     *
     * Finna: updates custom order as well.
     *
     * @param ResourceEntityInterface|int $resourceOrId Entity or ID of resource to link up
     * @param UserEntityInterface|int     $userOrId     Entity or ID of user creating link
     * @param UserListEntityInterface|int $listOrId     Entity or ID of list to link up
     * @param string                      $notes        Notes to associate with link
     * @param ?int                        $order        Custom order
     *
     * @return UserResource|false
     */
    public function createOrUpdateLinkWithOrder(
        ResourceEntityInterface|int $resourceOrId,
        UserEntityInterface|int $userOrId,
        UserListEntityInterface|int $listOrId,
        string $notes = '',
        ?int $order = null
    ): UserResourceEntityInterface {
        $resource = $this->getDoctrineReference(ResourceEntityInterface::class, $resourceOrId);
        $user = $this->getDoctrineReference(UserEntityInterface::class, $userOrId);
        $list = $this->getDoctrineReference(UserListEntityInterface::class, $listOrId);
        $params = compact('resource', 'list', 'user');
        $result = current($this->entityManager->getRepository(UserResourceEntityInterface::class)
            ->findBy($params));

        if (empty($result)) {
            $result = $this->createEntity()
                ->setResource($resource)
                ->setUser($user)
                ->setUserList($list);
        }
        // Update the notes:
        $result->setNotes($notes);
        // Update Finna custom order:
        assert($result instanceof UserResourceEntityInterface);
        $result->setFinnaCustomOrderIndex($order);
        $this->persistEntity($result);

        // Update list date:
        $list->setFinnaUpdated(new DateTime());
        $this->persistEntity($list);

        return $result;
    }

    /**
     * Unlink rows for the specified resource.
     *
     * @param int|int[]|null              $resourceId ID (or array of IDs) of resource(s) to unlink (null for ALL
     * matching resources)
     * @param UserEntityInterface|int     $userOrId   ID or entity representing user removing links
     * @param UserListEntityInterface|int $listOrId   ID or entity representing list to unlink (null for ALL
     * matching lists)
     *
     * @return void
     */
    public function unlinkFavorites(
        int|array|null $resourceId,
        VuFindUserEntityInterface|int $userOrId,
        VuFindUserListEntityInterface|int|null $listOrId = null
    ): void {
        parent::unlinkFavorites($resourceId, $userOrId, $listOrId);
        if (null !== $listOrId) {
            $list = $this->getDoctrineReference(UserListEntityInterface::class, $listOrId);
            // Update list date:
            $list->setFinnaUpdated(new DateTime());
            $this->persistEntity($list);
        }
    }
}
