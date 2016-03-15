<?php
/**
 * Table Definition for user
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2015.
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind
 * @package  Db_Table
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace Finna\Db\Table;

/**
 * Table Definition for user
 *
 * @category VuFind
 * @package  Db_Table
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
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
        // Prefix username with the institution code if set
        $row = $this->select(
            [
                'username' => isset($this->config->Site->institution)
                    ? $this->config->Site->institution . ":$username"
                    : $username
            ]
        )->current();
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
}
