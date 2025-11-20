<?php

/**
 * Common EDS & EPF API Options
 *
 * PHP version 8
 *
 * Copyright (C) EBSCO Industries 2013
 * Copyright (C) The National Library of Finland 2022
 * Copyright (C) Villanova University 2025
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  EBSCO
 * @author   Michelle Milton <mmilton@epnet.com>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Search\EDS;

use function count;

/**
 * Common EDS & EPF API Options
 *
 * @category VuFind
 * @package  EBSCO
 * @author   Michelle Milton <mmilton@epnet.com>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Maccabee Levine <msl321@lehigh.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
abstract class AbstractEDSOptions extends \VuFind\Search\Base\Options
{
    use \VuFind\Search\Options\ViewOptionsTrait;

    /**
     * Default view option
     *
     * @var string
     */
    protected $defaultView = 'list_brief';

    /**
     * Extract a component from the defaultView API property.
     *
     * The defaultView API property takes the form vufindSetting_ebscoSetting -- the first component
     * of the underscore-delimited string is the view name used by VuFind (e.g. list or grid).
     * However, for EDS and EPF, only list is suggested to be used. The second component is the format
     * requested from the EBSCO API (e.g. title, brief or detailed).
     *
     * @param int    $index   Index of part to extract from the property
     * @param string $default Default to use as a fallback if the property does not contain delimited values
     *
     * @return string
     */
    protected function getDefaultViewPart(int $index, string $default): string
    {
        $ebscoDefaultView = $this->getConfiguredDefaultView();
        $viewArr = explode('_', $ebscoDefaultView);
        // $default is used to support legacy configs which only contain the EDS view part
        // that is normally the second part of the delimited value
        return (count($viewArr) > 1) ? $viewArr[$index] : $default;
    }

    /**
     * Get default view setting.
     *
     * @return int
     */
    public function getDefaultView()
    {
        return $this->getDefaultViewPart(0, 'list');
    }

    /**
     * Return the view type to request from the EBSCO API.
     *
     * @return string
     */
    public function getEbscoView()
    {
        return $this->getDefaultViewPart(1, $this->getConfiguredDefaultView());
    }
}
