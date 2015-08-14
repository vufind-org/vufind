<?php
/**
 * Hierarchy Controller
 *
 * PHP version 5
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:building_a_controller Wiki
 */
namespace VuFind\Controller;

/**
 * Hierarchy Controller
 *
 * @category VuFind2
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:building_a_controller Wiki
 */
class HierarchyController extends AbstractBase
{
    /**
     * XML output routine
     *
     * @param string $xml XML to output
     *
     * @return \Zend\Http\Response
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
     * @return \Zend\Http\Response
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
     * @return \Zend\Http\Response
     */
    public function searchtreeAction()
    {
        $this->writeSession();  // avoid session write timing bug
        $config = $this->getConfig();
        $limit = isset($config->Hierarchy->treeSearchLimit)
            ? $config->Hierarchy->treeSearchLimit : -1;
        $resultIDs = [];
        $hierarchyID = $this->params()->fromQuery('hierarchyID');
        $lookfor = $this->params()->fromQuery('lookfor', '');
        $searchType = $this->params()->fromQuery('type', 'AllFields');

        $results = $this->getServiceLocator()
            ->get('VuFind\SearchResultsPluginManager')->get('Solr');
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
        $this->writeSession();  // avoid session write timing bug
        // Retrieve the record from the index
        $id = $this->params()->fromQuery('id');
        $loader = $this->getServiceLocator()->get('VuFind\RecordLoader');
        try {
            if ($recordDriver = $loader->load($id)) {
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
        $this->writeSession();  // avoid session write timing bug
        // Retrieve the record from the index
        $id = $this->params()->fromQuery('id');
        $loader = $this->getServiceLocator()->get('VuFind\RecordLoader');
        try {
            if ($recordDriver = $loader->load($id)) {
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
        }

        // If we got this far, something went wrong:
        return $this->outputJSON('error', 503); // Service Unavailable
    }

    /**
     * Get a record for display within a tree
     *
     * @return mixed
     */
    public function getrecordAction()
    {
        $id = $this->params()->fromQuery('id');
        $loader = $this->getServiceLocator()->get('VuFind\RecordLoader');
        try {
            $record = $loader->load($id);
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
