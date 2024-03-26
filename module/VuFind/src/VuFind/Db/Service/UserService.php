<?php

/**
 * Database service for user.
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
 * @author   Sudharma Kellampalli <skellamp@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */

namespace VuFind\Db\Service;

use Laminas\Log\LoggerAwareInterface;
use VuFind\Db\Entity\UserEntityInterface;
use VuFind\Db\Table\User;
use VuFind\Log\LoggerAwareTrait;

/**
 * Database service for user.
 *
 * @category VuFind
 * @package  Database
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
class UserService extends AbstractDbService implements
    LoggerAwareInterface,
    UserServiceInterface
{
    use LoggerAwareTrait;

    /**
     * Constructor.
     *
     * @param User $userTable User table
     */
    public function __construct(protected User $userTable)
    {
    }

    /**
     * Retrieve a user object from the database based on ID.
     *
     * @param string $id ID.
     *
     * @return ?UserEntityInterface
     */
    public function getUserById($id)
    {
        return $this->userTable->getById($id);
    }

    /**
     * Retrieve a user object from the database based on the given field.
     *
     * @param string $fieldName  Field name
     * @param string $fieldValue Field value
     *
     * @return ?UserEntityInterface
     */
    public function getUserByField($fieldName, $fieldValue)
    {
        switch ($fieldName) {
            case 'id':
                return $this->userTable->getById($fieldValue);
            case 'username':
                return $this->userTable->getByUsername($fieldValue, false);
            case 'cat_id':
                return $this->userTable->getByCatalogId($fieldValue);
        }
        return null;
    }
}
