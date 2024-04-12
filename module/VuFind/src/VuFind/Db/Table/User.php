<?php

/**
 * Table Definition for user
 *
 * PHP version 8
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

use Laminas\Config\Config;
use Laminas\Db\Adapter\Adapter;
use Laminas\Session\Container;
use VuFind\Db\Row\RowGateway;
use VuFind\Db\Row\User as UserRow;

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
     * @param Adapter       $adapter Database adapter
     * @param PluginManager $tm      Table manager
     * @param array         $cfg     Laminas configuration
     * @param RowGateway    $rowObj  Row prototype object (null for default)
     * @param Config        $config  VuFind configuration
     * @param Container     $session Session container to inject into rows
     * (optional; used for privacy mode)
     * @param string        $table   Name of database table to interface with
     */
    public function __construct(
        Adapter $adapter,
        PluginManager $tm,
        $cfg,
        ?RowGateway $rowObj,
        Config $config,
        Container $session = null,
        $table = 'user'
    ) {
        $this->config = $config;
        $this->session = $session;
        parent::__construct($adapter, $tm, $cfg, $rowObj, $table);
    }

    /**
     * Create a row for the specified username.
     *
     * @param string $username Username
     *
     * @return UserRow
     */
    public function createRowForUsername($username)
    {
        $row = $this->createRow();
        $row->username = $username;
        $row->created = date('Y-m-d H:i:s');
        // Failing to initialize this here can cause Laminas\Db errors in
        // the VuFind\Auth\Shibboleth and VuFind\Auth\ILS integration tests.
        $row->user_provided_email = 0;
        return $row;
    }

    /**
     * Retrieve a user object from the database based on ID.
     *
     * @param int $id ID.
     *
     * @return ?UserRow
     */
    public function getById($id)
    {
        return $this->select(['id' => $id])->current();
    }

    /**
     * Retrieve a user object from the database based on catalog ID.
     *
     * @param string $catId Catalog ID.
     *
     * @return ?UserRow
     */
    public function getByCatalogId($catId)
    {
        return $this->select(['cat_id' => $catId])->current();
    }

    /**
     * Retrieve a user object from the database based on username; when requested,
     * create a new row if no existing match is found.
     *
     * @param string $username Username to use for retrieval.
     * @param bool   $create   Should we create users that don't already exist?
     *
     * @return ?UserRow
     */
    public function getByUsername($username, $create = true)
    {
        $callback = function ($select) use ($username) {
            $select->where->literal('lower(username) = lower(?)', [$username]);
        };
        $row = $this->select($callback)->current();
        return ($create && empty($row))
            ? $this->createRowForUsername($username) : $row;
    }

    /**
     * Retrieve a user object from the database based on email.
     *
     * @param string $email email to use for retrieval.
     *
     * @return ?UserRow
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
     * Return a row by a verification hash
     *
     * @param string $hash User-unique hash string
     *
     * @return ?UserRow
     */
    public function getByVerifyHash($hash)
    {
        $row = $this->select(['verify_hash' => $hash])->current();
        return $row;
    }
}
