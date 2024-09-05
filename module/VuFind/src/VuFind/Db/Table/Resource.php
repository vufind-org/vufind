<?php

/**
 * Table Definition for resource
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
use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Select;
use VuFind\Date\Converter as DateConverter;
use VuFind\Db\Row\RowGateway;
use VuFind\Db\Service\DbServiceAwareInterface;
use VuFind\Db\Service\DbServiceAwareTrait;
use VuFind\Db\Service\ResourceServiceInterface;

use function in_array;

/**
 * Table Definition for resource
 *
 * @category VuFind
 * @package  Db_Table
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class Resource extends Gateway implements DbServiceAwareInterface
{
    use DbServiceAwareTrait;

    /**
     * Loader for record populator
     *
     * @var callable
     */
    protected $resourcePopulatorLoader;

    /**
     * Constructor
     *
     * @param Adapter       $adapter                 Database adapter
     * @param PluginManager $tm                      Table manager
     * @param array         $cfg                     Laminas configuration
     * @param ?RowGateway   $rowObj                  Row prototype object (null for default)
     * @param DateConverter $dateConverter           Date converter
     * @param callable      $resourcePopulatorLoader Resource populator loader
     * @param string        $table                   Name of database table to interface with
     */
    public function __construct(
        Adapter $adapter,
        PluginManager $tm,
        array $cfg,
        ?RowGateway $rowObj,
        protected DateConverter $dateConverter,
        callable $resourcePopulatorLoader,
        string $table = 'resource'
    ) {
        $this->resourcePopulatorLoader = $resourcePopulatorLoader;
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
     *
     * @deprecated Use ResourceServiceInterface::getResourceByRecordId() or
     * \VuFind\Record\ResourcePopulator::getOrCreateResourceForDriver() or
     * \VuFind\Record\ResourcePopulator::getOrCreateResourceForRecordId() as appropriate.
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
            $resourcePopulator = ($this->resourcePopulatorLoader)();
            $result = $driver
                ? $resourcePopulator->createAndPersistResourceForDriver($driver)
                : $resourcePopulator->createAndPersistResourceForRecordId($id, $source);
        }
        return $result;
    }

    /**
     * Look up a rowset for a set of specified resources.
     *
     * @param array  $ids    Array of IDs
     * @param string $source Source of records to look up
     *
     * @return ResourceEntityInterface[]
     *
     * @deprecated Use ResourceServiceInterface::getResourcesByRecordIds()
     */
    public function findResources($ids, $source = DEFAULT_SEARCH_BACKEND)
    {
        return $this->getDbService(ResourceServiceInterface::class)->getResourcesByRecordIds($ids, $source);
    }

    /**
     * Get a set of records from the requested favorite list.
     *
     * @param string $user              ID of user owning favorite list
     * @param string $list              ID of list to retrieve (null for all favorites)
     * @param array  $tags              Tags to use for limiting results
     * @param string $sort              Resource table field to use for sorting (null for no particular sort).
     * @param int    $offset            Offset for results
     * @param int    $limit             Limit for results (null for none)
     * @param ?bool  $caseSensitiveTags Should tags be searched case sensitively (null for configured default)
     *
     * @return \Laminas\Db\ResultSet\AbstractResultSet
     */
    public function getFavorites(
        $user,
        $list = null,
        $tags = [],
        $sort = null,
        $offset = 0,
        $limit = null,
        $caseSensitiveTags = null
    ) {
        // Set up base query:
        return $this->select(
            function ($s) use ($user, $list, $tags, $sort, $offset, $limit, $caseSensitiveTags) {
                $columns = [Select::SQL_STAR];
                $s->columns($columns);
                $s->join(
                    'user_resource',
                    'resource.id = user_resource.resource_id',
                    ['last_saved' => new Expression('MAX(saved)')]
                );
                $s->where->equalTo('user_resource.user_id', $user);
                // Adjust for list if necessary:
                if (null !== $list) {
                    $s->where->equalTo('user_resource.list_id', $list);
                }
                // Adjust for tags if necessary:
                if (!empty($tags)) {
                    $linkingTable = $this->getDbTable('ResourceTags');
                    foreach ($tags as $tag) {
                        $matches = $linkingTable->getResourcesForTag($tag, $user, $list, $caseSensitiveTags)->toArray();
                        $getId = function ($i) {
                            return $i['resource_id'];
                        };
                        $s->where->in('resource_id', array_map($getId, $matches));
                    }
                }
                if ($offset > 0) {
                    $s->offset($offset);
                }
                if (null !== $limit) {
                    $s->limit($limit);
                }

                $s->group(['resource.id']);

                // Apply sorting, if necessary:
                if ($sort == 'last_saved' || $sort == 'last_saved DESC') {
                    $s->order($sort);
                } elseif (!empty($sort)) {
                    Resource::applySort($s, $sort, 'resource', $columns);
                }
            }
        );
    }

    /**
     * Get a set of records that do not have metadata stored in the resource
     * table.
     *
     * @return ResourceEntityInterface[]
     *
     * @deprecated Use ResourceServiceInterface::findMissingMetadata()
     */
    public function findMissingMetadata()
    {
        return $this->getDbService(ResourceServiceInterface::class)->findMissingMetadata();
    }

    /**
     * Update the database to reflect a changed record identifier.
     *
     * @param string $oldId  Original record ID
     * @param string $newId  Revised record ID
     * @param string $source Record source
     *
     * @return void
     *
     * @deprecated Use \VuFind\Record\RecordIdUpdater::updateRecordId()
     */
    public function updateRecordId($oldId, $newId, $source = DEFAULT_SEARCH_BACKEND)
    {
        $resourceService = $this->getDbService(ResourceServiceInterface::class);
        if (
            $oldId !== $newId
            && $resource = $resourceService->getResourceByRecordId($oldId, $source)
        ) {
            $tableObjects = [];
            // Do this as a transaction to prevent odd behavior:
            $connection = $this->getAdapter()->getDriver()->getConnection();
            $connection->beginTransaction();
            // Does the new ID already exist?
            if ($newResource = $resourceService->getResourceByRecordId($newId, $source)) {
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
     * @param \Laminas\Db\Sql\Select $query   Query to modify
     * @param string                 $sort    Field to use for sorting (may include
     * 'desc' qualifier)
     * @param string                 $alias   Alias to the resource table (defaults to
     * 'resource')
     * @param array                  $columns Existing list of columns to select
     *
     * @return void
     */
    public static function applySort($query, $sort, $alias = 'resource', $columns = [])
    {
        // Apply sorting, if necessary:
        $legalSorts = [
            'title', 'title desc', 'author', 'author desc', 'year', 'year desc',
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
                $expression = new Expression(
                    'case when ? is null then 1 else 0 end',
                    [$alias . '.' . $rawField],
                    [Expression::TYPE_IDENTIFIER]
                );
                $query->columns(array_merge($columns, [$expression]));
                $order[] = $expression;
            }

            // Apply the user-specified sort:
            $order[] = $alias . '.' . $sort;

            // Inject the sort preferences into the query object:
            $query->order($order);
        }
    }
}
