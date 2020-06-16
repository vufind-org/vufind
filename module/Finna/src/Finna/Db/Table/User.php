<?php
/**
 * Table Definition for user
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2015-2020.
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
 * @package  Db_Table
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Konsta Raunio <konsta.raunio@helsinki.fi>
 * @author   Tuure Ilmarinen <tuure.ilmarinen@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace Finna\Db\Table;

use Laminas\Db\Sql\Select;

/**
 * Table Definition for user
 *
 * @category VuFind
 * @package  Db_Table
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Konsta Raunio <konsta.raunio@helsinki.fi>
 * @author   Tuure Ilmarinen <tuure.ilmarinen@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class User extends \VuFind\Db\Table\User
{
    /**
     * Create a row for the specified username.
     *
     * @param string $username Username to use for retrieval.
     *
     * @return UserRow
     */
    public function createRowForUsername($username)
    {
        $row = $this->createRow();
        // Prefix username with the institution code if set
        $row->username = isset($this->config->Site->institution)
            ? $this->config->Site->institution . ":$username"
            : $username;
        $row->created = date('Y-m-d H:i:s');
        return $row;
    }

    /**
     * Retrieve a user object from the database based on catalog ID.
     *
     * @param string $catId Catalog ID.
     *
     * @return UserRow
     */
    public function getByCatalogId($catId)
    {
        if (isset($this->config->Site->institution)) {
            $catId = $this->config->Site->institution . ":$catId";
        }
        return parent::getByCatalogId($catId);
    }

    /**
     * Retrieve a user object from the database based on email.
     *
     * @param string $email email to use for retrieval.
     *
     * @return UserRow
     */
    public function getByEmail($email)
    {
        $row = $this->select(
            function (Select $select) use ($email) {
                $where = $select->where->equalTo('email', $email);
                // Allow retrieval by email only on users registered with database
                // method to keep e.g. Shibboleth accounts intact.
                $where->and->equalTo('auth_method', 'database');
                // Limit by institution code if set
                if (isset($this->config->Site->institution)) {
                    $prefix = $this->config->Site->institution . ':';
                    $where->and->like('username', "$prefix%");
                }
            }
        );
        return $row->current();
    }

    /**
     * Retrieve a user object from the database based on username; create a new
     * row if no existing match is found.
     *
     * @param string $username Username to use for retrieval.
     * @param bool   $create   Should we create users that don't already exist?
     *
     * @return UserRow
     */
    public function getByUsername($username, $create = true)
    {
        // Prefix username with the institution code if set and not already prefixed
        $searchUsername = $username;
        if (isset($this->config->Site->institution)) {
            $prefix = $this->config->Site->institution . ':';
            if (strncmp($username, $prefix, strlen($prefix)) !== 0) {
                $searchUsername = $prefix . $username;
            }
        }
        $row = $this->select(['username' => $searchUsername])->current();
        return ($create && empty($row))
            ? $this->createRowForUsername($username) : $row;
    }

    /**
     * Get user from database by id.
     *
     * @param type $id user id
     *
     * @return boolean
     */
    public function getById($id)
    {
        if (!is_numeric($id)) {
            return false;
        }
        $row = $this->select(['id' => $id])->current();
        return (empty($row)) ? false : $row;
    }

    /**
     * Get users with due date reminders.
     *
     * @return array
     */
    public function getUsersWithDueDateReminders()
    {
        return $this->select(
            function (Select $select) {
                $subquery = new Select('user_card');
                $subquery->columns(['user_id']);
                $subquery->where->greaterThan('finna_due_date_reminder', 0);
                $select->where->in('id', $subquery);
                $select->order('username desc');
            }
        );
    }

    /**
     * Check if user input for nickname exists already in database.
     *
     * @param string $nickname to compare with user table column finna_nickname
     *
     * @return boolean true if taken nickname, false if available
     */
    public function nicknameIsTaken($nickname): bool
    {
        return ! empty($this->select(['finna_nickname' => $nickname])->current());
    }
}
