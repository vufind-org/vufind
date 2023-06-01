<?php

/**
 * Demo Link Resolver Driver
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2015.
 *
 * last update: 2011-04-13
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
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:link_resolver_drivers Wiki
 */

namespace VuFind\Resolver\Driver;

/**
 * Demo Link Resolver Driver
 *
 * @category VuFind
 * @package  Resolver_Drivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:link_resolver_drivers Wiki
 */
class Demo extends AbstractBase
{
    /**
     * Constructor
     *
     * @param string $baseUrl Base URL for link resolver
     */
    public function __construct($baseUrl = 'http://localhost')
    {
        parent::__construct($baseUrl);
    }

    /**
     * Fetch Links
     *
     * Fetches a set of links corresponding to an OpenURL
     *
     * @param string $openURL openURL (url-encoded)
     *
     * @return string
     */
    public function fetchLinks($openURL)
    {
        return $openURL;
    }

    /**
     * Parse Links
     *
     * Parses data returned by a link resolver
     * and converts it to a standardised format for display
     *
     * @param string $data Raw data
     *
     * @return array       Array of values
     */
    public function parseLinks($data)
    {
        return [
            [
                'href' => 'https://vufind.org/wiki?' . $data . '#print',
                'title' => 'Print',
                'coverage' => 'fake1',
                'service_type' => 'getHolding',
                'access' => 'unknown',
                'notes' => 'General notes',
            ],
            [
                'href' => 'https://vufind.org/wiki?' . $data . '#electronic',
                'title' => 'Electronic',
                'coverage' => 'fake2',
                'service_type' => 'getFullTxt',
                'access' => 'open',
                'authentication' => 'Authentication notes',
                'notes' => 'General notes',
            ],
        ];
    }
}
