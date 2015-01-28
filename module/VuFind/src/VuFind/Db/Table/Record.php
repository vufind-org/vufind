<?php
/**
 * Table Definition for record
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
 * @author   Markus Beh <markus.beh@ub.uni-freiburg.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace VuFind\Db\Table;

use Zend\Db\Sql\Expression;
use Zend\Db\Sql\Sql;

/**
 * Table Definition for user statistics
 *
 * @category VuFind2
 * @package Db_Table
 * @author Markus Beh <markus.beh@ub.uni-freiburg.de>
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link http://vufind.org Main Site
 */
class Record extends Gateway
{

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct('record', 'VuFind\Db\Row\Record');
    }
    
    public function findRecord($ids)
    {
        
        if (empty($ids)) {
            throw new \Exception('Record ID cannot be empty');
        }
        
        $where = array(
            'c_id' => $ids
        );
            
        $select = $this->select($where);
        
        $records = array();
        $count = $select->count();
        for ($it = 0; $it < $count; $it++) {
            $records[] = $select->current();
            $select->next();
        }
        
        return $records;
    }

    public function updateRecord($id, $source, $rawData, $recordId, $userId, $sessionId, $resourceId) {
        
        $records = $this->findRecord(array($id));
        if (empty($records)) {
            $record = $this->createRow();
        } else {
            $record = $records[0];
        }
        
        $record->c_id = $id;
        $record->record_id = $recordId;
        $record->data = json_encode($rawData);
        $record->source = $source;
        $record->user_id = $userId;
        $record->session_id = $sessionId;
        $record->updated = date('Y-m-d H:i:s');
        $record->resource_id = $resourceId;
        
        // Create or update record.
        $record->save();
        
        return $record;
    }
    
    public function cleanup($userId) {
        $sql = new Sql($this->getAdapter());
        $select = $sql->select();
        $select->from('record');
        $select->join('user_resource', 'record.resource_id = user_resource.resource_id', array(),$select::JOIN_LEFT);
        $select->where->equalTo('record.user_id', $userId);
        $select->where->isNull('user_resource.id');

        $statement = $sql->prepareStatementForSqlObject($select);
        $results = $statement->execute();
        
        foreach($results as $result) {
           $this->delete($result['c_id']);
        }
    }

    public function delete($id) {
        $records = $this->findRecord(array($id));
        if (!empty($records)) {
            $records[0]->delete();
        }
    }
    
}