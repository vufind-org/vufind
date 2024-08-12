<?php

/**
 * Search Origin Object
 *
 * PHP version 8
 *
 * Copyright (C) Michigan State University 2024.
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
 * @package  Search
 * @author   Robby ROUDON <roudonro@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Search\SearchOrigin;

/**
 * Abstract object for any search origin
 *
 * @category VuFind
 * @package  Search
 * @author   Robby ROUDON <roudonro@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
abstract class AbstractSearchOrigin
{
    /**
     * Name of the URL parameter containing the value of the search origin
     *
     * @var string
     */
    public const PARAM_NAME = 'origin';

    /**
     * Get origin name
     *
     * @return string
     */
    abstract public static function getName(): string;

    /**
     * Get array of parameters to put in the search URL
     *
     * @return array
     */
    abstract public function getSearchUrlParamsArray(): array;

    /**
     * Get array of parameters to recreate the origin in the URL
     *
     * @return array
     */
    abstract public function getOriginUrlParamsArray(): array;

    /**
     * Get route name to generate the url
     *
     * @return string
     */
    abstract public function getRouteName(): string;

    /**
     * Get translation label
     *
     * @return string
     */
    abstract public function getTranslationKey(): string;
}
