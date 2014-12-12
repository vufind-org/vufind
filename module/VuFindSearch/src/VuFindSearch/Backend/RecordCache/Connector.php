<?php

/**
 * Class for connection the record cache.
 *
 * PHP version 5
 *
 * Copyright (C) 2014 University of Freiburg.
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
 * @package  Search
 * @author   Markus Beh <markus.beh@ub.uni-freiburg.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */

namespace VuFindSearch\Backend\RecordCache;

use VuFindSearch\ParamBag;
use Zend\Log\LoggerInterface;

/**
 * Class for connecting the record cache.
 *
 * @category VuFind2
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */

class Connector
{

    protected $databaseManager = null;

    public function __construct($databaseManager)
    {
        $this->databaseManager = $databaseManager;
    }

    public function retrieve($id, ParamBag $params = null)
    {
        $recordTable = $this->databaseManager->get('record');
        $recordDetails = $params->get('id');
        $record = $recordTable->findRecord($id, null, null, null, $recordDetails['userId'], $recordDetails['listId'] );
        $response = $this->buildResponse($record);
        
        return $response;
    }

    public function retrieveBatch($ids, ParamBag $params = null)
    {
        $result = array();
        
        $recordTable = $this->databaseManager->get('record');
        foreach ($ids as $id) {
            $recordDetails = $params->get('id');
            $record = $recordTable->findRecord($id, null, null, null, $recordDetails['userId'], $recordDetails['listId'] );
            $response[] = $this->buildResponse($record);
        }
        
        return $response;
    }

    protected function buildResponse($record)
    {
        $response = array();
        
        $response[] = array(
            'source' => $record['source'],
            'data' => json_decode($record['data'], true)
        );
        return $response;
    }

    public function search(ParamBag $params)
    {
        return array();
    }

    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }
}
