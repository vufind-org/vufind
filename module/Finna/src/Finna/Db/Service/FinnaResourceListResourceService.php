<?php

/**
 * Finna resource list resource service.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2025.
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
 * @package  Db_Service
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace Finna\Db\Service;

use Finna\Db\Entity\FinnaResourceListEntityInterface;
use Finna\Db\Entity\FinnaResourceListResourceEntityInterface;
use Psr\Log\LoggerAwareInterface;
use VuFind\Db\Entity\ResourceEntityInterface;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\Service\AbstractDbService;
use VuFind\Db\Service\DbServiceAwareInterface;
use VuFind\Db\Service\DbServiceAwareTrait;
use VuFind\Log\LoggerAwareTrait;

/**
 * Finna resource list resource service.
 *
 * @category VuFind
 * @package  Database
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class FinnaResourceListResourceService extends AbstractDbService implements
    FinnaResourceListResourceServiceInterface,
    LoggerAwareInterface,
    DbServiceAwareInterface
{
    use LoggerAwareTrait;
    use DbServiceAwareTrait;

    /**
     * Create user/resource/list link if one does not exist; update notes if one does.
     *
     * @param ResourceEntityInterface          $resource Entity
     * @param UserEntityInterface              $user     Entity
     * @param FinnaResourceListEntityInterface $list     Entity
     * @param string                           $notes    Notes to associate with link
     *
     * @return FinnaResourceListResourceEntityInterface
     */
    public function createOrUpdateLink(
        ResourceEntityInterface $resource,
        UserEntityInterface $user,
        FinnaResourceListEntityInterface $list,
        string $notes = ''
    ): FinnaResourceListResourceEntityInterface {
        $result = $this->entityManager->getRepository(FinnaResourceListResourceEntityInterface::class)
            ->findOneBy(compact('resource', 'user', 'list'));

        if (null === $result) {
            $result = $this->createEntity()
                ->setResource($resource)
                ->setUser($user)
                ->setResourceList($list);
        }
        // Update the notes if not empty value:
        if ($notes) {
            $result->setNotes($notes);
        }
        $this->persistEntity($result);
        return $result;
    }

    /**
     * Unlink rows for the specified resource.
     *
     * @param int|int[]|null                   $resourceId ID (or array of IDs) of resource(s) to unlink (null for ALL
     *                                                     matching resources)
     * @param UserEntityInterface              $user       User entity
     * @param FinnaResourceListEntityInterface $list       List entity
     *
     * @return void
     */
    public function unlinkResources(
        int|array|null $resourceId,
        UserEntityInterface $user,
        FinnaResourceListEntityInterface $list
    ): void {
        $dql = 'DELETE FROM ' . FinnaResourceListResourceEntityInterface::class . ' frlr '
            . 'WHERE frlr.resource IN (:resource_id) AND frlr.list = :list';
        $parameters = [
            'user' => $$user,
            'resource' => (array)($resourceId ?? []),
            'list' => $list,
        ];
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters($parameters);
        $query->execute();
    }

    /**
     * Create a FinnaResourceListResource entity object.
     *
     * @return FinnaResourceListResourceEntityInterface
     */
    public function createEntity(): FinnaResourceListResourceEntityInterface
    {
        return $this->entityPluginManager->get(FinnaResourceListResourceEntityInterface::class);
    }

    /**
     * Change all matching rows to use the new resource ID instead of the old one (called when an ID changes).
     *
     * @param int $old Original resource ID
     * @param int $new New resource ID
     *
     * @return void
     */
    public function changeResourceId(int $old, int $new): void
    {
        $dql = 'UPDATE ' . FinnaResourceListResourceEntityInterface::class . ' e '
            . 'SET e.resource = :new WHERE e.resource = :old';
        $parameters = compact('new', 'old');
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters($parameters);
        $query->execute();
    }

    /**
     * Get resources for a resource list
     *
     * @param UserEntityInterface              $user   User entity
     * @param FinnaResourceListEntityInterface $list   List entity
     * @param string|null                      $sort   Sort order
     * @param int                              $offset Offset
     * @param int                              $limit  Limit
     *
     * @return array
     */
    public function getResourcesForList(
        UserEntityInterface $user,
        FinnaResourceListEntityInterface $list,
        ?string $sort = null,
        int $offset = 0,
        int $limit = -1
    ): array {
        $dql = 'SELECT DISTINCT frlr FROM ' . FinnaResourceListResourceEntityInterface::class . ' frlr '
            . 'JOIN ' . ResourceEntityInterface::class . ' r WITH r.id = frlr.resource '
            . 'WHERE frlr.user = :user AND frlr.list = :list';

        $parameters = compact('user', 'list');

        $query = $this->entityManager->createQuery($dql);
        $query->setParameters($parameters);
        return $query->getResult();
    }
}
