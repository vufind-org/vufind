<?php

/**
 * Table Definition for finna_record_view_inst_view
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

use Finna\Db\Row\FinnaRecordViewInstView as FinnaRecordViewInstViewRow;
use Laminas\Db\Adapter\Adapter;
use VuFind\Db\Row\RowGateway;
use VuFind\Db\Table\PluginManager;

/**
 * Table Definition for finna_record_view_inst_view
 *
 * @category VuFind
 * @package  Db_Table
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class FinnaRecordViewInstView extends \VuFind\Db\Table\Gateway
{
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
        $table = 'finna_record_view_inst_view'
    ) {
        parent::__construct($adapter, $tm, $cfg, $rowObj, $table);
    }

    /**
     * Retrieve an object from the database based on a log record; when requested,
     * create a new row if no existing match is found.
     *
     * @param array $logEntry Log record
     * @param bool  $create   Should we create records that don't already exist?
     *
     * @return ?FinnaRecordViewInstViewRow
     */
    public function getByLogEntry(
        array $logEntry,
        $create = true
    ): ?FinnaRecordViewInstViewRow {
        $record = $this->select(
            [
                'institution' => $logEntry['institution'],
                'view' => $logEntry['view'],
            ]
        )->current();
        if ($create && empty($record)) {
            $record = $this->createRow();
            $record->institution = $logEntry['institution'];
            $record->view = $logEntry['view'];
            $record->save();
        }
        return $record;
    }
}
