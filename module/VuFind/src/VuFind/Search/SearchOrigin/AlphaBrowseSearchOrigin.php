<?php

/**
 * Search Origin Object
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
 * @package  TODO?
 * @author   Robby ROUDON <roudonro@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */

namespace VuFind\Search\SearchOrigin;

use Exception;

/**
 * Object for search originating from AlphaBrowse
 *
 * @category VuFind
 * @package  TODO?
 * @author   Robby ROUDON <roudonro@msu.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class AlphaBrowseSearchOrigin extends AbstractSearchOrigin
{
    /**
     * Name of the origin of the search
     *
     * @var string
     */
    protected const NAME = 'AB';

    /**
     * Name of the route to get back to
     *
     * @var string
     */
    protected const URL_NAME = 'alphabrowse-home';

    /**
     * URL Parameter for "source" in search URL
     *
     * @var string
     */
    public const SEARCH_SOURCE_DISPLAY_PARAM = 'AB-sd';

    /**
     * URL Parameter for "source" in search URL
     *
     * @var string
     */
    public const SEARCH_SOURCE_PARAM = 'AB-s';

    /**
     * URL Parameter for "from" in search URL
     *
     * @var string
     */
    public const SEARCH_FROM_PARAM = 'AB-f';

    /**
     * URL Parameter for "page" in search URL
     *
     * @var string
     */
    public const SEARCH_PAGE_PARAM = 'AB-p';

    /**
     * URL Parameter for "source" in origin URL
     *
     * @var string
     */
    public const ORIGIN_SOURCE_PARAM = 'source';

    /**
     * URL Parameter for "from" in origin URL
     *
     * @var string
     */
    public const ORIGIN_FROM_PARAM = 'from';

    /**
     * URL Parameter for "page" in origin URL
     *
     * @var string
     */
    public const ORIGIN_PAGE_PARAM = 'page';

    /**
     * Value of the parameter for "sourceDisplay"
     *
     * @var string
     */
    protected $sourceDisplay;

    /**
     * Value of the parameter for "source"
     *
     * @var string
     */
    protected $source;

    /**
     * Value of the parameter for "from"
     *
     * @var string
     */
    protected $from;

    /**
     * Value of the parameter for "page"
     *
     * @var int
     */
    protected $page;

    /**
     *  Constructor
     *
     * @param string|null $sourceDisplay Display source parameter for alpha browse search
     * @param string|null $source        source parameter for alpha browse search
     * @param string|null $from          from parameter for alpha browse search
     * @param int|null    $page          page parameter for alpha browse search
     *
     * @throws Exception
     */
    public function __construct(?string $sourceDisplay, ?string $source, ?string $from, ?int $page = null)
    {
        if (isset($sourceDisplay, $source, $from) !== true) {
            throw new Exception('Missing parameters');
        }
        $this->sourceDisplay = $sourceDisplay;
        $this->source = $source;
        $this->from = $from;
        $this->page = $page;
    }

    /**
     * Get origin name
     *
     * @return string
     */
    public static function getName(): string
    {
        return self::NAME;
    }

    /**
     * Get name to display (ie. back to author browse))
     *
     * @return string
     */
    public function getDisplayName(): string
    {
        return $this->sourceDisplay;
    }

    /**
     * Get array of parameters to put in the URL
     *
     * @return array
     */
    public function getSearchUrlParamsArray(): array
    {
        $return = [
            self::PARAM_NAME => self::getName(),
            self::SEARCH_SOURCE_DISPLAY_PARAM => $this->sourceDisplay,
            self::SEARCH_SOURCE_PARAM => $this->source,
            self::SEARCH_FROM_PARAM => $this->from,
        ];
        if (isset($this->page)) {
            $return[self::SEARCH_PAGE_PARAM] = $this->page;
        }
        return $return;
    }

    /**
     * Get array of parameters to recreate the origin in the URL
     *
     * @return array
     */
    public function getOriginUrlParamsArray(): array
    {
        $return = [
            self::ORIGIN_SOURCE_PARAM => $this->source,
            self::ORIGIN_FROM_PARAM => $this->from,
        ];
        if (isset($this->page)) {
            $return[self::ORIGIN_PAGE_PARAM] = $this->page;
        }
        return $return;
    }

    /**
     * Get route name to generate the url
     *
     * @return string
     */
    public function getRouteName(): string
    {
        return self::URL_NAME;
    }
}
