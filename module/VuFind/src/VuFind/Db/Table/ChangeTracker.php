<?php
/**
 * Table Definition for change_tracker
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Db_Table
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
namespace VuFind\Db\Table;
use VuFind\Db\Row\RowGateway;
use Zend\Db\Adapter\Adapter;

/**
 * Table Definition for change_tracker
 *
 * @category VuFind
 * @package  Db_Table
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class ChangeTracker extends Gateway
{
    /**
     * Date/time format for database
     *
     * @var string
     */
    protected $dateFormat = 'Y-m-d H:i:s';

    /**
     * Constructor
     *
     * @param Adapter       $adapter Database adapter
     * @param PluginManager $tm      Table manager
     * @param array         $cfg     Zend Framework configuration
     * @param RowGateway    $rowObj  Row prototype object (null for default)
     * @param string        $table   Name of database table to interface with
     */
    public function __construct(Adapter $adapter, PluginManager $tm, $cfg,
        RowGateway $rowObj = null, $table = 'change_tracker'
    ) {
        parent::__construct($adapter, $tm, $cfg, $rowObj, $table);
    }

    /**
     * Retrieve a row from the database based on primary key; return null if it
     * is not found.
     *
     * @param string $core The Solr core holding the record.
     * @param string $id   The ID of the record being indexed.
     *
     * @return \VuFind\Db\Row\ChangeTracker|null
     */
    public function retrieve($core, $id)
    {
        return $this->select(['core' => $core, 'id' => $id])->current();
    }

    /**
     * Retrieve a set of deleted rows from the database.
     *
     * @param string $core  The Solr core holding the record.
     * @param string $from  The beginning date of the range to search.
     * @param string $until The end date of the range to search.
     *
     * @return \Zend\Db\ResultSet\AbstractResultSet
     */
    public function retrieveDeleted($core, $from, $until)
    {
        $callback = function ($select) use ($core, $from, $until) {
            $select->where
                ->equalTo('core', $core)
                ->greaterThanOrEqualTo('deleted', $from)
                ->lessThanOrEqualTo('deleted', $until);
            $select->order('deleted');
        };
        return $this->select($callback);
    }

    /**
     * Retrieve a row from the database based on primary key; create a new
     * row if no existing match is found.
     *
     * @param string $core The Solr core holding the record.
     * @param string $id   The ID of the record being indexed.
     *
     * @return \VuFind\Db\Row\ChangeTracker
     */
    public function retrieveOrCreate($core, $id)
    {
        $row = $this->retrieve($core, $id);
        if (empty($row)) {
            $row = $this->createRow();
            $row->core = $core;
            $row->id = $id;
            $row->first_indexed = $row->last_indexed = $this->getUtcDate();
        }
        return $row;
    }

    /**
     * Update the change tracker table to indicate that a record has been deleted.
     *
     * The method returns the updated/created row when complete.
     *
     * @param string $core The Solr core holding the record.
     * @param string $id   The ID of the record being indexed.
     *
     * @return \VuFind\Db\Row\ChangeTracker
     */
    public function markDeleted($core, $id)
    {
        // Get a row matching the specified details:
        $row = $this->retrieveOrCreate($core, $id);

        // If the record is already deleted, we don't need to do anything!
        if (!empty($row->deleted)) {
            return $row;
        }

        // Save new value to the object:
        $row->deleted = $this->getUtcDate();
        $row->save();
        return $row;
    }

    /**
     * Get a UTC time.
     *
     * @param int $ts Timestamp (null for current)
     *
     * @return string
     */
    protected function getUtcDate($ts = null)
    {
        $oldTz = date_default_timezone_get();
        date_default_timezone_set('UTC');
        $date = date($this->dateFormat, null === $ts ? time() : $ts);
        date_default_timezone_set($oldTz);
        return $date;
    }

    /**
     * Convert a string to time in UTC.
     *
     * @param string $str String to parse
     *
     * @return int
     */
    protected function strToUtcTime($str)
    {
        $oldTz = date_default_timezone_get();
        date_default_timezone_set('UTC');
        $time = strtotime($str);
        date_default_timezone_set($oldTz);
        return $time;
    }

    /**
     * Update the change_tracker table to reflect that a record has been indexed.
     * We need to know the date of the last change to the record (independent of
     * its addition to the index) in order to tell the difference between a
     * reindex of a previously-encountered record and a genuine change.
     *
     * The method returns the updated/created row when complete.
     *
     * @param string $core   The Solr core holding the record.
     * @param string $id     The ID of the record being indexed.
     * @param int    $change The timestamp of the last record change.
     *
     * @return \VuFind\Db\Row\ChangeTracker
     */
    public function index($core, $id, $change)
    {
        // Get a row matching the specified details:
        $row = $this->retrieveOrCreate($core, $id);

        // Flag to indicate whether we need to save the contents of $row:
        $saveNeeded = false;

        // Make sure there is a change date in the row (this will be empty
        // if we just created a new row):
        if (empty($row->last_record_change)) {
            $row->last_record_change = $this->getUtcDate($change);
            $saveNeeded = true;
        }

        // Are we restoring a previously deleted record, or was the stored
        // record change date before current record change date?  Either way,
        // we need to update the table!
        if (!empty($row->deleted)
            || $this->strToUtcTime($row->last_record_change) < $change
        ) {
            // Save new values to the object:
            $row->last_indexed = $this->getUtcDate();
            $row->last_record_change = $this->getUtcDate($change);

            // If first indexed is null, we're restoring a deleted record, so
            // we need to treat it as new -- we'll use the current time.
            if (empty($row->first_indexed)) {
                $row->first_indexed = $row->last_indexed;
            }

            // Make sure the record is "undeleted" if necessary:
            $row->deleted = null;

            $saveNeeded = true;
        }

        // Save the row if changes were made:
        if ($saveNeeded) {
            $row->save();
        }

        // Send back the row:
        return $row;
    }
}
