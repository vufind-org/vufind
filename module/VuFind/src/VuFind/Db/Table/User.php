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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Db_Table
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace VuFind\Db\Table;

/**
 * Table Definition for user
 *
 * @category VuFind2
 * @package  Db_Table
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class User extends Gateway
{
    /**
     * VuFind configuration
     *
     * @var \Zend\Config\Config
     */
    protected $config;

    /**
     * Constructor
     *
     * @param \Zend\Config\Config $config VuFind configuration
     */
    public function __construct(\Zend\Config\Config $config)
    {
        parent::__construct('user', 'VuFind\Db\Row\User');
        $this->config = $config;
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
        $row = $this->select(array('username' => $username))->current();
        if ($create && empty($row)) {
            $row = $this->createRow();
            $row->username = $username;
            $row->created = date('Y-m-d H:i:s');
        }
        return $row;
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
        $row = $this->select(array('email' => $email))->current();
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
        $row = $this->select(array('verify_hash' => $hash))->current();
        return $row;
    }
}
