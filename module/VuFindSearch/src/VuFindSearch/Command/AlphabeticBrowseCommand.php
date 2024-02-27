<?php

/**
 * Fetch alphabrowse data from the backend (currently only supported by Solr)
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2021.
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */

namespace VuFindSearch\Command;

use VuFindSearch\Backend\Solr\Backend;
use VuFindSearch\ParamBag;

/**
 * Fetch alphabrowse data from the backend (currently only supported by Solr)
 *
 * @category VuFind
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class AlphabeticBrowseCommand extends CallMethodCommand
{
    /**
     * Name of index to search.
     *
     * @var string
     */
    protected $source;

    /**
     * Starting point for browse results.
     *
     * @var string
     */
    protected $from;

    /**
     * Result page to return.
     *
     * @var int
     */
    protected $page;

    /**
     * Number of results to return on each page.
     *
     * @var int
     */
    protected $limit;

    /**
     * Delta to use when calculating page offset.
     *
     * @var int
     */
    protected $offsetDelta;

    /**
     * Constructor.
     *
     * @param string    $backendId   Search backend identifier
     * @param string    $source      Name of index to search
     * @param string    $from        Starting point for browse results
     * @param int       $page        Result page to return (starts at 0)
     * @param int       $limit       Number of results to return on each page
     * @param ?ParamBag $params      Additional parameters
     * @param int       $offsetDelta Delta to use when calculating page
     * offset (useful for showing a few results above the highlighted row)
     */
    public function __construct(
        string $backendId,
        string $source,
        string $from,
        int $page,
        int $limit = 20,
        ParamBag $params = null,
        int $offsetDelta = 0
    ) {
        $this->source = $source;
        $this->from = $from;
        $this->page = $page;
        $this->limit = $limit;
        $this->offsetDelta = $offsetDelta;
        parent::__construct(
            $backendId,
            Backend::class, // we should define interface, if needed in more places
            'alphabeticBrowse',
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
            $this->getSource(),
            $this->getFrom(),
            $this->getPage(),
            $this->getLimit(),
            $this->getSearchParameters(),
            $this->getOffsetDelta(),
        ];
    }

    /**
     * Return name of index to search.
     *
     * @return string
     */
    public function getSource(): string
    {
        return $this->source;
    }

    /**
     * Return starting point for browse results.
     *
     * @return string
     */
    public function getFrom(): string
    {
        return $this->from;
    }

    /**
     * Return result page to return.
     *
     * @return int
     */
    public function getPage(): int
    {
        return $this->page;
    }

    /**
     * Return number of results to return on each page.
     *
     * @return int
     */
    public function getLimit(): int
    {
        return $this->limit;
    }

    /**
     * Return delta to use when calculating page offset.
     *
     * @return int
     */
    public function getOffsetDelta(): int
    {
        return $this->offsetDelta;
    }
}
