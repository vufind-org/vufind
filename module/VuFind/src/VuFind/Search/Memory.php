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
use Zend\Session\Container;

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
     * Is memory currently active? (i.e. will we save new URLs?)
     *
     * @var bool
     */
    protected $active = true;

    /**
     * Session container
     *
     * @var Container
     */
    protected $session;

    /**
     * Constructor
     *
     * @param Container $session Session container for storing URLs (optional)
     */
    public function __construct($session = null)
    {
        $this->session = (null === $session)
            ? new Container('Search') : $session;
    }

    /**
     * Stop updating the URL in memory -- used in combined search to prevent
     * multiple search URLs from overwriting one another.
     *
     * @return void
     */
    public function disable()
    {
        $this->active = false;
    }

    /**
     * Clear the last accessed search URL in the session.
     *
     * @return void
     */
    public function forgetSearch()
    {
        unset($this->session->last);
    }

    /**
     * Store the last accessed search URL in the session for future reference.
     *
     * @param string $url URL to remember
     *
     * @return void
     */
    public function rememberSearch($url)
    {
        // Do nothing if disabled.
        if (!$this->active) {
            return;
        }

        // Only remember URL if string is non-empty... otherwise clear the memory.
        if (strlen(trim($url)) > 0) {
            $this->session->last = $url;
        } else {
            $this->forgetSearch();
        }
    }

    /**
     * Retrieve last accessed search URL, if available.  Returns null if no URL
     * is available.
     *
     * @return string|null
     */
    public function retrieve()
    {
        return isset($this->session->last) ? $this->session->last : null;
    }
}