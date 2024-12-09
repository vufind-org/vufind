<?php

/**
 * Hierarchy Controller
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010-2023.
 * Copyright (C) The National Library of Finland 2024.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */

namespace VuFind\Controller;

use Laminas\Stdlib\ResponseInterface;

use function array_slice;
use function count;
use function is_object;

/**
 * Hierarchy Controller
 *
 * @category VuFind
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
class HierarchyController extends AbstractBase
{
    /**
     * Output JSON
     *
     * @param array $result Result to be encoded as JSON
     * @param int   $status Response status code
     *
     * @return ResponseInterface
     */
    protected function outputJSON(array $result, int $status = 200): ResponseInterface
    {
        $response = $this->getResponse();
        $headers = $response->getHeaders();
        $headers->addHeaderLine('Content-type', 'application/json');
        $response->setContent(json_encode($result));
        $response->setStatusCode($status);
        return $response;
    }

    /**
     * Gets a Hierarchy Tree
     *
     * @return mixed
     */
    public function gettreeAction()
    {
        $this->disableSessionWrites();  // avoid session write timing bug

        $id = $this->params()->fromQuery('id');
        $source = $this->params()->fromQuery('sourceId', DEFAULT_SEARCH_BACKEND);
        $loader = $this->getRecordLoader();
        $message = 'Service Unavailable'; // default error message
        try {
            $recordDriver = $loader->load($id, $source);
            $hierarchyDriver = $recordDriver->tryMethod('getHierarchyDriver');
            if (is_object($hierarchyDriver)) {
                return $this->outputJSON([
                    'html' => $hierarchyDriver->render(
                        $recordDriver,
                        $this->params()->fromQuery('context', 'Record'),
                        'List',
                        $this->params()->fromQuery('hierarchyId', ''),
                        $this->params()->fromQuery(),
                    ),
                ]);
            }
        } catch (\Exception $e) {
            // Let exceptions fall through to error condition below:
            $message = APPLICATION_ENV === 'development' ? (string)$e : 'Unexpected exception';
        }

        // If we got this far, something went wrong:
        $code = 503;
        $response = ['error' => compact('code', 'message')];
        return $this->outputJSON($response, $code);
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

        $results = $this->getService(\VuFind\Search\Results\PluginManager::class)->get($source);
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
        return $this->outputJSON($returnArray);
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
