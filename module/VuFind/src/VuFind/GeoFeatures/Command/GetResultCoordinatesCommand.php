<?php

/**
 * Command to fetch map coordinates for search results.
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
namespace VuFind\GeoFeatures\Command;

use VuFindSearch\Backend\BackendInterface;
use VuFindSearch\Command\CommandInterface;

/**
 * Command to fetch map coordinates for search results.
 *
 * @category VuFind
 * @package  GeoFeatures
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class GetResultCoordinatesCommand extends \VuFindSearch\Command\AbstractBase
{
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
        $context = $this->getContext();
        $queryBuilder = $backend->getQueryBuilder();
        $params = $this->getSearchParameters();
        $params->mergeWith($queryBuilder->build($context['searchQuery']));
        $params->set('fl', 'id, ' . $context['geoField'] . ', title');
        $params->set('wt', 'json');
        $params->set('rows', '10000000'); // set to return all results
        $response = json_decode($backend->getConnector()->search($params));
        $this->result = [];
        foreach ($response->response->docs as $current) {
            if (!isset($current->title)) {
                $current->title = $context['defaultTitle'];
            }
            $this->result[] = [
                $current->id, $current->{$context['geoField']}, $current->title
            ];
        }
        return parent::execute($backend);
    }
}
