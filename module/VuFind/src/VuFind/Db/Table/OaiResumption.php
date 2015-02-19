<?php
/**
 * Table Definition for oai_resumption
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
 * Table Definition for oai_resumption
 *
 * @category VuFind2
 * @package  Db_Table
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class OaiResumption extends Gateway
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct('oai_resumption', 'VuFind\Db\Row\OaiResumption');
    }

    /**
     * Remove all expired tokens from the database.
     *
     * @return void
     */
    public function removeExpired()
    {
        $callback = function ($select) {
            $now = date('Y-m-d H:i:s');
            $select->where->lessThanOrEqualTo('expires', $now);
        };
        $this->delete($callback);
    }

    /**
     * Retrieve a row from the database based on primary key; return null if it
     * is not found.
     *
     * @param string $token The resumption token to retrieve.
     *
     * @return \VuFind\Db\Row\OaiResumption|null
     */
    public function findToken($token)
    {
        return $this->select(['id' => $token])->current();
    }

    /**
     * Create a new resumption token
     *
     * @param array $params Parameters associated with the token.
     * @param int   $expire Expiration time for token (Unix timestamp).
     *
     * @return int          ID of new token
     */
    public function saveToken($params, $expire)
    {
        $row = $this->createRow();
        $row->saveParams($params);
        $row->expires = date('Y-m-d H:i:s', $expire);
        $row->save();
        return $row->id;
    }
}
