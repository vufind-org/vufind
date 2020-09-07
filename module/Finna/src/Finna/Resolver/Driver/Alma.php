<?php
/**
 * Alma Link Resolver Driver
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2019-2020
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
 * @package  Resolver_Drivers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:link_resolver_drivers Wiki
 */
namespace Finna\Resolver\Driver;

/**
 * Alma Link Resolver Driver
 *
 * @category VuFind
 * @package  Resolver_Drivers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:link_resolver_drivers Wiki
 */
class Alma extends \VuFind\Resolver\Driver\Alma
{
    /**
     * Fetch Links
     *
     * Fetches a set of links corresponding to an OpenURL
     *
     * @param string $openURL openURL (url-encoded)
     *
     * @return string         Raw XML returned by resolver
     */
    public function fetchLinks($openURL)
    {
        // Ignore Date Filter only if we don't have a year
        parse_str($openURL, $params);
        $idx = array_search('Date Filter', $this->ignoredFilterReasons);
        if (!empty($params['rft_date']) || !empty($params['rft.date'])) {
            if (false !== $idx) {
                unset($this->ignoredFilterReasons[$idx]);
            }
        } else {
            if (false === $idx) {
                $this->ignoredFilterReasons[] = 'Date Filter';
            }
        }

        return parent::fetchLinks($openURL);
    }
}
