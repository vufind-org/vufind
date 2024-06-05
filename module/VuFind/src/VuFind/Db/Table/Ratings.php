<?php

/**
 * Table Definition for ratings
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
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Db\Table;

use Laminas\Db\Adapter\Adapter;
use Laminas\Db\Sql\Expression;
use Laminas\Db\Sql\Select;
use VuFind\Db\Row\RowGateway;
use VuFind\Db\Service\DbServiceAwareInterface;
use VuFind\Db\Service\DbServiceAwareTrait;
use VuFind\Db\Service\ResourceServiceInterface;

/**
 * Table Definition for ratings
 *
 * @category VuFind
 * @package  Db_Table
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class Ratings extends Gateway implements DbServiceAwareInterface
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
        $table = 'ratings'
    ) {
        parent::__construct($adapter, $tm, $cfg, $rowObj, $table);
    }

    /**
     * Get average rating and rating count associated with the specified resource.
     *
     * @param string $id     Record ID to look up
     * @param string $source Source of record to look up
     * @param ?int   $userId User ID, or null for all users
     *
     * @return array Array with keys count and rating (between 0 and 100)
     */
    public function getForResource(string $id, string $source, ?int $userId): array
    {
        $resourceService = $this->getDbService(ResourceServiceInterface::class);
        $resource = $resourceService->getResourceByRecordId($id, $source);
        if (!$resource) {
            return [
                'count' => 0,
                'rating' => 0,
            ];
        }

        $callback = function ($select) use ($resource, $userId) {
            $select->columns(
                [
                    // RowGateway requires an id field:
                    'id' => new Expression(
                        '1',
                        [],
                        [Expression::TYPE_IDENTIFIER]
                    ),
                    'count' => new Expression(
                        'COUNT(?)',
                        [Select::SQL_STAR],
                        [Expression::TYPE_IDENTIFIER]
                    ),
                    'rating' => new Expression(
                        'FLOOR(AVG(?))',
                        ['rating'],
                        [Expression::TYPE_IDENTIFIER]
                    ),
                ]
            );
            $select->where->equalTo('ratings.resource_id', $resource->id);
            if (null !== $userId) {
                $select->where->equalTo('ratings.user_id', $userId);
            }
        };

        $result = $this->select($callback)->current();
        return [
            'count' => $result->count ?? 0,
            'rating' => $result->rating ?? 0,
        ];
    }

    /**
     * Get rating breakdown for the specified resource.
     *
     * @param string $id     Record ID to look up
     * @param string $source Source of record to look up
     * @param array  $groups Group definition (key => [min, max])
     *
     * @return array Array with keys count and rating (between 0 and 100) as well as
     * an groups array with ratings from lowest to highest
     */
    public function getCountsForResource(
        string $id,
        string $source,
        array $groups
    ): array {
        $result = [
            'count' => 0,
            'rating' => 0,
            'groups' => [],
        ];
        foreach (array_keys($groups) as $key) {
            $result['groups'][$key] = 0;
        }

        $resourceService = $this->getDbService(ResourceServiceInterface::class);
        $resource = $resourceService->getResourceByRecordId($id, $source);
        if (!$resource) {
            return $result;
        }

        $callback = function ($select) use ($resource) {
            $select->columns(
                [
                    // RowGateway requires an id field:
                    'id' => new Expression(
                        '1',
                        [],
                        [Expression::TYPE_IDENTIFIER]
                    ),
                    'count' => new Expression(
                        'COUNT(?)',
                        [Select::SQL_STAR],
                        [Expression::TYPE_IDENTIFIER]
                    ),
                    'rating' => 'rating',
                ]
            );
            $select->where->equalTo('ratings.resource_id', $resource->id);
            $select->group('rating');
        };

        $ratingTotal = 0;
        $groupCount = 0;
        foreach ($this->select($callback) as $rating) {
            $result['count'] += $rating->count;
            $ratingTotal += $rating->rating;
            ++$groupCount;
            if ($groups) {
                foreach ($groups as $key => $range) {
                    if (
                        $rating->rating >= $range[0] && $rating->rating <= $range[1]
                    ) {
                        $result['groups'][$key] = ($result['groups'][$key] ?? 0)
                            + $rating->count;
                    }
                }
            }
        }
        $result['rating'] = $groupCount ? floor($ratingTotal / $groupCount) : 0;
        return $result;
    }

    /**
     * Deletes all ratings by a user.
     *
     * @param \VuFind\Db\Row\User $user User object
     *
     * @return void
     */
    public function deleteByUser(\VuFind\Db\Row\User $user): void
    {
        $this->delete(['user_id' => $user->id]);
    }

    /**
     * Get statistics on use of ratings.
     *
     * @return array
     */
    public function getStatistics(): array
    {
        $select = $this->sql->select();
        $select->columns(
            [
                'users' => new Expression(
                    'COUNT(DISTINCT(?))',
                    ['user_id'],
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
}
