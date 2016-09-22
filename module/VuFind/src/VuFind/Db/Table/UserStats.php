<?php
/**
 * Table Definition for statistics (user data)
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

use Zend\Db\Sql\Expression;

/**
 * Table Definition for user statistics
 *
 * @category VuFind
 * @package  Db_Table
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class UserStats extends Gateway
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct('user_stats');
    }

    /**
     * Returns the list of most popular browsers with use counts
     *
     * @param bool $withVersions Include browser version numbers?
     * True = versions (Firefox 12.0) False = names only (Firefox).
     * @param int  $limit        How many to return
     *
     * @return array
     */
    public function getBrowserStats($withVersions = false, $limit = 5)
    {
        $callback = function ($select) use ($withVersions, $limit) {
            if ($withVersions) {
                $select->columns(
                    [
                        'browserName' => new Expression(
                            'CONCAT_WS(" ",?,?)',
                            ['browser', 'browserVersion'],
                            [
                                Expression::TYPE_IDENTIFIER,
                                Expression::TYPE_IDENTIFIER
                            ]
                        ),
                        'count' => new Expression(
                            'COUNT(DISTINCT (?))',
                            ['session'],
                            [Expression::TYPE_IDENTIFIER]
                        )
                    ]
                );
                $select->group('browserName');
            } else {
                $select->columns(
                    [
                        'browserName' => 'browser',
                        'count' => new Expression(
                            'COUNT(DISTINCT (?))',
                            ['session'],
                            [Expression::TYPE_IDENTIFIER]
                        )
                    ]
                );
                $select->group('browser');
            }
            $select->limit($limit);
            $select->order('count DESC');
        };
        
        return $this->select($callback)->toArray();
    }
}
