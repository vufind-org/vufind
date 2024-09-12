<?php

/**
 * Factory for record collection.
 *
 * PHP version 8
 *
 * Copyright (C) EBSCO Industries 2013
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
 * @package  Search
 * @author   Michelle Milton <mmilton@epnet.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */

namespace VuFindSearch\Backend\EDS\Response;

use VuFindSearch\Exception\InvalidArgumentException;
use VuFindSearch\Response\RecordCollectionFactoryInterface;

use function call_user_func;
use function gettype;
use function is_array;
use function is_callable;
use function sprintf;

/**
 * Factory for record collection.
 *
 * @category VuFind
 * @package  Search
 * @author   Michelle Milton <mmilton@epnet.com>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class RecordCollectionFactory implements RecordCollectionFactoryInterface
{
    /**
     * Factory to turn data into a record object.
     *
     * @var callable
     */
    protected $recordFactory;

    /**
     * Class of collection.
     *
     * @var string
     */
    protected $collectionClass;

    /**
     * Constructor.
     *
     * @param callable $recordFactory   Record factory callback
     * @param string   $collectionClass Class of collection
     *
     * @return void
     */
    public function __construct($recordFactory = null, $collectionClass = null)
    {
        if (!is_callable($recordFactory)) {
            throw new InvalidArgumentException('Record factory must be callable.');
        }
        $this->recordFactory = $recordFactory;
        $this->collectionClass = $collectionClass ?? RecordCollection::class;
    }

    /**
     * Return record collection.
     *
     * @param array $response EdsApi search response
     *
     * @return RecordCollection
     */
    public function factory($response)
    {
        if (!is_array($response)) {
            throw new InvalidArgumentException(
                sprintf(
                    'Unexpected type of value: Expected array, got %s',
                    gettype($response)
                )
            );
        }
        $collection = new $this->collectionClass($response);
        // Obtain path to records -- first try format of the search response,
        // then try format of the retrieve response, then give up with empty array.
        $records = $response['SearchResult']['Data']['Records']
            ?? $response['Records'] ?? [];

        foreach ($records as $record) {
            $collection->add(call_user_func($this->recordFactory, $record), false);
        }
        return $collection;
    }
}
