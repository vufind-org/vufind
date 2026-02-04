<?php

/**
 * Factory for Blender2 search params objects.
 *
 * PHP version 8
 *
 * Copyright (C) The National Library of Finland 2019-2022.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\Search\Blender2;

/**
 * Factory for Blender2 search params objects.
 *
 * @category VuFind
 * @package  Search_Blender
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class ParamsFactory extends \VuFind\Search\Blender\ParamsFactory
{
    /**
     * Configuration file to read Blender settings from.
     *
     * Note that any change to this must be made before calling the constructor of this class.
     *
     * @var string
     */
    protected $blenderIni = 'Blender2';

    /**
     * Configuration file to read Blender mappings settings from.
     *
     * Note that any change to this must be made before calling the constructor of this class.
     *
     * @var string
     */
    protected $blenderMappingsYaml = 'Blender2Mappings';
}
