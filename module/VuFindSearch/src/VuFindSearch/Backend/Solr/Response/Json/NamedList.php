<?php

/**
 * SOLR NamedList with parameter json.nl=arrarr.
 *
 * PHP version 8
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
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */

namespace VuFindSearch\Backend\Solr\Response\Json;

use Countable;
use Iterator;

use function count;
use function in_array;
use function is_array;

/**
 * SOLR NamedList with parameter json.nl=arrarr.
 *
 * A NamedList arrarr represent a NamedList as an array of two element arrays
 * [[name1,val1], [name2, val2], [name3,val3]].
 *
 * @category VuFind
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 * @see      http://wiki.apache.org/solr/SolJSON
 */
class NamedList implements Countable, Iterator
{
    /**
     * The named list.
     *
     * @var array
     */
    protected $list;

    /**
     * The current position
     *
     * @var array
     */
    protected $current = null;

    /**
     * Constructor.
     *
     * @param array $list Named list
     *
     * @return void
     */
    public function __construct(array $list)
    {
        $this->list = $list;
    }

    /**
     * Convert the named list into a standard associative array.
     *
     * @return array
     */
    public function toArray()
    {
        $arr = [];
        foreach ($this as $k => $v) {
            $arr[$k] = $v;
        }
        return $arr;
    }

    /// Countable

    /**
     * Return count of elements.
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->list);
    }

    /// Iterator

    /**
     * Return current element value.
     *
     * @return mixed
     */
    public function current(): mixed
    {
        return $this->valid() ? $this->current[1] : null;
    }

    /**
     * Return current element name.
     *
     * @return string
     */
    public function key(): mixed
    {
        return $this->valid() ? $this->current[0] : null;
    }

    /**
     * Move to next element.
     *
     * @return void
     */
    public function next(): void
    {
        $this->current = next($this->list);
    }

    /**
     * Return true if the iterator is at a valid position.
     *
     * @return bool
     */
    public function valid(): bool
    {
        return is_array($this->current);
    }

    /**
     * Rewind iterator.
     *
     * @return void
     */
    public function rewind(): void
    {
        reset($this->list);
        $this->current = current($this->list);
    }

    /**
     * Remove single element from list.
     *
     * @param string $key Key to remove
     *
     * @return void
     */
    public function removeKey($key)
    {
        $this->removeKeys([$key]);
    }

    /**
     * Remove elements from list.
     *
     * @param array $keys Keys to remove
     *
     * @return void
     */
    public function removeKeys(array $keys)
    {
        $newList = [];
        foreach ($this->list as $current) {
            if (!in_array($current[0], $keys)) {
                $newList[] = $current;
            }
        }
        $this->list = $newList;
        $this->rewind();
    }
}
