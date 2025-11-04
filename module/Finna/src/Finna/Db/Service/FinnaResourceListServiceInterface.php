<?php

/**
 * Resource list service interface
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
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */

namespace Finna\Db\Service;

use Finna\Db\Entity\FinnaResourceListEntityInterface;
use VuFind\Db\Entity\ResourceEntityInterface;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\Service\DbServiceInterface;

/**
 * Resource list service interface
 *
 * @category VuFind
 * @package  Database
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
interface FinnaResourceListServiceInterface extends DbServiceInterface
{
    /**
     * Create a FinnaResourceList entity object.
     *
     * @return FinnaResourceListEntityInterface
     */
    public function createEntity(): FinnaResourceListEntityInterface;

    /**
     * Delete a resource list entity.
     *
     * @param FinnaResourceListEntityInterface $list List entity
     *
     * @return void
     */
    public function deleteResourceList(FinnaResourceListEntityInterface $list): void;

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
    ): array;

    /**
     * Retrieve a list object.
     *
     * @param int $id Numeric ID for existing list.
     *
     * @return FinnaResourceListEntityInterface
     * @throws RecordMissingException
     */
    public function getResourceListById(int $id): FinnaResourceListEntityInterface;

    /**
     * Get resource lists for user
     *
     * @param UserEntityInterface $user           User entity object
     * @param string              $listIdentifier Identifier of the list used by institution
     * @param string              $institution    Institution name saved in details
     * @param ?string             $listType       List type to retrieve settings for or omit for all
     *
     * @return array
     */
    public function getResourceListsForUser(
        UserEntityInterface $user,
        string $listIdentifier = '',
        string $institution = '',
        ?string $listType = null
    ): array;

    /**
     * Get lists which does not contain given resource
     *
     * @param UserEntityInterface     $user           User entity object
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
    ): array;
}
