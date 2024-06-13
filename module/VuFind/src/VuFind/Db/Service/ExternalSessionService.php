<?php

/**
 * Database service for external_session table.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2024.
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */

namespace VuFind\Db\Service;

use DateTime;
use VuFind\Db\Entity\ExternalSessionEntityInterface;
use VuFind\Db\Table\DbTableAwareInterface;
use VuFind\Db\Table\DbTableAwareTrait;

/**
 * Database service for external_session table.
 *
 * @category VuFind
 * @package  Database
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
class ExternalSessionService extends AbstractDbService implements ExternalSessionServiceInterface, DbTableAwareInterface
{
    use DbTableAwareTrait;

    /**
     * Create a new external session entity.
     *
     * @return ExternalSessionEntityInterface
     */
    public function createEntity(): ExternalSessionEntityInterface
    {
        return $this->getDbTable('ExternalSession')->createRow();
    }

    /**
     * Add a mapping between local and external session id's; return the newly-created entity.
     *
     * @param string $localSessionId    Local (VuFind) session id
     * @param string $externalSessionId External session id
     *
     * @return ExternalSessionEntityInterface
     */
    public function addSessionMapping(
        string $localSessionId,
        string $externalSessionId
    ): ExternalSessionEntityInterface {
        $this->destroySession($localSessionId);
        $row = $this->createEntity()
            ->setSessionId($localSessionId)
            ->setExternalSessionId($externalSessionId)
            ->setCreated(new DateTime());
        $this->persistEntity($row);
        return $row;
    }

    /**
     * Retrieve an object from the database based on an external session ID
     *
     * @param string $sid External session ID to retrieve
     *
     * @return ?ExternalSessionEntityInterface
     */
    public function getExternalSessionByExternalSessionId(string $sid): ?ExternalSessionEntityInterface
    {
        return $this->getDbTable('ExternalSession')->select(['external_session_id' => $sid])->current();
    }

    /**
     * Destroy data for the given session ID.
     *
     * @param string $sid Session ID to erase
     *
     * @return void
     */
    public function destroySession(string $sid): void
    {
        $this->getDbTable('ExternalSession')->delete(['session_id' => $sid]);
    }
}
