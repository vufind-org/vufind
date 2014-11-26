<?php

/**
 * Record collection factory.
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

namespace VuFindSearch\Backend\RecordCache\Response;

use VuFindSearch\Response\RecordCollectionFactoryInterface;
use VuFindSearch\Exception\InvalidArgumentException;

class RecordCollectionFactory implements RecordCollectionFactoryInterface
{

    protected $recordFactories;

    protected $collectionClass;

    public function __construct($recordFactories = null, $collectionClass = null)
    {
        // // Set default record factory if none provided:
        // if (null === $recordFactory) {
        // $recordFactory = function ($i) {
        // return new Record($i);
        // };
        // } else if (!is_callable($recordFactory)) {
        // throw new InvalidArgumentException('Record factory must be callable.');
        // }
        $this->recordFactories = $recordFactories;
        $this->collectionClass = (null === $collectionClass) ? 'VuFindSearch\Backend\RecordCache\Response\RecordCollection' : $collectionClass;
    }

    public function factory($response)
    {
        $collection = new $this->collectionClass($response);
        
        foreach ($response as $record) {
            $factory = $this->recordFactories[$record['source']];
            $doc = $record['data'];
            
            $collection->add(call_user_func($factory, $doc));
        }
        
        return $collection;
    }
}