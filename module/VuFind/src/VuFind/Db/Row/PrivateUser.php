<?php

/**
 * Fake database row to represent a user in privacy mode.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2015.
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
 * @package  Db_Row
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Db\Row;

use VuFind\Auth\UserSessionPersistenceInterface;

use function array_key_exists;

/**
 * Fake database row to represent a user in privacy mode.
 *
 * @category VuFind
 * @package  Db_Row
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class PrivateUser extends User
{
    /**
     * __get
     *
     * @param string $name Field to retrieve.
     *
     * @throws \Laminas\Db\RowGateway\Exception\InvalidArgumentException
     * @return mixed
     */
    public function __get($name)
    {
        return array_key_exists($name, $this->data) ? parent::__get($name) : null;
    }

    /**
     * Save
     *
     * @return int
     */
    public function save()
    {
        $this->initialize();
        $this->id = -1; // fake ID
        $this->getDbService(UserSessionPersistenceInterface::class)->addUserDataToSession($this);
        return 1;
    }

    /**
     * Set session container
     *
     * @param \Laminas\Session\Container $session Session container
     *
     * @return void
     *
     * @deprecated No longer used or needed
     */
    public function setSession(\Laminas\Session\Container $session)
    {
    }
}
