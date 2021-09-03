<?php

/**
 * Command to perform a Solr search and return a decoded JSON response
 * free from additional processing.
 *
 * PHP version 7
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
 * @package  GeoFeatures
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
namespace VuFindSearch\Backend\Solr\Command;

use VuFindSearch\Backend\Solr\Backend;
use VuFindSearch\Command\CommandInterface;
use VuFindSearch\ParamBag;
use VuFindSearch\Query\AbstractQuery;

/**
 * Command to perform a Solr search and return a decoded JSON response
 * free from additional processing.
 *
 * @category VuFind
 * @package  GeoFeatures
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class RawJsonSearchCommand extends \VuFindSearch\Command\CallMethodCommand
{
    /**
     * Constructor
     *
     * @param string        $backend Search backend identifier
     * @param AbstractQuery $query   Search query string
     * @param int           $offset  Search offset
     * @param int           $limit   Search limit
     * @param ?ParamBag     $params  Search backend parameters
     */
    public function __construct(
        string $backend,
        AbstractQuery $query,
        int $offset = 0,
        int $limit = 100,
        ParamBag $params = null
    ) {
        parent::__construct(
            $backend,
            Backend::class,
            'rawJsonSearch',
            [$query, $offset, $limit],
            $params
        );
    }

    /**
     * Save a result, flag the command as executed, and return the command object;
     * useful as the final step in execute() implementations.
     *
     * @param mixed $result Result of execution.
     *
     * @return CommandInterface
     */
    protected function finalizeExecution($result): CommandInterface
    {
        // We should JSON-decode the result when we save it, for convenience:
        return parent::finalizeExecution(json_decode($result));
    }
}
