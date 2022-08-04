<?php
/**
 * Table Definition for resource
 *
 * PHP version 7
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
use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Select;
use VuFind\Date\Converter as DateConverter;
use VuFind\Db\Row\RowGateway;
use VuFind\Record\Loader;

/**
 * Table Definition for resource
 *
 * @category VuFind
 * @package  Db_Table
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class Resource extends Gateway
{
    /**
     * Date converter
     *
     * @var DateConverter
     */
    protected $dateConverter;

    /**
     * Record loader
     *
     * @var Loader
     */
    protected $recordLoader;

    /**
     * Constructor
     *
     * @param Adapter       $adapter   Database adapter
     * @param PluginManager $tm        Table manager
     * @param array         $cfg       Laminas configuration
     * @param RowGateway    $rowObj    Row prototype object (null for default)
     * @param DateConverter $converter Date converter
     * @param Loader        $loader    Record loader
     * @param string        $table     Name of database table to interface with
     */
    public function __construct(
        Adapter $adapter,
        PluginManager $tm,
        $cfg,
        ?RowGateway $rowObj,
        DateConverter $converter,
        Loader $loader,
        $table = 'resource'
    ) {
        $this->dateConverter = $converter;
        $this->recordLoader = $loader;
        parent::__construct($adapter, $tm, $cfg, $rowObj, $table);
    }

    /**
     * Look up a row for the specified resource.
     *
     * @param string                            $id     Record ID to look up
     * @param string                            $source Source of record to look up
     * @param bool                              $create If true, create the row if it
     * does not yet exist.
     * @param \VuFind\RecordDriver\AbstractBase $driver A record driver for the
     * resource being created (optional -- improves efficiency if provided, but will
     * be auto-loaded as needed if left null).
     *
     * @return \VuFind\Db\Row\Resource|null Matching row if found or created, null
     * otherwise.
     */
    public function findResource(
        $id,
        $source = DEFAULT_SEARCH_BACKEND,
        $create = true,
        $driver = null
    ) {
        if (empty($id)) {
            throw new \Exception('Resource ID cannot be empty');
        }
        $select = $this->select(['record_id' => $id, 'source' => $source]);
        $result = $select->current();

        // Create row if it does not already exist and creation is enabled:
        if (empty($result) && $create) {
            $result = $this->createRow();
            $result->record_id = $id;
            $result->source = $source;

            // Load record if it was not provided:
            if (null === $driver) {
                $driver = $this->recordLoader->load($id, $source);
            }

            // Load metadata into the database for sorting/failback purposes:
            $result->assignMetadata($driver, $this->dateConverter);

            // Save the new row.
            $result->save();
        }
        return $result;
    }

    /**
     * Look up a rowset for a set of specified resources.
     *
     * @param array  $ids    Array of IDs
     * @param string $source Source of records to look up
     *
     * @return \Laminas\Db\ResultSet\AbstractResultSet
     */
    public function findResources($ids, $source = DEFAULT_SEARCH_BACKEND)
    {
        $callback = function ($select) use ($ids, $source) {
            $select->where->in('record_id', $ids);
            $select->where->equalTo('source', $source);
        };
        return $this->select($callback);
    }

    /**
     * Get a set of records from the requested favorite list.
     *
     * @param string $user   ID of user owning favorite list
     * @param string $list   ID of list to retrieve (null for all favorites)
     * @param array  $tags   Tags to use for limiting results
     * @param string $sort   Resource table field to use for sorting (null for
     * no particular sort).
     * @param int    $offset Offset for results
     * @param int    $limit  Limit for results (null for none)
     *
     * @return \Laminas\Db\ResultSet\AbstractResultSet
     */
    public function getFavorites(
        $user,
        $list = null,
        $tags = [],
        $sort = null,
        $offset = 0,
        $limit = null
    ) {
        // Set up base query:
        $obj = & $this;
        return $this->select(
            function ($s) use ($user, $list, $tags, $sort, $offset, $limit, $obj) {
                $s->columns(
                    [
                        new Expression(
                            'DISTINCT(?)',
                            ['resource.id'],
                            [Expression::TYPE_IDENTIFIER]
                        ), Select::SQL_STAR
                    ]
                );
                $s->join(
                    ['ur' => 'user_resource'],
                    'resource.id = ur.resource_id',
                    []
                );
                $s->where->equalTo('ur.user_id', $user);

                // Adjust for list if necessary:
                if (null !== $list) {
                    $s->where->equalTo('ur.list_id', $list);
                }

                if ($offset > 0) {
                    $s->offset($offset);
                }
                if (null !== $limit) {
                    $s->limit($limit);
                }

                // Adjust for tags if necessary:
                if (!empty($tags)) {
                    $linkingTable = $obj->getDbTable('ResourceTags');
                    foreach ($tags as $tag) {
                        $matches = $linkingTable
                            ->getResourcesForTag($tag, $user, $list)->toArray();
                        $getId = function ($i) {
                            return $i['resource_id'];
                        };
                        $s->where->in('resource.id', array_map($getId, $matches));
                    }
                }

                // Apply sorting, if necessary:
                if (!empty($sort)) {
                    Resource::applySort($s, $sort);
                }
            }
        );
    }

    /**
     * Get a set of records that do not have metadata stored in the resource
     * table.
     *
     * @return \Laminas\Db\ResultSet\AbstractResultSet
     */
    public function findMissingMetadata()
    {
        $callback = function ($select) {
            $select->where->equalTo('title', '')
                ->OR->isNull('author')
                ->OR->isNull('year');
        };
        return $this->select($callback);
    }

    /**
     * Update the database to reflect a changed record identifier.
     *
     * @param string $oldId  Original record ID
     * @param string $newId  Revised record ID
     * @param string $source Record source
     *
     * @return void
     */
    public function updateRecordId($oldId, $newId, $source = DEFAULT_SEARCH_BACKEND)
    {
        if ($oldId !== $newId
            && $resource = $this->findResource($oldId, $source, false)
        ) {
            $tableObjects = [];
            // Do this as a transaction to prevent odd behavior:
            $connection = $this->getAdapter()->getDriver()->getConnection();
            $connection->beginTransaction();
            // Does the new ID already exist?
            if ($newResource = $this->findResource($newId, $source, false)) {
                // Special case: merge new ID and old ID:
                foreach (['comments', 'userresource', 'resourcetags'] as $table) {
                    $tableObjects[$table] = $this->getDbTable($table);
                    $tableObjects[$table]->update(
                        ['resource_id' => $newResource->id],
                        ['resource_id' => $resource->id]
                    );
                }
                $resource->delete();
            } else {
                // Default case: just update the record ID:
                $resource->record_id = $newId;
                $resource->save();
            }
            // Done -- commit the transaction:
            $connection->commit();

            // Deduplicate rows where necessary (this can be safely done outside
            // of the transaction):
            if (isset($tableObjects['resourcetags'])) {
                $tableObjects['resourcetags']->deduplicate();
            }
            if (isset($tableObjects['userresource'])) {
                $tableObjects['userresource']->deduplicate();
            }
        }
    }

    /**
     * Apply a sort parameter to a query on the resource table.
     *
     * @param \Laminas\Db\Sql\Select $query Query to modify
     * @param string                 $sort  Field to use for sorting (may include
     * 'desc' qualifier)
     * @param string                 $alias Alias to the resource table (defaults to
     * 'resource')
     *
     * @return void
     */
    public static function applySort($query, $sort, $alias = 'resource')
    {
        // Apply sorting, if necessary:
        $legalSorts = [
            'title', 'title desc', 'author', 'author desc', 'year', 'year desc'
        ];
        if (!empty($sort) && in_array(strtolower($sort), $legalSorts)) {
            // Strip off 'desc' to obtain the raw field name -- we'll need it
            // to sort null values to the bottom:
            $parts = explode(' ', $sort);
            $rawField = trim($parts[0]);

            // Start building the list of sort fields:
            $order = [];

            // The title field can't be null, so don't bother with the extra
            // isnull() sort in that case.
            if (strtolower($rawField) != 'title') {
                $order[] = new Expression(
                    'isnull(?)',
                    [$alias . '.' . $rawField],
                    [Expression::TYPE_IDENTIFIER]
                );
            }

            // Apply the user-specified sort:
            $order[] = $alias . '.' . $sort;

            // Inject the sort preferences into the query object:
            $query->order($order);
        }
    }
}
