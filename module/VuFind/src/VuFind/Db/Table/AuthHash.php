<?php

/**
 * Table Definition for auth_hash
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) The National Library of Finland 2019.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Db\Table;

use Laminas\Db\Adapter\Adapter;
use VuFind\Db\Row\RowGateway;

/**
 * Table Definition for auth_hash
 *
 * @category VuFind
 * @package  Db_Table
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class AuthHash extends Gateway
{
    use ExpirationTrait;

    public const TYPE_EMAIL = 'email'; // EmailAuthenticator

    /**
     * Constructor
     *
     * @param Adapter       $adapter Database adapter
     * @param PluginManager $tm      Table manager
     * @param array         $cfg     Laminas configuration
     * @param RowGateway    $rowObj  Row prototype object (null for default)
     * @param string        $table   Name of database table to interface with
     */
    public function __construct(
        Adapter $adapter,
        PluginManager $tm,
        $cfg,
        ?RowGateway $rowObj = null,
        $table = 'auth_hash'
    ) {
        parent::__construct($adapter, $tm, $cfg, $rowObj, $table);
    }

    /**
     * Retrieve an object from the database based on hash and type; create a new
     * row if no existing match is found.
     *
     * @param string $hash   Hash
     * @param string $type   Hash type
     * @param bool   $create Should we create rows that don't already exist?
     *
     * @return ?\VuFind\Db\Row\AuthHash
     */
    public function getByHashAndType($hash, $type, $create = true)
    {
        $row = $this->select(['hash' => $hash, 'type' => $type])->current();
        if ($create && empty($row)) {
            $row = $this->createRow();
            $row->hash = $hash;
            $row->type = $type;
            $row->created = date('Y-m-d H:i:s');
        }
        return $row;
    }

    /**
     * Retrieve last object from the database based on session id.
     *
     * @param string $sessionId Session ID
     *
     * @return ?\VuFind\Db\Row\AuthHash
     */
    public function getLatestBySessionId($sessionId)
    {
        $callback = function ($select) use ($sessionId) {
            $select->where->equalTo('session_id', $sessionId);
            $select->order('created DESC');
        };
        return $this->select($callback)->current();
    }

    /**
     * Update the select statement to find records to delete.
     *
     * @param Select $select    Select clause
     * @param string $dateLimit Date threshold of an "expired" record in format
     * 'Y-m-d H:i:s'.
     *
     * @return void
     */
    protected function expirationCallback($select, $dateLimit)
    {
        $select->where->lessThan('created', $dateLimit);
    }
}
