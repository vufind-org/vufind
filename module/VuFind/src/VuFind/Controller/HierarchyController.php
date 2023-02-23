<?php
/**
 * Hierarchy Controller
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2010.
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
     * XML output routine
     *
     * @param string $xml XML to output
     *
     * @return \Laminas\Http\Response
     */
    protected function output($xml)
    {
        $response = $this->getResponse();
        $headers = $response->getHeaders();
        $headers->addHeaderLine('Content-type', 'text/xml');
        $response->setContent($xml);
        return $response;
    }

    /**
     * Output JSON
     *
     * @param string $json   A JSON string
     * @param int    $status Response status code
     *
     * @return \Laminas\Http\Response
     */
    protected function outputJSON($json, $status = 200)
    {
        $response = $this->getResponse();
        $headers = $response->getHeaders();
        $headers->addHeaderLine('Content-type', 'application/json');
        $response->setContent($json);
        $response->setStatusCode($status);
        return $response;
    }

    /**
     * Search the tree and echo a json result of items that
     * matched the keywords.
     *
     * @return \Laminas\Http\Response
     */
    public function searchtreeAction()
    {
        $this->disableSessionWrites();  // avoid session write timing bug
        $config = $this->getConfig();
        $limit = $config->Hierarchy->treeSearchLimit ?? -1;
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
        $facets = $results->getFullFieldFacets(['id'], false, $limit + 1);

        $callback = function ($data) {
            return $data['value'];
        };
        $resultIDs = isset($facets['id']['data']['list'])
            ? array_map($callback, $facets['id']['data']['list']) : [];

        $limitReached = ($limit > 0 && count($resultIDs) > $limit);

        $returnArray = [
            "limitReached" => $limitReached,
            "results" => array_slice($resultIDs, 0, $limit)
        ];
        return $this->outputJSON(json_encode($returnArray));
    }

    /**
     * Gets a Hierarchy Tree
     *
     * @return mixed
     */
    public function gettreeAction()
    {
        $this->disableSessionWrites();  // avoid session write timing bug
        // Retrieve the record from the index
        $id = $this->params()->fromQuery('id');
        $source = $this->params()
            ->fromQuery('hierarchySource', DEFAULT_SEARCH_BACKEND);
        $loader = $this->getRecordLoader();
        try {
            if ($recordDriver = $loader->load($id, $source)) {
                $results = $recordDriver->getHierarchyDriver()->render(
                    $recordDriver,
                    $this->params()->fromQuery('context'),
                    $this->params()->fromQuery('mode'),
                    $this->params()->fromQuery('hierarchyID')
                );
                if ($results) {
                    return $this->output($results);
                }
            }
        } catch (\Exception $e) {
            // Let exceptions fall through to error condition below:
        }

        // If we got this far, something went wrong:
        return $this->output(
            "<error>" . $this->translate("hierarchy_tree_error") . "</error>"
        );
    }

    /**
     * Gets a Hierarchy Tree
     *
     * @return mixed
     */
    public function gettreejsonAction()
    {
        $this->disableSessionWrites();  // avoid session write timing bug

        // Retrieve the record from the index
        $id = $this->params()->fromQuery('id');
        $source = $this->params()
            ->fromQuery('hierarchySource', DEFAULT_SEARCH_BACKEND);
        $loader = $this->getRecordLoader();
        $message = 'Service Unavailable'; // default error message
        try {
            if ($recordDriver = $loader->load($id, $source)) {
                $results = $recordDriver->getHierarchyDriver()
                    ->getTreeRenderer($recordDriver)->getJSON(
                        $this->params()->fromQuery('hierarchyID'),
                        $this->params()->fromQuery('context')
                    );
                if ($results) {
                    return $this->outputJSON($results);
                } else {
                    return $this->outputJSON($results, 204); // No Content
                }
            }
        } catch (\Exception $e) {
            // Let exceptions fall through to error condition below:
            $message = APPLICATION_ENV !== 'development'
                ? (string)$e : 'Unexpected exception';
        }

        // If we got this far, something went wrong:
        $code = 503;
        $response = ['error' => compact('code', 'message')];
        return $this->outputJSON(json_encode($response), $code);
        // Service Unavailable
    }

    /**
     * Get a record for display within a tree
     *
     * @return mixed
     */
    public function getrecordAction()
    {
        $id = $this->params()->fromQuery('id');
        $source = $this->params()
            ->fromQuery('hierarchySource', DEFAULT_SEARCH_BACKEND);
        $loader = $this->getRecordLoader();
        try {
            $record = $loader->load($id, $source);
            $result = $this->getViewRenderer()->record($record)
                ->getCollectionBriefRecord();
        } catch (\VuFind\Exception\RecordMissing $e) {
            $result = $this->getViewRenderer()
                ->render('collection/collection-record-error.phtml');
        }
        $response = $this->getResponse();
        $response->setContent($result);
        return $response;
    }
}
