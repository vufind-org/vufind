<?php

/**
 * Resource list service
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
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Db_Service
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */

namespace Finna\Db\Service;

use Finna\Db\Entity\FinnaResourceList;
use Finna\Db\Entity\FinnaResourceListEntityInterface;
use Finna\Db\Entity\FinnaResourceListResourceEntityInterface;
use Psr\Log\LoggerAwareInterface;
use VuFind\Db\Entity\ResourceEntityInterface;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\Service\AbstractDbService;
use VuFind\Db\Service\DbServiceAwareInterface;
use VuFind\Db\Service\DbServiceAwareTrait;
use VuFind\Exception\RecordMissing as RecordMissingException;
use VuFind\Log\LoggerAwareTrait;

/**
 * Resource list service
 *
 * @category VuFind
 * @package  Db_Service
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class FinnaResourceListService extends AbstractDbService implements
    FinnaResourceListServiceInterface,
    LoggerAwareInterface,
    DbServiceAwareInterface
{
    use LoggerAwareTrait;
    use DbServiceAwareTrait;

    /**
     * Create a FinnaResourceList entity object.
     *
     * @return FinnaResourceListEntityInterface
     */
    public function createEntity(): FinnaResourceListEntityInterface
    {
        return $this->entityPluginManager->get(FinnaResourceListEntityInterface::class);
    }

    /**
     * Delete a resource list entity.
     *
     * @param FinnaResourceListEntityInterface $list List entity
     *
     * @return void
     */
    public function deleteResourceList(FinnaResourceListEntityInterface $list): void
    {
        $this->deleteEntity($list);
    }

    /**
     * Get lists which does contain given resource
     *
     * @param UserEntityInterface     $user           User entity object or ID
     * @param ResourceEntityInterface $resource       Resource entity to look for
     * @param string                  $listIdentifier Identifier of the list used by institution
     * @param string                  $institution    Institution name
     * @param ?string                 $listType       List type to retrieve settings for or omit for all
     *
     * @return array
     */
    public function getListsContainingResource(
        UserEntityInterface $user,
        ResourceEntityInterface $resource,
        string $listIdentifier = '',
        string $institution = '',
        ?string $listType = null
    ): array {
        $dql = 'SELECT frl FROM ' . FinnaResourceListEntityInterface::class . ' frl '
            . 'JOIN ' . FinnaResourceListResourceEntityInterface::class . ' frlr WITH frlr.list = frl.id '
            . 'JOIN ' . ResourceEntityInterface::class . ' r WITH r.id = frlr.resource '
            . 'WHERE r.recordId = :recordId AND frl.user = :user '
            . 'ORDER BY frl.title';

        $parameters = [
            'user' => $user,
            'recordId' => $resource->getRecordId(),
        ];
        $query = $this->entityManager->createQuery($dql);
        $query->setParameters($parameters);
        return $query->getResult();
    }

    /**
     * Retrieve a list object.
     *
     * @param int $id Numeric ID for existing list.
     *
     * @return FinnaResourceListEntityInterface
     * @throws RecordMissingException
     */
    public function getResourceListById(int $id): FinnaResourceListEntityInterface
    {
        $result = $this->getEntityById(FinnaResourceList::class, $id);
        if (empty($result)) {
            throw new RecordMissingException('Cannot load reservation list ' . $id);
        }
        return $result;
    }

    /**
     * Get resource lists for user
     *
     * @param UserEntityInterface $user           User entity object or ID
     * @param string              $listIdentifier Identifier of the list used by institution
     * @param string              $institution    Institution name
     * @param ?string             $listType       List type to retrieve settings for or omit for all
     *
     * @return array
     */
    public function getResourceListsForUser(
        UserEntityInterface $user,
        string $listIdentifier = '',
        string $institution = '',
        ?string $listType = null
    ): array {
        $dql = 'SELECT frl AS list_entity, COUNT(DISTINCT(frlr.resource)) AS count '
            . 'FROM ' . FinnaResourceListEntityInterface::class . ' frl '
            . 'LEFT JOIN ' . FinnaResourceListResourceEntityInterface::class . ' frlr WITH frlr.list = frl.id '
            . 'WHERE frl.user = :user';

        $parameters = compact('user');

        if ($listIdentifier) {
            $dql .= ' AND frl.listConfigIdentifier = :listConfigIdentifier';
            $parameters['listConfigIdentifier'] = $listIdentifier;
        }
        if ($institution) {
            $dql .= ' AND frl.institution = :institution';
            $parameters['institution'] = $institution;
        }
        if ($listType) {
            $dql .= ' AND frl.listType = :listType';
            $parameters['listType'] = $listType;
        }

        $dql .= ' GROUP BY frl'
            . ' ORDER BY frl.title';

        $query = $this->entityManager->createQuery($dql);
        $query->setParameters($parameters);
        return $query->getResult();
    }

    /**
     * Get lists which does not contain given resource
     *
     * @param UserEntityInterface     $user           User entity object or ID
     * @param ResourceEntityInterface $resource       Resource entity to look for
     * @param string                  $listIdentifier Identifier of the list used by institution
     * @param string                  $institution    Institution name
     * @param ?string                 $listType       List type to retrieve settings for or omit for all
     *
     * @return array
     */
    public function getListsNotContainingResource(
        UserEntityInterface $user,
        ResourceEntityInterface $resource,
        string $listIdentifier = '',
        string $institution = '',
        ?string $listType = null
    ): array {
        $containingLists = $this->getListsContainingResource(
            $user,
            $resource,
            $listIdentifier,
            $institution,
            $listType
        );
        if (!$containingLists) {
            return $containingLists;
        }
        $listIds = array_map(
            fn ($relation) => $relation->getListId(),
            $containingLists
        );

        $parameters = [
            'userId' => $user->getId(),
            'listsContaining' => $listIds,
        ];

        $dql = 'SELECT frl FROM ' . FinnaResourceListEntityInterface::class . ' frl '
            . 'WHERE frl.id NOT IN (:listsContaining) AND frl.user = :userId';

        if ($listIdentifier) {
            $dql .= ' AND frl.listConfigIdentifier = :listConfigIdentifier';
            $parameters['listConfigIdentifier'] = $listIdentifier;
        }
        if ($institution) {
            $dql .= ' AND frl.institution = :institution';
            $parameters['institution'] = $institution;
        }
        if ($listType) {
            $dql .= ' AND frl.listType = :listType';
            $parameters['listType'] = $listType;
        }
        $dql .= ' ORDER BY frl.title';

        $query = $this->entityManager->createQuery($dql);
        $query->setParameters($parameters);
        return $query->getResult();
    }
}
