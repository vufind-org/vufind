<?php

/**
 * Table Definition for finna_record_view_record
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2022.
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
 * @link     https://vufind.org Main Page
 */

namespace Finna\Db\Table;

use Finna\Db\Row\FinnaRecordViewRecord as FinnaRecordViewRecordRow;
use Laminas\Db\Adapter\Adapter;
use VuFind\Db\Row\RowGateway;
use VuFind\Db\Table\PluginManager;

/**
 * Table Definition for finna_record_view_records
 *
 * @category VuFind
 * @package  Db_Table
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class FinnaRecordViewRecord extends \VuFind\Db\Table\Gateway
{
    /**
     * Log entry fields to copy
     *
     * @var array
     */
    protected $logEntryFields = [
        'backend',
        'source',
        'record_id',
        'format_id',
        'usage_rights_id',
        'online',
        'extra_metadata',
    ];

    /**
     * Constructor
     *
     * @param Adapter       $adapter Database adapter
     * @param PluginManager $tm      Table manager
     * @param array         $cfg     Laminas configuration
     * @param ?RowGateway   $rowObj  Row prototype object (null for default)
     * @param string        $table   Name of database table to interface with
     */
    public function __construct(
        Adapter $adapter,
        PluginManager $tm,
        $cfg,
        ?RowGateway $rowObj = null,
        $table = 'finna_record_view_record'
    ) {
        parent::__construct($adapter, $tm, $cfg, $rowObj, $table);
    }

    /**
     * Retrieve an object from the database based on a log record; when requested,
     * create a new row if no existing match is found.
     *
     * @param array $logEntry Log record
     * @param bool  $create   Should we create records that don't already exist?
     * @param bool  $update   Should we update any existing record as necessary?
     *
     * @return ?FinnaRecordViewRecordRow
     */
    public function getByLogEntry(
        array $logEntry,
        $create = true,
        $update = true
    ): ?FinnaRecordViewRecordRow {
        $record = $this->select(
            [
                'backend' => $logEntry['backend'],
                'source' => $logEntry['source'],
                'record_id' => $logEntry['record_id'],
            ]
        )->current();
        if ($create && empty($record)) {
            $record = $this->createRow();
            foreach ($this->logEntryFields as $field) {
                $record[$field] = $logEntry[$field] ?? null;
            }
            $record->save();
        } elseif ($update && !empty($record)) {
            $changes = false;
            foreach ($this->logEntryFields as $field) {
                if ($record[$field] !== ($logEntry[$field] ?? null)) {
                    $record[$field] = $logEntry[$field] ?? null;
                    $changes = true;
                }
            }
            if ($changes) {
                $record->save();
            }
        }
        return $record;
    }
}
