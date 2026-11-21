<?php

/**
 * Database service for session_data.
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

use VuFind\Db\Entity\SessionDataEntityInterface;

/**
 * Database service for session_data.
 *
 * @category VuFind
 * @package  Database
 * @author   Thomas Wagener <wagener@hebis.uni-frankfurt.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
class SessionDataService extends AbstractSessionRelatedDataService implements SessionDataServiceInterface
{
    /**
     * Get class name of entity managed by the service.
     *
     * @return string
     */
    protected function getEntityClass(): string
    {
        return SessionDataEntityInterface::class;
    }

    /**
     * Create an entity.
     *
     * @return SessionDataEntityInterface
     */
    public function createEntity(): SessionDataEntityInterface
    {
        return $this->entityPluginManager->get(SessionDataEntityInterface::class);
    }

    /**
     * Get by type and current session.
     *
     * @param string $type Type
     *
     * @return ?SessionDataEntityInterface
     */
    public function getByType(string $type): ?SessionDataEntityInterface
    {
        $queryBuilder = $this->entityManager->createQueryBuilder();
        $queryBuilder->select('sd')
            ->from(SessionDataEntityInterface::class, 'sd')
            ->where('sd.sessionId = :sessionId AND sd.type = :type')
            ->setParameter('sessionId', $this->sessionManager->getId())
            ->setParameter('type', $type);
        $query = $queryBuilder->getQuery();
        return $query->getResult()[0] ?? null;
    }
}
