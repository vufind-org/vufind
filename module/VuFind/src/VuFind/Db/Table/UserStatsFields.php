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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Db_Table
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace VuFind\Db\Table;

/**
 * Table Definition for statistics
 *
 * @category VuFind2
 * @package  Db_Table
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
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
        /* TODO
        // Statistics data
        foreach ($stats as $field=>$value) {
            if (gettype($value) == "boolean") {
                $value = ($value) ? "true":"false";
            }
            $this->insert(
                array(
                    'id'    => $userData['id'],
                    'field' => $field . "",
                    'value' => $value . "",
                )
            );
        }
        // User data
        $userStats = new VuFind_Model_Db_UserStats();
        $userStats->insert($userData);
         */
    }

    /**
     * Get data for these fields
     *
     * @param array $fields What fields of data are we researching?
     * @param array $values Values to match while we search
     *
     * @return associative array
     */
    public function getFields($fields, $values = array())
    {
        /* TODO
        if (empty($fields)) {
            return null;
        }
        if (!is_array($fields)) {
            $fields = array($fields);
        }
        // Build select
        $select = $this->select()
            ->setIntegrityCheck(false);
        $select->from(
            $this->_name,
            array($fields[0] => 'value')
        );
        $select->where($this->_name.'.field = ?', $fields[0]);
        for ($i=1;$i<count($fields);$i++) {
            $select->where('field'.$i.'.field = ?', $fields[$i]);
            $select->join(
                array('field'.$i => $this->_name),
                $this->_name.'.id=field'.$i.'.id',
                array($fields[$i] => 'field'.$i.'.value')
            );
        }
        foreach ($values as $key=>$value) {
            $select->where($this->_name.'.'.$key.' = ?', $value);
        }
        $stmt = $select->query();
        return $stmt->fetchAll();
         */
    }

    /**
     * Get the number of hits for a field
     *
     * @param string  $field name of the field we want
     * @param integer $value a specific value to count
     *
     * @return integer
     */
    public function getCount($field, $value = null)
    {
        /* TODO
        $select = $this->select();
        $select->from(
            array($this->_name),
            array(
                'count' => 'COUNT(value)'
            )
        );
        $select->where('field = ?', $field);
        if ($value != null) {
            $select->where('value = ?', $value);
        }
        $stmt = $select->query();
        $result = $stmt->fetch();
        return $result['count'];
         */
    }

    /**
     * Get the most frequent hits in a field
     *
     * @param string  $field  name of the field we want
     * @param integer $number result limit
     *
     * @return associative array
     */
    public function getTop($field, $number)
    {
        /* TODO
        $select = $this->select();
        $select->from(
            array($this->_name),
            array(
                'value',
                'count' => 'COUNT(value)'
            )
        );
        $select->limit($number);
        $select->group('value');
        $select->order('count DESC');
        $select->where('field = ?', $field);
        $stmt = $select->query();
        $result = $stmt->fetchAll();
        $top = array();
        $emptyIndex = -1;
        foreach ($result as $row) {
            if ($row['value'] == "" || $row['value'] == '*:*') {
                $row['value'] = '(empty)';
                if ($emptyIndex == -1) {
                    $emptyIndex = count($top);
                } else {
                    $top[$emptyIndex]['count'] += $row['count'];
                    continue;
                }
            }
            $top[] = array(
                'value' => $row['value'],
                'count' => $row['count']
            );
        }
        return $top;
         */
    }
}