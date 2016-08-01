<?php
/**
 * GeoCoords view helper
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Leila Gonzales <lmg@agiweb.org>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
namespace VuFind\View\Helper\Root;
/**
 * GeoCoords view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Leila Gonzales <lmg@agiweb.org>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class GeoCoords extends \Zend\View\Helper\AbstractHelper
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
    protected $geoField;

    /**
     * Constructor
     *
     * @param bool   $enabled  MapSearch enabled flag
     * @param string $coords   Default coordinates
     * @param string $geoField geoField variable name
     */
    public function __construct($enabled, $coords, $geoField)
    {
        $this->enabled = $enabled;
        $this->coords = $coords;
        $this->geoField = $geoField;
    }

    /**
     * Invoke
     *
     * @return string
     */
    public function __invoke()
    {
        $data = [$this->enabled, $this->coords, $this->geoField];
        return $data;
    }
}
