<?php
/**
 * Table Definition for user
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
namespace VuFind\Db\Table;
use Zend\Config\Config, Zend\Session\Container;

/**
 * Table Definition for user
 *
 * @category VuFind
 * @package  Db_Table
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class User extends Gateway
{
    /**
     * VuFind configuration
     *
     * @var Config
     */
    protected $config;

    /**
     * Session container
     *
     * @var Container
     */
    protected $session;

    /**
     * Constructor
     *
     * @param Config    $config   VuFind configuration
     * @param string    $rowClass Name of class for representing rows
     * @param Container $session  Session container to inject into rows (optional;
     * used for privacy mode)
     */
    public function __construct(Config $config, $rowClass = 'VuFind\Db\Row\User',
        Container $session = null
    ) {
        parent::__construct('user', $rowClass);
        $this->config = $config;
        $this->session = $session;
    }

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
        $row->username = $username;
        $row->created = date('Y-m-d H:i:s');
        return $row;
    }

    /**
     * Retrieve a user object from the database based on username; when requested,
     * create a new row if no existing match is found.
     *
     * @param string $username Username to use for retrieval.
     * @param bool   $create   Should we create users that don't already exist?
     *
     * @return UserRow
     */
    public function getByUsername($username, $create = true)
    {
        $row = $this->select(['username' => $username])->current();
        return ($create && empty($row))
            ? $this->createRowForUsername($username) : $row;
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
        $row = $this->select(['email' => $email])->current();
        return $row;
    }

    /**
     * Get user rows with insecure passwords and/or catalog passwords
     *
     * @return mixed
     */
    public function getInsecureRows()
    {
        $callback = function ($select) {
            $select->where
                ->notEqualTo('password', '')
                ->OR->isNotNull('cat_password');
        };
        return $this->select($callback);
    }

    /**
     * Construct the prototype for rows.
     *
     * @return object
     */
    protected function initializeRowPrototype()
    {
        $prototype = parent::initializeRowPrototype();
        $prototype->setConfig($this->config);
        if (null !== $this->session && is_callable([$prototype, 'setSession'])) {
            $prototype->setSession($this->session);
        }
        return $prototype;
    }

    /**
     * Return a row by a verification hash
     *
     * @param string $hash User-unique hash string
     *
     * @return mixed
     */
    public function getByVerifyHash($hash)
    {
        $row = $this->select(['verify_hash' => $hash])->current();
        return $row;
    }
}
