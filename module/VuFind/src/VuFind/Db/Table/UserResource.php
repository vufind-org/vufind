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
}
