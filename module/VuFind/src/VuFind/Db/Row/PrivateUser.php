<?php
/**
 * Fake database row to represent a user in privacy mode.
 *
 * PHP version 7
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
     * Session container for account information.
     *
     * @var \Laminas\Session\Container
     */
    protected $session = null;

    /**
     * __get
     *
     * @param string $name Field to retrieve.
     *
     * @throws Exception\InvalidArgumentException
     * @return mixed
     */
    public function __get($name)
    {
        return array_key_exists($name, $this->data) ? parent::__get($name) : null;
    }

    /**
     * Whether library cards are enabled
     *
     * @return bool
     */
    public function libraryCardsEnabled()
    {
        return false; // not supported in this context
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
        if (null === $this->session) {
            throw new \Exception('Expected session container missing.');
        }
        $this->session->userDetails = $this->toArray();
        return 1;
    }

    /**
     * Set session container
     *
     * @param \Laminas\Session\Container $session Session container
     *
     * @return void
     */
    public function setSession(\Laminas\Session\Container $session)
    {
        $this->session = $session;
    }
}
