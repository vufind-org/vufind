<?php

/**
 * Table Definition for user_resource
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
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Db\Table;

use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Select;
use VuFind\Db\Row\RowGateway;
use VuFind\Db\Service\DbServiceAwareInterface;
use VuFind\Db\Service\DbServiceAwareTrait;
use VuFind\Db\Service\ResourceTagsServiceInterface;
use VuFind\Db\Service\UserResourceServiceInterface;

/**
 * Table Definition for user_resource
 *
 * @category VuFind
 * @package  Db_Table
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class UserResource extends Gateway implements DbServiceAwareInterface
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
        $table = 'user_resource'
    ) {
        parent::__construct($adapter, $tm, $cfg, $rowObj, $table);
    }

    /**
     * Get information saved in a user's favorites for a particular record.
     *
     * @param string $resourceId ID of record being checked.
     * @param string $source     Source of record to look up
     * @param int    $listId     Optional list ID (to limit results to a particular
     * list).
     * @param int    $userId     Optional user ID (to limit results to a particular
     * user).
     *
     * @return \Laminas\Db\ResultSet\AbstractResultSet
     */
    public function getSavedData(
        $resourceId,
        $source = DEFAULT_SEARCH_BACKEND,
        $listId = null,
        $userId = null
    ) {
        $callback = function ($select) use ($resourceId, $source, $listId, $userId) {
            $select->columns(
                [
                    new Expression(
                        'DISTINCT(?)',
                        ['user_resource.id'],
                        [Expression::TYPE_IDENTIFIER]
                    ), Select::SQL_STAR,
                ]
            );
            $select->join(
                ['r' => 'resource'],
                'r.id = user_resource.resource_id',
                []
            );
            $select->join(
                ['ul' => 'user_list'],
                'user_resource.list_id = ul.id',
                ['list_title' => 'title', 'list_id' => 'id']
            );
            $select->where->equalTo('r.source', $source)
                ->equalTo('r.record_id', $resourceId);

            if (null !== $userId) {
                $select->where->equalTo('user_resource.user_id', $userId);
            }
            if (null !== $listId) {
                $select->where->equalTo('user_resource.list_id', $listId);
            }
        };
        return $this->select($callback);
    }

    /**
     * Create link if one does not exist; update notes if one does.
     *
     * @param string $resource_id ID of resource to link up
     * @param string $user_id     ID of user creating link
     * @param string $list_id     ID of list to link up
     * @param string $notes       Notes to associate with link
     *
     * @return \VuFind\Db\Row\UserResource
     *
     * @deprecated Use UserResourceServiceInterface::createOrUpdateLink()
     */
    public function createOrUpdateLink(
        $resource_id,
        $user_id,
        $list_id,
        $notes = ''
    ) {
        return $this->getDbService(UserResourceServiceInterface::class)
            ->createOrUpdateLink($resource_id, $user_id, $list_id, $notes);
    }

    /**
     * Unlink rows for the specified resource. This will also automatically remove
     * any tags associated with the relationship.
     *
     * @param string|array $resource_id ID (or array of IDs) of resource(s) to
     * unlink (null for ALL matching resources)
     * @param string       $user_id     ID of user removing links
     * @param string       $list_id     ID of list to unlink
     * (null for ALL matching lists, with the destruction of all tags associated
     * with the $resource_id value; true for ALL matching lists, but retaining
     * any tags associated with the $resource_id independently of lists)
     *
     * @return void
     *
     * @deprecated
     */
    public function destroyLinks($resource_id, $user_id, $list_id = null)
    {
        // Remove any tags associated with the links we are removing; we don't
        // want to leave orphaned tags in the resource_tags table after we have
        // cleared out favorites in user_resource!
        $resourceTagsService = $this->getDbService(ResourceTagsServiceInterface::class);
        if ($list_id === true) {
            $resourceTagsService->destroyAllListResourceTagsLinksForUser($resource_id, $user_id);
        } else {
            $resourceTagsService->destroyResourceTagsLinksForUser($resource_id, $user_id, $list_id);
        }

        // Now build the where clause to figure out which rows to remove:
        $callback = function ($select) use ($resource_id, $user_id, $list_id) {
            $select->where->equalTo('user_id', $user_id);
            if (null !== $resource_id) {
                $select->where->in('resource_id', (array)$resource_id);
            }
            // null or true values of $list_id have different meanings in the
            // context of the destroyResourceTagsLinksForUser() call above, since
            // some tags have a null $list_id value. In the case of user_resource
            // rows, however, every row has a non-null $list_id value, so the
            // two cases are equivalent and may be handled identically.
            if (null !== $list_id && true !== $list_id) {
                $select->where->equalTo('list_id', $list_id);
            }
        };

        // Delete the rows:
        $this->delete($callback);
    }

    /**
     * Get statistics on use of lists.
     *
     * @return array
     */
    public function getStatistics()
    {
        $select = $this->sql->select();
        $select->columns(
            [
                'users' => new Expression(
                    'COUNT(DISTINCT(?))',
                    ['user_id'],
                    [Expression::TYPE_IDENTIFIER]
                ),
                'lists' => new Expression(
                    'COUNT(DISTINCT(?))',
                    ['list_id'],
                    [Expression::TYPE_IDENTIFIER]
                ),
                'resources' => new Expression(
                    'COUNT(DISTINCT(?))',
                    ['resource_id'],
                    [Expression::TYPE_IDENTIFIER]
                ),
                'total' => new Expression('COUNT(*)'),
            ]
        );
        $statement = $this->sql->prepareStatementForSqlObject($select);
        $result = $statement->execute();
        return (array)$result->current();
    }

    /**
     * Get a list of duplicate rows (this sometimes happens after merging IDs,
     * for example after a Summon resource ID changes).
     *
     * @return mixed
     */
    public function getDuplicates()
    {
        $callback = function ($select) {
            $select->columns(
                [
                    'resource_id' => new Expression(
                        'MIN(?)',
                        ['resource_id'],
                        [Expression::TYPE_IDENTIFIER]
                    ),
                    'list_id' => new Expression(
                        'MIN(?)',
                        ['list_id'],
                        [Expression::TYPE_IDENTIFIER]
                    ),
                    'user_id' => new Expression(
                        'MIN(?)',
                        ['user_id'],
                        [Expression::TYPE_IDENTIFIER]
                    ),
                    'cnt' => new Expression(
                        'COUNT(?)',
                        ['resource_id'],
                        [Expression::TYPE_IDENTIFIER]
                    ),
                    'id' => new Expression(
                        'MIN(?)',
                        ['id'],
                        [Expression::TYPE_IDENTIFIER]
                    ),
                ]
            );
            $select->group(['resource_id', 'list_id', 'user_id']);
            $select->having('COUNT(resource_id) > 1');
        };
        return $this->select($callback);
    }

    /**
     * Deduplicate rows (sometimes necessary after merging foreign key IDs).
     *
     * @return void
     */
    public function deduplicate()
    {
        foreach ($this->getDuplicates() as $dupe) {
            // Do this as a transaction to prevent odd behavior:
            $connection = $this->getAdapter()->getDriver()->getConnection();
            $connection->beginTransaction();

            // Merge notes together...
            $mainCriteria = [
                'resource_id' => $dupe['resource_id'],
                'list_id' => $dupe['list_id'],
                'user_id' => $dupe['user_id'],
            ];
            $dupeRows = $this->select($mainCriteria);
            $notes = [];
            foreach ($dupeRows as $row) {
                if (!empty($row['notes'])) {
                    $notes[] = $row['notes'];
                }
            }
            $this->update(
                ['notes' => implode(' ', $notes)],
                ['id' => $dupe['id']]
            );
            // Now delete extra rows...
            $callback = function ($select) use ($dupe, $mainCriteria) {
                // match on all relevant IDs in duplicate group
                $select->where($mainCriteria);
                // getDuplicates returns the minimum id in the set, so we want to
                // delete all of the duplicates with a higher id value.
                $select->where->greaterThan('id', $dupe['id']);
            };
            $this->delete($callback);

            // Done -- commit the transaction:
            $connection->commit();
        }
    }
}
