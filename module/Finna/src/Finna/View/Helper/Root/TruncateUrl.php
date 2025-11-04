<?php

/**
 * URL truncater
 *
 * PHP version 8
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
 * along with this program; if not, see
 * <https://www.gnu.org/licenses/>.
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace Finna\View\Helper\Root;

use function strlen;

/**
 * URL truncater
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class TruncateUrl extends \Laminas\View\Helper\AbstractHelper
{
    /**
     * Truncate a URL for display
     *
     * @param string $url URL to truncate
     *
     * @return string
     */
    public function __invoke($url)
    {
        // Remove 'http://' (leave any other)
        if (strncasecmp($url, 'http://', 7) == 0) {
            $url = substr($url, 7);
        }
        // Remove trailing slash if it's the only one
        if (strpos($url, '/') == strlen($url) - 1) {
            $url = substr($url, 0, -1);
        }
        // Shorten if necessary
        if (strlen($url) > 40) {
            $url = preg_replace(
                '#^ (?>((?:.*:/+)?[^/]+/.{8})) .{4,} (.{12}) $#x',
                '$1...$2',
                $url
            );
        }
        return $url;
    }
}
