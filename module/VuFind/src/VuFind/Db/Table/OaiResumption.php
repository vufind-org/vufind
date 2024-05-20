<?php

/**
 * Table Definition for oai_resumption
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

use Laminas\Db\Adapter\Adapter;
use VuFind\Db\Row\RowGateway;
use VuFind\Db\Service\DbServiceAwareInterface;

/**
 * Table Definition for oai_resumption
 *
 * @category VuFind
 * @package  Db_Table
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class OaiResumption extends Gateway implements DbServiceAwareInterface
{
    use \VuFind\Db\Service\DbServiceAwareTrait;

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
        $table = 'oai_resumption'
    ) {
        parent::__construct($adapter, $tm, $cfg, $rowObj, $table);
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
     * @return ?\VuFind\Db\Row\OaiResumption
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
     *
     * @deprecated Use \VuFind\Db\Service\OaiResumptionService::createAndPersistToken()
     */
    public function saveToken($params, $expire)
    {
        return $this->getDbService(\VuFind\Db\Service\OaiResumptionServiceInterface::class)
            ->createAndPersistToken($params, $expire)->getId();
    }
}
