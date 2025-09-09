<?php

/**
 * Favorites aspect of the Search Multi-class (Options)
 *
 * PHP version 8
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
 * @package  Search_Favorites
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\Search\Favorites;

use VuFind\Config\ConfigManagerInterface;

/**
 * Search Favorites Options
 *
 * @category VuFind
 * @package  Search_Favorites
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class Options extends \VuFind\Search\Base\Options
{
    use \VuFind\Config\Feature\ExplodeSettingTrait;

    /**
     * Constructor
     *
     * @param ConfigManagerInterface $configManager Config manager
     */
    public function __construct(ConfigManagerInterface $configManager)
    {
        parent::__construct($configManager);

        $this->defaultSort = 'title';
        $this->sortOptions = [
            'title' => 'sort_title', 'author' => 'sort_author',
            'year DESC' => 'sort_year', 'year' => 'sort_year_asc',
            'last_saved DESC' => 'sort_saved', 'last_saved' => 'sort_saved_asc',
        ];

        if (null !== ($limit = $this->mainConfig['Social']['lists_default_limit'] ?? null)) {
            $this->defaultLimit = $limit;
        }
        if (null !== ($limitOptions = $this->mainConfig['Social']['lists_limit_options'] ?? null)) {
            $this->limitOptions = $this->explodeListSetting($limitOptions);
        }
        if (null !== ($view = $this->mainConfig['Social']['lists_view'] ?? null)) {
            $this->listviewOption = $view;
        }
        if ($sortOptions = $this->mainConfig['List_Sorting'] ?? null) {
            $this->sortOptions = (array)$sortOptions;
            $this->defaultSort = array_keys($this->sortOptions)[0];
        }
    }

    /**
     * Return the route name for the search results action.
     *
     * @return string
     */
    public function getSearchAction()
    {
        return 'myresearch-favorites';
    }

    /**
     * Load all recommendation settings from the relevant ini file. Returns an
     * associative array where the key is the location of the recommendations (top
     * or side) and the value is the settings found in the file (which may be either
     * a single string or an array of strings).
     *
     * @param string $handler Name of handler for which to load specific settings.
     *
     * @return array associative: location (top/side/etc.) => search settings
     */
    public function getRecommendationSettings($handler = null)
    {
        return ['side' => 'FavoriteFacets'];
    }
}
