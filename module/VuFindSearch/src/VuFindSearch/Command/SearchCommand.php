<?php

/**
 * Perform a search and return record collection command.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
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
 * @author   David Maus <maus@hab.de>
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */

namespace VuFindSearch\Command;

use VuFindSearch\Backend\BackendInterface;
use VuFindSearch\Command\Feature\QueryOffsetLimitTrait;
use VuFindSearch\ParamBag;
use VuFindSearch\Query\QueryInterface;

/**
 * Perform a search and return record collection command.
 *
 * @category VuFind
 * @package  Search
 * @author   David Maus <maus@hab.de>
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class SearchCommand extends CallMethodCommand
{
    use QueryOffsetLimitTrait;

    /**
     * SearchCommand constructor.
     *
     * @param string         $backendId Search backend identifier
     * @param QueryInterface $query     Search query
     * @param int            $offset    Search offset
     * @param int            $limit     Search limit
     * @param ?ParamBag      $params    Search backend parameters
     */
    public function __construct(
        string $backendId,
        QueryInterface $query,
        int $offset = 0,
        int $limit = 20,
        ?ParamBag $params = null
    ) {
        $this->query = $query;
        $this->offset = $offset;
        $this->limit = $limit;
        parent::__construct(
            $backendId,
            BackendInterface::class,
            'search',
            $params
        );
    }

    /**
     * Return search backend interface method arguments.
     *
     * @return array
     */
    public function getArguments(): array
    {
        return [
            $this->getQuery(),
            $this->getOffset(),
            $this->getLimit(),
            $this->getSearchParameters(),
        ];
    }
}
