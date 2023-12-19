<?php

/**
 * Hierarchy Controller
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010-2023.
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
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */

namespace VuFind\Controller;

use Laminas\Stdlib\ResponseInterface;

use function array_slice;
use function count;

/**
 * Hierarchy Controller
 *
 * @category VuFind
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
class HierarchyController extends AbstractBase
{
    /**
     * Output JSON
     *
     * @param string $json   A JSON string
     * @param int    $status Response status code
     *
     * @return ResponseInterface
     */
    protected function outputJSON($json, $status = 200): ResponseInterface
    {
        $response = $this->getResponse();
        $headers = $response->getHeaders();
        $headers->addHeaderLine('Content-type', 'application/json');
        $response->setContent($json);
        $response->setStatusCode($status);
        return $response;
    }

    /**
     * Search the tree and output a JSON result of items that matched the keywords.
     *
     * @return ResponseInterface
     */
    public function searchtreeAction(): ResponseInterface
    {
        $this->disableSessionWrites();  // avoid session write timing bug
        $config = $this->getConfig();
        $limit = $config->Hierarchy->treeSearchLimit;
        $resultIDs = [];
        $hierarchyID = $this->params()->fromQuery('hierarchyID');
        $source = $this->params()
            ->fromQuery('hierarchySource', DEFAULT_SEARCH_BACKEND);
        $lookfor = $this->params()->fromQuery('lookfor', '');
        $searchType = $this->params()->fromQuery('type', 'AllFields');

        $results = $this->serviceLocator
            ->get(\VuFind\Search\Results\PluginManager::class)->get($source);
        $results->getParams()->setBasicSearch($lookfor, $searchType);
        $results->getParams()->addFilter('hierarchy_top_id:' . $hierarchyID);
        $facets = $results->getFullFieldFacets(['id'], false, null === $limit ? -1 : $limit + 1);

        $callback = function ($data) {
            return $data['value'];
        };
        $resultIDs = isset($facets['id']['data']['list'])
            ? array_map($callback, $facets['id']['data']['list']) : [];

        $limitReached = ($limit > 0 && count($resultIDs) > $limit);

        $returnArray = [
            'limitReached' => $limitReached,
            'results' => array_slice($resultIDs, 0, $limit),
        ];
        return $this->outputJSON(json_encode($returnArray));
    }

    /**
     * Get a record for display within a tree
     *
     * @return ResponseInterface
     */
    public function getrecordAction(): ResponseInterface
    {
        $id = $this->params()->fromQuery('id');
        $source = $this->params()->fromQuery('source', DEFAULT_SEARCH_BACKEND);
        $loader = $this->getRecordLoader();
        try {
            $record = $loader->load($id, $source);
            $result = $this->getViewRenderer()->record($record)->getCollectionBriefRecord();
        } catch (\VuFind\Exception\RecordMissing $e) {
            $result = $this->getViewRenderer()->render('collection/collection-record-error.phtml');
        }
        $response = $this->getResponse();
        $response->setContent($result);
        return $response;
    }
}
