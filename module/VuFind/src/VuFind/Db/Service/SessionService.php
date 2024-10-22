<?php

/**
 * Database service for Session.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2023.
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
 * @author   Sudharma Kellampalli <skellamp@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */

namespace VuFind\Db\Service;

use DateTime;
use VuFind\Db\Entity\SessionEntityInterface;
use VuFind\Db\Table\DbTableAwareInterface;
use VuFind\Db\Table\DbTableAwareTrait;

/**
 * Database service for Session.
 *
 * @category VuFind
 * @package  Database
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Sudharma Kellampalli <skellamp@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
class SessionService extends AbstractDbService implements
    DbTableAwareInterface,
    SessionServiceInterface,
    Feature\DeleteExpiredInterface
{
    use DbTableAwareTrait;

    /**
     * Retrieve an object from the database based on session ID; create a new
     * row if no existing match is found.
     *
     * @param string $sid    Session ID to retrieve
     * @param bool   $create Should we create rows that don't already exist?
     *
     * @return ?SessionEntityInterface
     */
    public function getSessionById(string $sid, bool $create = true): ?SessionEntityInterface
    {
        return $this->getDbTable('Session')->getBySessionId($sid, $create);
    }

    /**
     * Retrieve data for the given session ID.
     *
     * @param string $sid      Session ID to retrieve
     * @param int    $lifetime Session lifetime (in seconds)
     *
     * @throws SessionExpiredException
     * @return string     Session data
     */
    public function readSession(string $sid, int $lifetime): string
    {
        return $this->getDbTable('Session')->readSession($sid, $lifetime);
    }

    /**
     * Store data for the given session ID.
     *
     * @param string $sid  Session ID to retrieve
     * @param string $data Data to store
     *
     * @return bool
     */
    public function writeSession(string $sid, string $data): bool
    {
        $this->getDbTable('Session')->writeSession($sid, $data);
        return true;
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
        $this->getDbTable('Session')->destroySession($sid);
    }

    /**
     * Garbage collect expired sessions. Returns number of deleted rows.
     *
     * @param int $maxLifetime Maximum session lifetime.
     *
     * @return int
     */
    public function garbageCollect(int $maxLifetime): int
    {
        return $this->getDbTable('Session')->garbageCollect($maxLifetime);
    }

    /**
     * Create a session entity object.
     *
     * @return SessionEntityInterface
     */
    public function createEntity(): SessionEntityInterface
    {
        return $this->getDbTable('Session')->createRow();
    }

    /**
     * Delete expired records. Allows setting a limit so that rows can be deleted in small batches.
     *
     * @param DateTime $dateLimit Date threshold of an "expired" record.
     * @param ?int     $limit     Maximum number of rows to delete or null for no limit.
     *
     * @return int Number of rows deleted
     */
    public function deleteExpired(DateTime $dateLimit, ?int $limit = null): int
    {
        return $this->getDbTable('Session')->deleteExpired($dateLimit->format('Y-m-d H:i:s'), $limit);
    }
}
