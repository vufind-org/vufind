<?php
/**
 * Author aspect of the Search Multi-class (Params)
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
 * @package  SearchObject
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
 
/**
 * Author Search Options
 *
 * @category VuFind2
 * @package  SearchObject
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class VF_Search_SolrAuthor_Params extends VF_Search_Solr_Params
{
    /**
     * Support method for _initSearch() -- handle basic settings.
     *
     * @param Zend_Controller_Request_Abstract $request A Zend request object.
     *
     * @return boolean True if search settings were found, false if not.
     */
    protected function initBasicSearch($request)
    {
        // If no lookfor parameter was found, we have no search terms to
        // add to our array!
        if (is_null($lookfor = $request->getParam('author'))) {
            return false;
        }

        // Force the search to be a phrase:
        $lookfor = '"' . str_replace('"', '\"', $lookfor) . '"';
        
        // Set the search (handler is always Author for this module):
        $this->setBasicSearch($lookfor, 'Author');
        return true;
    }

    /**
     * Build a string for onscreen display showing the
     *   query used in the search (not the filters).
     *
     * @return string user friendly version of 'query'
     */
    public function getDisplayQuery()
    {
        // For display purposes, undo the query manipulation performed above
        // in initBasicSearch():
        $q = parent::getDisplayQuery();
        return str_replace('\"', '"', substr($q, 1, strlen($q) - 2));
    }

    /**
     * Load all recommendation settings from the relevant ini file.  Returns an
     * associative array where the key is the location of the recommendations (top
     * or side) and the value is the settings found in the file (which may be either
     * a single string or an array of strings).
     *
     * @return array associative: location (top/side) => search settings
     */
    protected function getRecommendationSettings()
    {
        // Load the necessary settings to determine the appropriate recommendations
        // module:
        $ss = VF_Config_Reader::getConfig($this->getSearchIni());

        // Load the AuthorModuleRecommendations configuration if available, use
        // standard defaults otherwise:
        if (isset($ss->AuthorModuleRecommendations)) {
            $recommend = array();
            foreach ($ss->AuthorModuleRecommendations as $section => $content) {
                $recommend[$section] = array();
                foreach ($content as $current) {
                    $recommend[$section][] = $current;
                }
            }
        } else {
            $recommend = array('side' => array('ExpandFacets:Author'));
        }

        return $recommend;
    }
}