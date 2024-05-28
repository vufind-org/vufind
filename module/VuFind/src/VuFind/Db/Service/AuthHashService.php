<?php

/**
 * Database service for auth_hash table.
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

use VuFind\Db\Entity\AuthHashEntityInterface;
use VuFind\Db\Table\DbTableAwareInterface;
use VuFind\Db\Table\DbTableAwareTrait;

use function is_int;

/**
 * Database service for auth_hash table.
 *
 * @category VuFind
 * @package  Database
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
class AuthHashService extends AbstractDbService implements
    AuthHashServiceInterface,
    DbTableAwareInterface
{
    use DbTableAwareTrait;

    /**
     * Create an auth_hash entity object.
     *
     * @return AuthHashEntityInterface
     */
    public function createEntity(): AuthHashEntityInterface
    {
        return $this->getDbTable('AuthHash')->createRow();
    }

    /**
     * Delete an auth_hash entity object.
     *
     * @param int|AuthHashEntityInterface $objOrId Object or ID value representing auth_hash to delete
     *
     * @return void
     */
    public function deleteAuthHash(int|AuthHashEntityInterface $objOrId)
    {
        if ($objOrId instanceof \VuFind\Db\Row\AuthHash) {
            $authHash = $objOrId;
        } else {
            $authHashId = is_int($objOrId) ? $objOrId : $objOrId->getId();
            $authHash = $this->getDbTable('AuthHash')->select(['id' => $authHashId])->current();
        }
        // If we couldn't find the ID, $authHash may be null; can't delete what doesn't exist!
        if ($authHash) {
            $authHash->delete();
        }
    }

    /**
     * Retrieve an object from the database based on hash and type; possibly create a new
     * row if no existing match is found.
     *
     * @param string $hash   Hash
     * @param string $type   Hash type
     * @param bool   $create Should we create rows that don't already exist?
     *
     * @return ?AuthHashEntityInterface
     */
    public function getByHashAndType(string $hash, string $type, bool $create = true): ?AuthHashEntityInterface
    {
        return $this->getDbTable('AuthHash')->getByHashAndType($hash, $type, $create);
    }

    /**
     * Retrieve last object from the database based on session id.
     *
     * @param string $sessionId Session ID
     *
     * @return ?AuthHashEntityInterface
     */
    public function getLatestBySessionId(string $sessionId): ?AuthHashEntityInterface
    {
        return $this->getDbTable('AuthHash')->getLatestBySessionId($sessionId);
    }
}
