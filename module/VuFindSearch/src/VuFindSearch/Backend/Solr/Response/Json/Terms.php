<?php

/**
 * SOLR Terms component.
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
use IteratorAggregate;
use Traversable;

/**
 * SOLR Terms component.
 *
 * @category VuFind
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class Terms implements IteratorAggregate
{
    /**
     * Terms, indexed by field.
     *
     * @var ArrayObject
     */
    protected $terms;

    /**
     * Constructor.
     *
     * @param array $terms Term information
     *
     * @return void
     */
    public function __construct(array $terms)
    {
        $terms = array_replace(
            ['responseHeader' => [], 'terms' => []],
            $terms
        );
        $this->terms = new ArrayObject();
        foreach ($terms['terms'] as $field => $info) {
            $this->terms->offsetSet($field, new NamedList($info));
        }
    }

    /**
     * Return aggregated iterator.
     *
     * @return Traversable
     */
    public function getIterator(): Traversable
    {
        return $this->terms->getIterator();
    }

    /**
     * Get terms for the specified field
     *
     * @param string $field Field name
     *
     * @return array
     */
    public function getFieldTerms($field)
    {
        if ($this->hasFieldTerms($field)) {
            return $this->terms->offsetGet($field);
        }
        return null;
    }

    /**
     * Does the requested field exist?
     *
     * @param string $field Field name
     *
     * @return bool
     */
    public function hasFieldTerms($field)
    {
        return $this->terms->offsetExists($field);
    }
}
