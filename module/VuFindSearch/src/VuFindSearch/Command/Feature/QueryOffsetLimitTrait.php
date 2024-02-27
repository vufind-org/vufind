<?php

/**
 * Trait for commands with search query, offset and limit arguments.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2021.
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
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */

namespace VuFindSearch\Command\Feature;

use VuFindSearch\Query\QueryInterface;

/**
 * Trait for commands with search query, offset and limit arguments.
 *
 * @category VuFind
 * @package  Search
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
trait QueryOffsetLimitTrait
{
    /**
     * Search query.
     *
     * @var QueryInterface
     */
    protected $query;

    /**
     * Search offset.
     *
     * @var int
     */
    protected $offset;

    /**
     * Search limit.
     *
     * @var int
     */
    protected $limit;

    /**
     * Return search query.
     *
     * @return QueryInterface
     */
    public function getQuery(): QueryInterface
    {
        return $this->query;
    }

    /**
     * Set search query.
     *
     * @param QueryInterface $query Query
     *
     * @return void
     */
    public function setQuery(QueryInterface $query): void
    {
        $this->query = $query;
    }

    /**
     * Return search offset.
     *
     * @return int
     */
    public function getOffset(): int
    {
        return $this->offset;
    }

    /**
     * Set search offset.
     *
     * @param int $offset Offset
     *
     * @return void
     */
    public function setOffset($offset): void
    {
        $this->offset = $offset;
    }

    /**
     * Return search limit.
     *
     * @return int
     */
    public function getLimit(): int
    {
        return $this->limit;
    }

    /**
     * Set search limit.
     *
     * @param int $limit Limit
     *
     * @return void
     */
    public function setLimit($limit): void
    {
        $this->limit = $limit;
    }
}
