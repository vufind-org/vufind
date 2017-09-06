<?php
/**
 * Table Definition for user_list
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2016.
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
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
namespace Finna\Db\Table;

/**
 * Table Definition for user_list
 *
 * @category VuFind
 * @package  Db_Table
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class UserList extends \VuFind\Db\Table\UserList
{
    /**
     * Retrieve user's list object by title.
     *
     * @param int    $userId User id
     * @param string $title  Title of the list to retrieve
     *
     * @return \Finna\Db\Row\UserList|false User list row or false if not found
     */
    public function getByTitle($userId, $title)
    {
        if (!is_numeric($userId)) {
            return false;
        }

        $callback = function ($select) use ($userId, $title) {
            $select->where->equalTo('user_id', $userId)->equalTo('title', $title);
        };
        return $this->select($callback)->current();
    }
}
