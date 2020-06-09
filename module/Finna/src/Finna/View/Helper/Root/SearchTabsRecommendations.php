<?php
/**
 * Helper class for search tabs recommendations.
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2015.
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
 * @package  View_Helpers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace Finna\View\Helper\Root;

/**
 * Helper class for search tabs recommendations.
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class SearchTabsRecommendations extends \Laminas\View\Helper\AbstractHelper
{
    /**
     * Recommendation config
     *
     * @var array
     */
    protected $recommendationConfig;

    /**
     * Constructor
     *
     * @param array $recommendationConfig Tab recommendation configuration
     */
    public function __construct($recommendationConfig)
    {
        $this->recommendationConfig = $recommendationConfig;
    }

    /**
     * Get the search tabs recommendation settings for the active search class
     *
     * @return array
     */
    public function getConfig()
    {
        return isset($this->recommendationConfig) ? $this->recommendationConfig : [];
    }
}
