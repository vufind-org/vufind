<?php

/**
 * Command to perform a Solr search and return a decoded JSON response
 * free from additional processing.
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
 * @package  GeoFeatures
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */

namespace VuFindSearch\Backend\Solr\Command;

use VuFindSearch\Backend\Solr\Backend;
use VuFindSearch\Command\CommandInterface;
use VuFindSearch\Command\Feature\QueryOffsetLimitTrait;
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
    use QueryOffsetLimitTrait;

    /**
     * If json should be returned as an array instead an object
     *
     * @var ?bool
     */
    protected $asArray = null;

    /**
     * Constructor
     *
     * @param string        $backendId Search backend identifier
     * @param AbstractQuery $query     Search query string
     * @param int           $offset    Search offset
     * @param int           $limit     Search limit
     * @param ?ParamBag     $params    Search backend parameters
     * @param ?bool         $asArray   If json should be returned as an array instead an object
     */
    public function __construct(
        string $backendId,
        AbstractQuery $query,
        int $offset = 0,
        int $limit = 100,
        ParamBag $params = null,
        ?bool $asArray = null
    ) {
        $this->query = $query;
        $this->offset = $offset;
        $this->limit = $limit;
        $this->asArray = $asArray;
        parent::__construct(
            $backendId,
            Backend::class,
            'rawJsonSearch',
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
        return parent::finalizeExecution(json_decode($result, $this->asArray));
    }
}
