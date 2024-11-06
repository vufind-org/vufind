<?php

/**
 * SOLR spellcheck information.
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

use ArrayObject;
use Countable;
use IteratorAggregate;
use Traversable;

use function is_array;
use function strlen;

/**
 * SOLR spellcheck information.
 *
 * @category VuFind
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class Spellcheck implements IteratorAggregate, Countable
{
    /**
     * Spellcheck terms mapped to term information.
     *
     * @var ArrayObject
     */
    protected $terms;

    /**
     * Spelling query that generated suggestions
     *
     * @var string
     */
    protected $query;

    /**
     * Secondary spelling suggestions (in case merged results are not useful).
     *
     * @var Spellcheck
     */
    protected $secondary = false;

    /**
     * Constructor.
     *
     * @param array  $spellcheck SOLR spellcheck information
     * @param string $query      Spelling query that generated suggestions
     *
     * @return void
     */
    public function __construct(array $spellcheck, $query)
    {
        $this->terms = new ArrayObject();
        // Solr 6.4 and before use an array of arrays with two elements, while
        // from Solr 6.5 on the array is associative.
        $list = isset($spellcheck[0]) ? new NamedList($spellcheck) : $spellcheck;
        foreach ($list as $term => $info) {
            if (is_array($info)) {
                $this->terms->offsetSet($term, $info);
            }
        }
        $this->query = $query;
    }

    /**
     * Get spelling query.
     *
     * @return string
     */
    public function getQuery()
    {
        return $this->query;
    }

    /**
     * Get secondary suggestions (or return false if none exist).
     *
     * @return Spellcheck|bool
     */
    public function getSecondary()
    {
        return $this->secondary;
    }

    /**
     * Merge in other spellcheck information.
     *
     * @param Spellcheck $spellcheck Other spellcheck information
     *
     * @return void
     */
    public function mergeWith(Spellcheck $spellcheck)
    {
        // Merge primary suggestions:
        $this->terms->uksort([$this, 'compareTermLength']);
        foreach ($spellcheck as $term => $info) {
            if (!$this->contains($term)) {
                $this->terms->offsetSet($term, $info);
            }
        }

        // Store secondary suggestions in case merge yielded useless
        // result set:
        if (!$this->secondary) {
            $this->secondary = $spellcheck;
        } else {
            $this->secondary->mergeWith($spellcheck);
        }
    }

    /// IteratorAggregate

    /**
     * Return aggregated iterator.
     *
     * @return Traversable
     */
    public function getIterator(): Traversable
    {
        return $this->terms->getIterator();
    }

    /// Countable

    /**
     * Return number of terms.
     *
     * @return int
     */
    public function count(): int
    {
        return $this->terms->count();
    }

    /// Internal API

    /**
     * Return true if we already have information for the term.
     *
     * @param string $term Term to check
     *
     * @return bool
     */
    protected function contains(string $term)
    {
        if ($this->terms->offsetExists($term)) {
            return true;
        }

        $qTerm = preg_quote($term, '/');
        $length = strlen($term);
        foreach (array_keys((array)$this->terms) as $key) {
            if ($length > strlen($key)) {
                return false;
            }
            if (strstr($key, $term) && preg_match("/\b$qTerm\b/u", $key)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Compare length of two terms such that terms are sorted by descending
     * length.
     *
     * This method belongs to the internal API but must be declared public in
     * order to be used for ArrayObject::uksort().
     *
     * @param string $a First term
     * @param string $b Second term
     *
     * @return integer
     *
     * @see http://www.php.net/manual/en/arrayobject.uksort.php
     */
    public function compareTermLength($a, $b)
    {
        return strlen($b) - strlen($a);
    }
}
