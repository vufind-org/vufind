<?php

/**
 * Fetch terms from the backend (currently only supported by Solr)
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
 * Fetch terms from the backend (currently only supported by Solr)
 *
 * @category VuFind
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class TermsCommand extends CallMethodCommand
{
    /**
     * Index field.
     *
     * @var ?string
     */
    protected ?string $field;

    /**
     * Starting term.
     *
     * @var ?string
     */
    protected ?string $start;

    /**
     * Maximum number of terms.
     *
     * @var ?int
     */
    protected ?int $limit;

    /**
     * Constructor.
     *
     * @param string    $backendId Search backend identifier
     * @param ?string   $field     Index field
     * @param ?string   $start     Starting term (blank for beginning of list)
     * @param ?int      $limit     Maximum number of terms
     * @param ?ParamBag $params    Search backend parameters
     */
    public function __construct(
        string $backendId,
        ?string $field,
        ?string $start,
        ?int $limit,
        ?ParamBag $params = null
    ) {
        $this->field = $field;
        $this->start = $start;
        $this->limit = $limit;
        parent::__construct(
            $backendId,
            Backend::class, // we should define interface, if needed in more places
            'terms',
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
            $this->getField(),
            $this->getStart(),
            $this->getLimit(),
            $this->getSearchParameters(),
        ];
    }

    /**
     * Return index field.
     *
     * @return ?string
     */
    public function getField(): ?string
    {
        return $this->field;
    }

    /**
     * Return starting term.
     *
     * @return ?string
     */
    public function getStart(): ?string
    {
        return $this->start;
    }

    /**
     * Return maximum number of terms.
     *
     * @return ?int
     */
    public function getLimit(): ?int
    {
        return $this->limit;
    }
}
