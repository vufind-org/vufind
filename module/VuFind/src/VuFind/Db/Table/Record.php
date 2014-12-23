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

    public function updateRecord($id, $source, $rawData, $recordId, $userId, $sessionId) {
        
        $record = $this->findRecord(array($id));
        if (empty($record)) {
            $record = $this->createRow();
        } 
        
        $record->c_id = $id;
        $record->record_id = $recordId;
        $record->data = str_replace("Katze", "CACHED_Katze_CACHED", json_encode($rawData));
        $record->source = $source;
        $record->user_id = $userId;
        $record->session_id = $sessionId;
        $record->updated = date('Y-m-d H:i:s');
    
        // Save the new row.
        $record->save();
        
        return $record;
    }

    protected function getCacheId($recordId, $listId, $userId, $sessionId)
    {
        return $recordId;
    }
}