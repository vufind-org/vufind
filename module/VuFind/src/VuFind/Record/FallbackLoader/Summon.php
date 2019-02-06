<?php
/**
 * Summon record fallback loader
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2018.
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
 * @package  Record
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
namespace VuFind\Record\FallbackLoader;

use SerialsSolutions\Summon\Zend2 as Connector;
use VuFind\Db\Table\Resource;
use VuFindSearch\Backend\Summon\Backend;
use VuFindSearch\ParamBag;

/**
 * Summon record fallback loader
 *
 * @category VuFind
 * @package  Record
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class Summon implements FallbackLoaderInterface
{
    /**
     * Resource table
     *
     * @var Resource
     */
    protected $table;

    /**
     * Summon backend
     *
     * @var Backend
     */
    protected $backend;

    /**
     * Constructor
     *
     * @param Resource $table   Resource database table object
     * @param Backend  $backend Summon search backend
     */
    public function __construct(Resource $table, Backend $backend)
    {
        $this->table = $table;
        $this->backend = $backend;
    }

    /**
     * Given an array of IDs that failed to load, try to find them using a
     * fallback mechanism.
     *
     * @param array $ids IDs to load
     *
     * @return array
     */
    public function load($ids)
    {
        $retVal = [];
        foreach ($ids as $id) {
            foreach ($this->fetchSingleRecord($id) as $record) {
                $this->updateRecord($record, $id);
                $retVal[] = $record;
            }
        }
        return $retVal;
    }

    /**
     * Fetch a single record (null if not found).
     *
     * @param string $id ID to load
     *
     * @return \VuFindSearch\Response\RecordCollectionInterface
     */
    protected function fetchSingleRecord($id)
    {
        $resource = $this->table->findResource($id, 'Summon');
        if ($resource && ($extra = json_decode($resource->extra_metadata, true))) {
            $bookmark = $extra['bookmark'] ?? '';
            if (strlen($bookmark) > 0) {
                $params = new ParamBag(
                    ['summonIdType' => Connector::IDENTIFIER_BOOKMARK]
                );
                return $this->backend->retrieve($bookmark, $params);
            }
        }
        return new \VuFindSearch\Backend\Summon\Response\RecordCollection([]);
    }

    /**
     * When a record ID has changed, update the record driver and database to
     * reflect the changes.
     *
     * @param \VuFind\RecordDriver\AbstractBase $record     Record to update
     * @param string                            $previousId Old ID of record
     *
     * @return void
     */
    protected function updateRecord($record, $previousId)
    {
        // Update the record driver with knowledge of the previous identifier...
        $record->setPreviousUniqueId($previousId);

        // Update the database to replace the obsolete identifier...
        $this->table->updateRecordId($previousId, $record->getUniqueId(), 'Summon');
    }
}
