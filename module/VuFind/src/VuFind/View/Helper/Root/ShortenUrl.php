<?php

/**
 * View helper for shortening URLs.
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2019.
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\View\Helper\Root;

use VuFind\UrlShortener\UrlShortenerInterface;

/**
 * View helper for formatting dates and times
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class ShortenUrl extends \Laminas\View\Helper\AbstractHelper
{
    /**
     * URL shortener
     *
     * @var UrlShortenerInterface
     */
    protected $shortener;

    /**
     * Constructor
     *
     * @param UrlShortenerInterface $shortener URL shortener
     */
    public function __construct(UrlShortenerInterface $shortener)
    {
        $this->shortener = $shortener;
    }

    /**
     * Shorten a URL
     *
     * @param string $url URL to shorten
     *
     * @return string
     */
    public function __invoke($url)
    {
        return $this->shortener->shorten($url);
    }
}
