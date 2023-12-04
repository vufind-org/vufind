<?php

/**
 * CollectionSideFacets Recommendations Module
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
 * @package  Recommendations
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:recommendation_modules Wiki
 */

namespace VuFind\Recommend;

/**
 * CollectionSideFacets Recommendations Module
 *
 * This class extends the SideFacets functionality for use in Collection display.
 *
 * @category VuFind
 * @package  Recommendations
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:recommendation_modules Wiki
 */
class CollectionSideFacets extends SideFacets
{
    /**
     * Is the keyword filter box active?
     *
     * @var bool
     */
    protected $keywordFilter = false;

    /**
     * Store the configuration of the recommendation module.
     *
     * @param string $settings Settings from searches.ini.
     *
     * @return void
     */
    public function setConfig($settings)
    {
        parent::setConfig($settings);

        // Parse the additional settings:
        $settings = explode(':', $settings);
        if (isset($settings[3]) && $settings[3] !== 'false') {
            $this->keywordFilter = true;
        }
    }

    /**
     * Get the current value of the keyword filter.
     *
     * @return string
     */
    public function getKeywordFilter()
    {
        return $this->results->getParams()->getDisplayQuery();
    }

    /**
     * Is the keyword filter box enabled?
     *
     * @return bool
     */
    public function keywordFilterEnabled()
    {
        return $this->keywordFilter;
    }
}
