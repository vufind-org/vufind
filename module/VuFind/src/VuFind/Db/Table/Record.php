<?php

/**
 * Table Definition for record
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) University of Freiburg 2014.
 * Copyright (C) The National Library of Finland 2015.
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
 * @author   Markus Beh <markus.beh@ub.uni-freiburg.de>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Db\Table;

use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Sql\Predicate\Expression;
use Laminas\Db\Sql\Where;
use VuFind\Db\Row\RowGateway;
use VuFind\Db\Service\DbServiceAwareInterface;
use VuFind\Db\Service\DbServiceAwareTrait;
use VuFind\Db\Service\RecordServiceInterface;

use function count;

/**
 * Table Definition for record
 *
 * @category VuFind
 * @package  Db_Table
 * @author   Markus Beh <markus.beh@ub.uni-freiburg.de>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class Record extends Gateway implements DbServiceAwareInterface
{
    use DbServiceAwareTrait;

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
        $table = 'record'
    ) {
        parent::__construct($adapter, $tm, $cfg, $rowObj, $table);
    }

    /**
     * Find a record by id
     *
     * @param string $id     Record ID
     * @param string $source Record source
     *
     * @throws \Exception
     * @return ?\VuFind\Db\Row\Record
     */
    public function findRecord($id, $source)
    {
        return $this->select(['record_id' => $id, 'source' => $source])->current();
    }

    /**
     * Find records by ids
     *
     * @param array  $ids    Record IDs
     * @param string $source Record source
     *
     * @throws \Exception
     * @return array Array of record row objects found
     */
    public function findRecords($ids, $source)
    {
        if (empty($ids)) {
            return [];
        }

        $where = new Where();
        foreach ($ids as $id) {
            $nested = $where->or->nest();
            $nested->addPredicates(
                ['record_id' => $id, 'source' => $source]
            );
        }

        return iterator_to_array($this->select($where));
    }

    /**
     * Update an existing entry in the record table or create a new one
     *
     * @param string $id      Record ID
     * @param string $source  Data source
     * @param mixed  $rawData Raw data from source (must be serializable)
     *
     * @return \VuFind\Db\Row\Record Updated or newly added record
     *
     * @deprecated Use RecordServiceInterface::updateRecord()
     */
    public function updateRecord($id, $source, $rawData)
    {
        return $this->getDbService(RecordServiceInterface::class)->updateRecord($id, $source, $rawData);
    }

    /**
     * Clean up orphaned entries (i.e. entries that are not in favorites anymore)
     *
     * @return int Number of records deleted
     */
    public function cleanup()
    {
        $callback = function ($select) {
            $select->columns(['id']);
            $select->join(
                'resource',
                new Expression(
                    'record.record_id = resource.record_id'
                    . ' AND record.source = resource.source'
                ),
                []
            )->join(
                'user_resource',
                'resource.id = user_resource.resource_id',
                [],
                $select::JOIN_LEFT
            );
            $select->where->isNull('user_resource.id');
        };

        $results = $this->select($callback);
        foreach ($results as $result) {
            $this->delete(['id' => $result['id']]);
        }

        return count($results);
    }
}
