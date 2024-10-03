<?php

/**
 * Simple abstract factory for record collection.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010-2024.
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
 * @author   David Maus <maus@hab.de>
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */

namespace VuFindSearch\Response;

use VuFindSearch\Exception\InvalidArgumentException;

use function call_user_func;
use function gettype;
use function is_array;
use function is_callable;
use function sprintf;

/**
 * Simple factory for record collection.
 *
 * @category VuFind
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
abstract class AbstractJsonRecordCollectionFactory implements RecordCollectionFactoryInterface
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
     * @param callable $recordFactory   Record factory callback (null for default)
     * @param string   $collectionClass Class of collection
     *
     * @return void
     */
    public function __construct($recordFactory = null, $collectionClass = null)
    {
        // Set default record factory if none provided:
        if (null === $recordFactory) {
            $recordFactory = function ($i) {
                return new JsonRecord($i);
            };
        } elseif (!is_callable($recordFactory)) {
            throw new InvalidArgumentException('Record factory must be callable.');
        }
        $this->recordFactory = $recordFactory;
        $this->collectionClass = $collectionClass ?? $this->getDefaultRecordCollectionClass();
    }

    /**
     * Return record collection.
     *
     * @param array $response Backend response
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
        foreach ($this->getDocumentListFromResponse($response) as $doc) {
            $collection->add(call_user_func($this->recordFactory, $doc), false);
        }
        return $collection;
    }

    /**
     * Get the class name of the record collection to use by default.
     *
     * @return string
     */
    abstract protected function getDefaultRecordCollectionClass(): string;

    /**
     * Given a backend response, return an array of documents.
     *
     * @param array $response Backend response
     *
     * @return array
     */
    abstract protected function getDocumentListFromResponse($response): array;
}
