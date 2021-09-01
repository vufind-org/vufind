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

use VuFindSearch\Backend\BackendInterface;
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
class RawJsonSearchCommand extends \VuFindSearch\Command\AbstractBase
{
    /**
     * Constructor
     *
     * @param string        $backend Search backend identifier
     * @param AbstractQuery $query   Search query string
     * @param ?ParamBag     $params  Search backend parameters
     */
    public function __construct(
        string $backend,
        AbstractQuery $query,
        ParamBag $params
    ) {
        parent::__construct($backend, $query, $params);
    }

    /**
     * Execute command on backend.
     *
     * @param BackendInterface $backend Backend
     *
     * @return CommandInterface Command instance for method chaining
     */
    public function execute(BackendInterface $backend): CommandInterface
    {
        if (!($backend instanceof \VuFindSearch\Backend\Solr\Backend)) {
            throw new \Exception('Unexpected backend: ' . get_class($backend));
        }
        $queryBuilder = $backend->getQueryBuilder();
        $params = $this->getSearchParameters();
        $params->mergeWith($queryBuilder->build($this->getContext()));
        $params->set('wt', 'json');
        return $this->finalizeExecution(
            json_decode($backend->getConnector()->search($params))
        );
    }
}
