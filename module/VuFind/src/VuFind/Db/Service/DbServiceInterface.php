<?php

/**
 * Base interface for VuFind database services.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2024.
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
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */

namespace VuFind\Db\Service;

use Exception;
use VuFind\Db\Entity\EntityInterface;

/**
 * Base interface for VuFind database services.
 *
 * @category VuFind
 * @package  Database
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
interface DbServiceInterface
{
    /**
     * Persist an entity.
     *
     * @param EntityInterface $entity Entity to persist.
     *
     * @return void
     */
    public function persistEntity(EntityInterface $entity): void;

    /**
     * Detach an entity.
     *
     * Makes the Entity Manager stop tracking the entity. This can save memory and work, but also helps when the target
     * entity of this entity with "ON DELETE SET NULL" is going to be deleted.
     *
     * @param EntityInterface $entity Entity to detach.
     *
     * @return void
     */
    public function detachEntity(EntityInterface $entity): void;

    /**
     * Begin a database transaction.
     *
     * @return void
     * @throws Exception
     */
    public function beginTransaction(): void;

    /**
     * Commit a database transaction.
     *
     * @return void
     * @throws Exception
     */
    public function commitTransaction(): void;

    /**
     * Roll back a database transaction.
     *
     * @return void
     * @throws Exception
     */
    public function rollBackTransaction(): void;
}
