<?php

/**
 * Blender2 aspect of the Search Multi-class (Options).
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2015-2022.
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
 * @package  Search_Blender
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Search\Blender2;

/**
 * Blender2 Search Options.
 *
 * @category VuFind
 * @package  Search_Blender
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class Options extends \VuFind\Search\Blender\Options
{
    /**
     * Configuration file to read search settings from.
     *
     * Note that any change to this must be made before calling the constructor of this class.
     *
     * @var string
     */
    protected $searchIni = 'Blender2';

    /**
     * Configuration file to read facet settings from.
     *
     * Note that any change to this must be made before calling the constructor of this class.
     *
     * @var string
     */
    protected $facetsIni = 'Blender2';

    /**
     * The route name for the search results action.
     *
     * @var string
     */
    protected $searchAction = 'blender2-results';

    /**
     * The route name for the advanced search action.
     *
     * @var string
     */
    protected $advancedSearchAction = 'blender2-advanced';
}
