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

use Laminas\Log\LoggerAwareInterface;
use VuFind\Db\Entity\SessionEntityInterface;
use VuFind\Exception\SessionExpired as SessionExpiredException;
use VuFind\Log\LoggerAwareTrait;

use function intval;

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
class SessionService extends AbstractDbService implements LoggerAwareInterface
{
    use LoggerAwareTrait;

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
        $queryBuilder = $this->entityManager->createQueryBuilder();
        $queryBuilder->select('s')
            ->from($this->getEntityClass(SessionEntityInterface::class), 's')
            ->where('s.sessionId = :sid')
            ->setParameter('sid', $sid);
        $query = $queryBuilder->getQuery();
        $session = current($query->getResult()) ?: null;
        if ($create && empty($session)) {
            $now = new \DateTime();
            $session = $this->createEntity()
                ->setSessionId($sid)
                ->setCreated($now);
            try {
                $this->persistEntity($session);
            } catch (\Exception $e) {
                $this->logError('Could not save session: ' . $e->getMessage());
                return null;
            }
        }
        return $session;
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
    public function readSession($sid, $lifetime)
    {
        $s = $this->getSessionById($sid);
        if (!$s) {
            throw new SessionExpiredException("Cannot read session $sid");
        }
        $lastused = $s->getLastUsed();
        // enforce lifetime of this session data
        if (!empty($lastused) && $lastused + $lifetime <= time()) {
            throw new SessionExpiredException('Session expired!');
        }

        // if we got this far, session is good -- update last access time, save
        // changes, and return data.
        $s->setLastUsed(time());
        try {
            $this->persistEntity($s);
        } catch (\Exception $e) {
            $this->logError('Could not save session: ' . $e->getMessage());
            return '';
        }
        $data = $s->getData();
        return $data ?? '';
    }

    /**
     * Store data for the given session ID.
     *
     * @param string $sid  Session ID to retrieve
     * @param string $data Data to store
     *
     * @return bool
     */
    public function writeSession($sid, $data)
    {
        $session = $this->getSessionById($sid);
        try {
            if (!$session) {
                throw new \Exception("cannot read id $sid");
            }
            $session->setLastUsed(time())
                ->setData($data);
            $this->persistEntity($session);
        } catch (\Exception $e) {
            $this->logError('Could not save session data: ' . $e->getMessage());
            return false;
        }
        return true;
    }

    /**
     * Destroy data for the given session ID.
     *
     * @param string $sid Session ID to erase
     *
     * @return void
     */
    public function destroySession($sid)
    {
        $queryBuilder = $this->entityManager->createQueryBuilder();
        $queryBuilder->delete($this->getEntityClass(SessionEntityInterface::class), 's')
            ->where('s.sessionId = :sid')
            ->setParameter('sid', $sid);
        $query = $queryBuilder->getQuery();
        $query->execute();
    }

    /**
     * Garbage collect expired sessions.
     *
     * @param int $sess_maxlifetime Maximum session lifetime.
     *
     * @return void
     */
    public function garbageCollect($sess_maxlifetime)
    {
        $queryBuilder = $this->entityManager->createQueryBuilder();
        $queryBuilder->delete($this->getEntityClass(SessionEntityInterface::class), 's')
            ->where('s.lastUsed < :used')
            ->setParameter('used', time() - intval($sess_maxlifetime));
        $query = $queryBuilder->getQuery();
        $query->execute();
    }

    /**
     * Create a session entity object.
     *
     * @return SessionEntityInterface
     */
    public function createEntity(): SessionEntityInterface
    {
        $class = $this->getEntityClass(SessionEntityInterface::class);
        return new $class();
    }
}
