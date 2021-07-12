<?php

/**
 * Get search terms command.
 *
 * PHP version 7
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
namespace VuFindSearch\Command;

use VuFindSearch\Feature\QueryAnalysisInterface;
use VuFindSearch\ParamBag;
use VuFindSearch\Query\QueryInterface;

/**
 * Get search terms command.
 *
 * @category VuFind
 * @package  Search
 * @author   Aleksi Peebles <aleksi.peebles@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class GetSearchTermsCommand extends CallMethodCommand
{
    /**
     * GetSearchTermsCommand constructor.
     *
     * @param string         $backend Search backend identifier
     * @param QueryInterface $query   Search query
     * @param ?ParamBag      $params  Search backend parameters
     */
    public function __construct(string $backend, QueryInterface $query,
        ?ParamBag $params = null
    ) {
        parent::__construct(
            $backend, QueryAnalysisInterface::class, 'getSearchTerms', [$query],
            $params
        );
    }
}
