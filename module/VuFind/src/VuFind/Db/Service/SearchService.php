<?php

/**
 * Database service for search.
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

use VuFind\Db\Entity\SearchEntityInterface;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\Table\DbTableAwareInterface;
use VuFind\Db\Table\DbTableAwareTrait;

use function count;

/**
 * Database service for search.
 *
 * @category VuFind
 * @package  Database
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
class SearchService extends AbstractDbService implements SearchServiceInterface, DbTableAwareInterface
{
    use DbTableAwareTrait;

    /**
     * Destroy unsaved searches belonging to the specified session/user.
     *
     * @param string                       $sessionId Session ID of current user.
     * @param UserEntityInterface|int|null $userOrId  User entity or ID of current user (optional).
     *
     * @return void
     */
    public function destroySession(string $sessionId, UserEntityInterface|int|null $userOrId = null): void
    {
        $uid = $userOrId instanceof UserEntityInterface ? $userOrId->getId() : $userOrId;
        $callback = function ($select) use ($sessionId, $uid) {
            $select->where->equalTo('session_id', $sessionId)->and->equalTo('saved', 0);
            if ($uid !== null) {
                $select->where->OR
                    ->equalTo('user_id', $uid)->and->equalTo('saved', 0);
            }
        };
        $this->getDbTable('search')->delete($callback);
    }

    /**
     * Get a SearchEntityInterface object by ID.
     *
     * @param int $id Search identifier
     *
     * @return ?SearchEntityInterface
     */
    public function getSearchById(int $id): ?SearchEntityInterface
    {
        return $this->getDbTable('search')->select(['id' => $id])->current();
    }

    /**
     * Get a SearchEntityInterface object by ID and owner.
     *
     * @param int                          $id        Search identifier
     * @param string                       $sessionId Session ID of current user.
     * @param UserEntityInterface|int|null $userOrId  User entity or ID of current user (optional).
     *
     * @return ?SearchEntityInterface
     */
    public function getSearchByIdAndOwner(
        int $id,
        string $sessionId,
        UserEntityInterface|int|null $userOrId
    ): ?SearchEntityInterface {
        $userId = $userOrId instanceof UserEntityInterface ? $userOrId->getId() : $userOrId;
        $callback = function ($select) use ($id, $sessionId, $userId) {
            $nest = $select->where
                ->equalTo('id', $id)
                ->and
                ->nest
                ->equalTo('session_id', $sessionId);
            if (!empty($userId)) {
                $nest->or->equalTo('user_id', $userId);
            }
        };
        return $this->getDbTable('search')->select($callback)->current();
    }

    /**
     * Get an array of rows for the specified user.
     *
     * @param string                       $sessionId Session ID of current user.
     * @param UserEntityInterface|int|null $userOrId  User entity or ID of current user (optional).
     *
     * @return SearchEntityInterface[]
     */
    public function getSearches(string $sessionId, UserEntityInterface|int|null $userOrId = null): array
    {
        $uid = $userOrId instanceof UserEntityInterface ? $userOrId->getId() : $userOrId;
        $callback = function ($select) use ($sessionId, $uid) {
            $select->where->equalTo('session_id', $sessionId)->and->equalTo('saved', 0);
            if ($uid !== null) {
                $select->where->OR->equalTo('user_id', $uid);
            }
            $select->order('created');
        };
        return iterator_to_array($this->getDbTable('search')->select($callback));
    }

    /**
     * Set invalid user_id values in the table to null; return count of affected rows.
     *
     * @return int
     */
    public function cleanUpInvalidUserIds(): int
    {
        $searchTable = $this->getDbTable('search');
        $allIds = $this->getDbTable('user')->getSql()->select()->columns(['id']);
        $searchCallback = function ($select) use ($allIds) {
            $select->where->equalTo('user_id', '0')
                ->OR->notIn('user_id', $allIds);
        };
        $badRows = $searchTable->select($searchCallback);
        $count = count($badRows);
        if ($count > 0) {
            $searchTable->update(['user_id' => null], $searchCallback);
        }
        return $count;
    }
}
