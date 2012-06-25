<?php
/**
 * Favorites aspect of the Search Multi-class (Params)
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
 * Search Favorites Parameters
 *
 * @category VuFind2
 * @package  SearchObject
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class VF_Search_Favorites_Params extends VF_Search_Base_Params
{
    /**
     * Constructor
     *
     * @param VF_Search_Base_Options $options Options to use (null to load defaults)
     */
    public function __construct($options = null)
    {
        parent::__construct($options);
        $this->recommendationsEnabled(true);
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
        return array('side' => 'FavoriteFacets');
    }

    /**
     * Add filters to the object based on values found in the request object.
     *
     * @param Zend_Controller_Request_Abstract $request A Zend request object.
     *
     * @return void
     */
    protected function initFilters($request)
    {
        // Special filter -- if the "id" parameter is set, limit to a specific list:
        $id = $request->getParam('id');
        if (!empty($id)) {
            $this->addFilter("lists:{$id}");
        }

        // Otherwise use standard parent behavior:
        return parent::initFilters($request);
    }
}