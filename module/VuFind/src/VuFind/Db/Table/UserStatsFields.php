<?php
/**
 * Table Definition for statistics
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

/**
 * Table Definition for statistics
 *
 * @category VuFind
 * @package  Db_Table
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class UserStatsFields extends Gateway
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct('user_stats_fields');
    }

    /**
     * Save data to the DB, data to statistics, user data in user_stats
     *
     * @param array $stats    data indexed by column name
     * @param array $userData data indexed by
     * id, timestamp, browser, ipaddress, referrer, url
     *
     * @return null
     */
    public function save($stats, $userData)
    {
        // Statistics data
        foreach ($stats as $field => $value) {
            if (gettype($value) == "boolean") {
                $value = ($value) ? "true" : "false";
            }
            $this->insert(
                [
                    'id'    => $userData['id'],
                    'field' => $field . "",
                    'value' => $value . "",
                ]
            );
        }
        // User data
        $userStats = $this->getDbTable('UserStats');
        $userStats->insert($userData);
    }

    /**
     * Get data for these fields
     *
     * @param array $fields What fields of data are we researching?
     * @param array $values Values to match while we search
     *
     * @return associative array
     */
    public function getFields($fields, $values = [])
    {
        if (empty($fields)) {
            return null;
        }
        if (!is_array($fields)) {
            $fields = [$fields];
        }
        $callback = function ($select) use ($fields, $values) {
            $select->columns(
                [$fields[0] => 'value']
            );
            $select->where->equalTo('field', $fields[0]);
            for ($i = 1;$i < count($fields);$i++) {
                $select->where->equalTo('field' . $i . '.field', $fields[$i]);
                $select->join(
                    ['field' . $i => 'user_stats_fields'],
                    'user_stats_fields.id=field' . $i . '.id',
                    [$fields[$i] => 'field' . $i . '.value']
                );
            }
            foreach ($values as $key => $value) {
                $select->where->equalTo($key, $value);
            }
        };
        
        return $this->select($callback);
    }
}
