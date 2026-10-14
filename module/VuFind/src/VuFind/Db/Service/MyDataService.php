<?php

/**
 * Database service for my_data.
 *
 * PHP version 8
 *
 * Copyright (C) Hebis Verbundzentrale 2026.
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
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */

namespace VuFind\Db\Service;

use VuFind\Db\Entity\MyDataEntityInterface;

/**
 * Database service for my_data.
 *
 * @category VuFind
 * @package  Database
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
class MyDataService extends AbstractSessionDataService implements MyDataServiceInterface
{
    /**
     * Get class name of entity managed by the service.
     *
     * @return string
     */
    protected function getEntityClass(): string
    {
        return MyDataEntityInterface::class;
    }

    /**
     * Create an entity.
     *
     * @return MyDataEntityInterface
     */
    public function createEntity(): MyDataEntityInterface
    {
        return $this->entityPluginManager->get(MyDataEntityInterface::class);
    }

    /**
     * Get an entity by ID.
     *
     * @param int $id ID
     *
     * @return ?MyDataEntityInterface
     */
    public function getById(int $id): ?MyDataEntityInterface
    {
        return $this->entityManager->find(MyDataEntityInterface::class, $id);
    }

    /**
     * Delete an entity by ID.
     *
     * @param int $id ID
     *
     * @return void
     */
    public function deleteById(int $id): void
    {
        $dql = 'DELETE FROM ' . MyDataEntityInterface::class . ' e'
            . ' WHERE e.id = :id';
        $query = $this->entityManager->createQuery($dql);
        $query->setParameter('id', $id);
        $query->execute();
    }
}
