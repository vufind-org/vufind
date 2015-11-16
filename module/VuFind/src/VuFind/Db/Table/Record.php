<?php
/**
 * Table Definition for record
 *
 * PHP version 5
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Db_Table
 * @author   Markus Beh <markus.beh@ub.uni-freiburg.de>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace VuFind\Db\Table;

use Zend\Db\Sql\Predicate\Expression;
use Zend\Db\Sql\Where;

/**
 * Table Definition for record
 *
 * @category VuFind2
 * @package  Db_Table
 * @author   Markus Beh <markus.beh@ub.uni-freiburg.de>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org Main Site
 */
class Record extends Gateway
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct('record', 'VuFind\Db\Row\Record');
    }

    /**
     * Find a record by id
     *
     * @param string $id     Record IDs
     * @param string $source Record source
     *
     * @throws \Exception
     * @return false|Record row object
     */
    public function findRecord($id, $source)
    {
        $records = $this->select(['record_id' => $id, 'source' => $source]);
        return $records->count() > 0 ? $records->current() : false;
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

        return $this->select($where)->toArray();
    }

    /**
     * Update an existing entry in the record table or create a new one
     *
     * @param string $id      Record ID
     * @param string $source  Data source
     * @param string $rawData Raw data from source
     *
     * @return Updated or newly added record
     */
    public function updateRecord($id, $source, $rawData)
    {
        $records = $this->select(['record_id' => $id, 'source' => $source]);
        if ($records->count() == 0) {
            $record = $this->createRow();
        } else {
            $record = $records->current();
        }

        $record->record_id = $id;
        $record->source = $source;
        $record->data = serialize($rawData);
        $record->version = \VuFind\Config\Version::getBuildVersion();
        $record->updated = date('Y-m-d H:i:s');

        // Create or update record.
        $record->save();

        return $record;
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
