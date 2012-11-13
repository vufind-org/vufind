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
 * @link     http://vufind.org/wiki/building_a_recommendations_module Wiki
 */
namespace VuFind\Controller;

/**
 * Hierarchy Controller
 *
 * @category VuFind2
 * @package  Controller
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/building_a_recommendations_module Wiki
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
    public function output($xml)
    {
        $response = $this->getResponse();
        $headers = $response->getHeaders();
        $headers->addHeaderLine('Content-type', 'text/xml');
        $response->setContent($xml);
        return $response;
    }

    /**
     * Gets a Hierarchy Tree
     *
     * @return void
     */
    public function gettreeAction()
    {
        // Retrieve the record from the index
        $id = $this->params()->fromQuery('id');
        $results = $this->getSearchManager()->setSearchClassId('Solr')->getResults();
        try {
            if ($recordDriver = $results->getRecord($id)) {
                $results = $recordDriver->getHierarchyDriver()->render(
                    $recordDriver,
                    $this->params()->fromQuery('context'),
                    $this->params()->fromQuery('mode'),
                    $this->params()->fromQuery('hierarchyID')
                );
                if ($results) {
                    $baseUrl = $this->url()->fromRoute('home');
                    $results = str_replace(
                        '%%%%VUFIND-BASE-URL%%%%', rtrim($baseUrl, '/'), $results
                    );
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
}
