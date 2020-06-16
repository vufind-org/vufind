<?php
/**
 * Table Definition for cache
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2017.
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
 * @link     http://vufind.org   Main Site
 */
namespace Finna\Db\Table;

use Laminas\Db\Adapter\Adapter;
use VuFind\Db\Row\RowGateway;
use VuFind\Db\Table\PluginManager;

/**
 * Table Definition for cache
 *
 * @category VuFind
 * @package  Db_Table
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class FinnaCache extends \VuFind\Db\Table\Gateway
{
    use \VuFind\Db\Table\ExpirationTrait;

    /**
     * Constructor
     *
     * @param Adapter       $adapter Database adapter
     * @param PluginManager $tm      Table manager
     * @param array         $cfg     Laminas configuration
     * @param RowGateway    $rowObj  Row prototype object (null for default)
     * @param string        $table   Name of database table to interface with
     */
    public function __construct(Adapter $adapter, PluginManager $tm, $cfg,
        RowGateway $rowObj = null, $table = 'finna_cache'
    ) {
        parent::__construct($adapter, $tm, $cfg, $rowObj, $table);
    }

    /**
     * Get cache item from database by id.
     *
     * @param string $id Item id
     *
     * @return mixed
     */
    public function getByResourceId($id)
    {
        $row = $this->select(['resource_id' => $id])->current();
        return (empty($row)) ? false : $row;
    }

    /**
     * Update the select statement to find records to delete.
     *
     * @param Select $select  Select clause
     * @param int    $daysOld Age in days of an "expired" record.
     * @param int    $idFrom  Lowest id of rows to delete.
     * @param int    $idTo    Highest id of rows to delete.
     *
     * @return void
     */
    protected function expirationCallback($select, $daysOld, $idFrom = null,
        $idTo = null
    ) {
        $expireDate = date('Y-m-d', time() - $daysOld * 24 * 60 * 60);
        $where = $select->where->lessThan('created', $expireDate);
        if (null !== $idFrom) {
            $where->and->greaterThanOrEqualTo('id', $idFrom);
        }
        if (null !== $idTo) {
            $where->and->lessThanOrEqualTo('id', $idTo);
        }
    }
}
