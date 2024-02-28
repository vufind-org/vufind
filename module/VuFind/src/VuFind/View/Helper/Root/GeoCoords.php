<?php

/**
 * GeoCoords view helper
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
 * @package  View_Helpers
 * @author   Leila Gonzales <lmg@agiweb.org>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */

namespace VuFind\View\Helper\Root;

use VuFind\Search\Base\Options;

use function is_array;

/**
 * GeoCoords view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Leila Gonzales <lmg@agiweb.org>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class GeoCoords extends \Laminas\View\Helper\AbstractHelper
{
    /**
     * Is Map Search enabled?
     *
     * @var bool
     */
    protected $enabled;

    /**
     * Default coordinates
     *
     * @var string
     */
    protected $coords;

    /**
     * Get geoField variable name
     *
     * @var string
     */
    protected $geoField = 'long_lat';

    /**
     * Constructor
     *
     * @param string $coords Default coordinates
     */
    public function __construct($coords)
    {
        $this->coords = $coords;
    }

    /**
     * Check if the relevant recommendation module is enabled; if not, there is no
     * point in generating a search link. Note that right now we are assuming it is
     * set up as a default top recommendation; this may need to be made more
     * flexible in future to account for more use cases.
     *
     * @param array $settings Recommendation settings
     *
     * @return bool
     */
    protected function recommendationEnabled($settings)
    {
        if (isset($settings['top']) && is_array($settings['top'])) {
            foreach ($settings['top'] as $setting) {
                $parts = explode(':', $setting);
                if (strtolower($parts[0]) === 'mapselection') {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Get search URL if geo search is enabled for the specified search class ID,
     * false if disabled.
     *
     * @param Options $options Search options
     *
     * @return string|bool
     */
    public function getSearchUrl(Options $options)
    {
        // If the relevant module is disabled, bail out now:
        if (!$this->recommendationEnabled($options->getRecommendationSettings())) {
            return false;
        }
        $urlHelper = $this->getView()->plugin('url');
        return $urlHelper('search-results')
            . '?filter[]=' . urlencode($this->geoField)
            . ':Intersects(ENVELOPE(' . urlencode($this->coords) . '))';
    }
}
