<?php

/**
 * Database service for search.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2024.
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
 * @package  Database
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */

namespace VuFind\Db\Service;

use VuFind\Db\Table\DbTableAwareInterface;
use VuFind\Db\Table\DbTableAwareTrait;

use function count;

/**
 * Database service for search.
 *
 * @category VuFind
 * @package  Database
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:database_gateways Wiki
 */
class SearchService extends AbstractDbService implements SearchServiceInterface, DbTableAwareInterface
{
    use DbTableAwareTrait;

    /**
     * Set invalid user_id values in the table to null; return count of affected rows.
     *
     * @return int
     */
    public function cleanUpInvalidUserIds(): int
    {
        $searchTable = $this->getDbTable('search');
        $allIds = $this->getDbTable('user')->getSql()->select()->columns(['id']);
        $searchCallback = function ($select) use ($allIds) {
            $select->where->equalTo('user_id', '0')
                ->OR->notIn('user_id', $allIds);
        };
        $badRows = $searchTable->select($searchCallback);
        $count = count($badRows);
        if ($count > 0) {
            $searchTable->update(['user_id' => null], $searchCallback);
        }
        return $count;
    }
}
