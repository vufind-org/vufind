<?php

/**
 * AbstractBase for Resolver Driver
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
 * AbstractBase for Resolver Driver
 *
 * @category VuFind
 * @package  Resolver_Drivers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:link_resolver_drivers Wiki
 */
abstract class AbstractBase implements DriverInterface
{
    /**
     * Base URL for link resolver
     *
     * @var string
     */
    protected $baseUrl;

    /**
     * Constructor
     *
     * @param string $baseUrl Base URL for link resolver
     */
    public function __construct($baseUrl)
    {
        $this->baseUrl = $baseUrl;
    }

    /**
     * Get Resolver Url
     *
     * Transform the OpenURL as needed to get a working link to the resolver.
     *
     * @param string $openURL openURL (url-encoded)
     *
     * @return string Returns resolver specific url
     */
    public function getResolverUrl($openURL)
    {
        $url = $this->baseUrl;
        $url .= !str_contains($url, '?') ? '?' : '&';
        $url .= $openURL;
        return $url;
    }

    /**
     * This controls whether a "More options" link will be shown below the fetched
     * resolver links eventually linking to the resolver page previously being
     * parsed.
     * This is especially useful for resolver such as the JOP resolver returning
     * XML which would not be of any immediate use for the user.
     *
     * @return bool
     */
    public function supportsMoreOptionsLink()
    {
        return true;
    }
}
