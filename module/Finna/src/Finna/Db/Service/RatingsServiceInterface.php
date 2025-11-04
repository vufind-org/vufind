<?php

/**
 * Database service interface for ratings.
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
 * @package  Database
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */

namespace Finna\Db\Service;

use Finna\Db\Entity\RatingsEntityInterface;
use VuFind\Db\Entity\ResourceEntityInterface;
use VuFind\Db\Entity\UserEntityInterface;

/**
 * Database service interface for ratings.
 *
 * @category VuFind
 * @package  Database
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways interface
 */
interface RatingsServiceInterface extends \VuFind\Db\Service\RatingsServiceInterface
{
    /**
     * Create an entity object.
     *
     * @return RatingsEntityInterface
     */
    public function createEntity(): RatingsEntityInterface;

    /**
     * Get a batch of entities.
     *
     * @param ?int $lastId    ID of last retrieved entity, or null to start from beginning
     * @param int  $batchSize Batch size
     *
     * @return RatingsEntityInterface[]
     */
    public function getEntityBatch(?int $lastId, int $batchSize): array;

    /**
     * Get a rating by resource and user.
     *
     * @param ResourceEntityInterface $resource Resource
     * @param UserEntityInterface     $user     User
     *
     * @return ?RatingsEntityInterface
     */
    public function getByResourceAndUser(
        ResourceEntityInterface $resource,
        UserEntityInterface $user
    ): ?RatingsEntityInterface;
}
