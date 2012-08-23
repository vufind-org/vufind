<?php
/**
 * VuFind Search Memory
 *
 * PHP version 5
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
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind2
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
namespace VuFind\Search;
use Zend\Session\Container as SessionContainer;

/**
 * Wrapper class to handle search memory
 *
 * @category VuFind2
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
class Memory
{
    /**
     * Clear the last accessed search URL in the session.
     *
     * @return void
     */
    public static function forgetSearch()
    {
        $session = new SessionContainer('Search');
        unset($session->last);
    }

    /**
     * Store the last accessed search URL in the session for future reference.
     *
     * @param string $url URL to remember
     *
     * @return void
     */
    public static function rememberSearch($url)
    {
        // Only remember URL if string is non-empty... otherwise clear the memory.
        if (strlen(trim($url)) > 0) {
            $session = new SessionContainer('Search');
            $session->last = $url;
        } else {
            self::forgetSearch();
        }
    }

    /**
     * Retrieve last accessed search URL, if available.  Returns null if no URL
     * is available.
     *
     * @return string|null
     */
    public static function retrieve()
    {
        $session = new SessionContainer('Search');
        return isset($session->last) ? $session->last : null;
    }
}