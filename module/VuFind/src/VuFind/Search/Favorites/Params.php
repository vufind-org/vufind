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
 * @package  Search_Favorites
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace VuFind\Search\Favorites;

/**
 * Search Favorites Parameters
 *
 * @category VuFind2
 * @package  Search_Favorites
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class Params extends \VuFind\Search\Base\Params
{
    /**
     * Auth manager
     *
     * @var \VuFind\Auth\Manager
     */
    protected $account;

    /**
     * Constructor
     *
     * @param \VuFind\Search\Base\Options  $options      Options to use
     * @param \VuFind\Config\PluginManager $configLoader Config loader
     */
    public function __construct($options, \VuFind\Config\PluginManager $configLoader)
    {
        parent::__construct($options, $configLoader);
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
        return ['side' => 'FavoriteFacets'];
    }

    /**
     * Add filters to the object based on values found in the request object.
     *
     * @param \Zend\StdLib\Parameters $request Parameter object representing user
     * request.
     *
     * @return void
     */
    protected function initFilters($request)
    {
        // Special filter -- if the "id" parameter is set, limit to a specific list:
        $id = $request->get('id');
        if (!empty($id)) {
            $this->addFilter("lists:{$id}");
        }

        // Otherwise use standard parent behavior:
        return parent::initFilters($request);
    }

    /**
     * Get account manager.
     *
     * @return \VuFind\Auth\Manager
     */
    public function getAuthManager()
    {
        return $this->account;
    }

    /**
     * Inject dependency: account manager.
     *
     * @param \VuFind\Auth\Manager $account Auth manager object.
     *
     * @return void
     */
    public function setAuthManager($account)
    {
        $this->account = $account;
    }
}