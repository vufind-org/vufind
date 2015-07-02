<?php

/**
 * SOLR NamedList with parameter json.nl=arrarr.
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
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */
namespace VuFindSearch\Backend\Solr\Response\Json;

use Countable, Iterator;

/**
 * SOLR NamedList with parameter json.nl=arrarr.
 *
 * A NamedList arrarr represent a NamedList as an array of two element arrays
 * [[name1,val1], [name2, val2], [name3,val3]].
 *
 * @category VuFind2
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
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
    public function count()
    {
        return count($this->list);
    }

    /// Iterator

    /**
     * Return current element value.
     *
     * @return mixed
     */
    public function current()
    {
        return $this->valid() ? $this->current[1] : null;
    }

    /**
     * Return current element name.
     *
     * @return string
     */
    public function key()
    {
        return $this->valid() ? $this->current[0] : null;
    }

    /**
     * Move to next element.
     *
     * @return void
     */
    public function next()
    {
        $this->current = next($this->list);
    }

    /**
     * Return true if the iterator is at a valid position.
     *
     * @return boolean
     */
    public function valid()
    {
        return is_array($this->current);
    }

    /**
     * Rewind iterator.
     *
     * @return void
     */
    public function rewind()
    {
        reset($this->list);
        $this->current = current($this->list);
    }

    /**
     * Remove element from list.
     *
     * @return void
     */
    public function remove()
    {
        unset($this->list[key($this->list)]);
    }
}