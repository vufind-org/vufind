<?php

/**
 * Relais view helper
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2018.
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

use Laminas\Config\Config;

/**
 * Relais view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class Relais extends \Laminas\View\Helper\AbstractHelper
{
    /**
     * Relais configuration (or null if none found)
     *
     * @var Config
     */
    protected $config;

    /**
     * Login URL
     *
     * @var string
     */
    protected $loginUrl;

    /**
     * Constructor.
     *
     * @param Config $config   Relais configuration (or null if none found)
     * @param string $loginUrl Login base URL
     */
    public function __construct($config, $loginUrl)
    {
        $this->config = $config;
        $this->loginUrl = $loginUrl;
    }

    /**
     * Create a Relais search link from a record driver.
     *
     * @param object $driver Record driver
     *
     * @return string
     */
    public function getSearchLink($driver)
    {
        // Get data elements:
        $isbn = $driver->tryMethod('getCleanISBN');
        $title = $driver->tryMethod('getShortTitle');
        if (empty($title)) {
            $title = $driver->tryMethod('getTitle');
        }
        $mainAuthor = $driver->tryMethod('getPrimaryAuthor');

        // Assemble and return URL:
        $separator = strstr($this->loginUrl, '?') === false ? '?' : '&';
        $url = $this->loginUrl . $separator . 'query='
            . ($isbn ? 'isbn%3D' . rawurlencode($isbn) : 'ti%3D'
            . rawurlencode($title));
        if ($mainAuthor) {
            $url .= '%20and%20au%3D' . rawurlencode($mainAuthor);
        }
        return $url;
    }

    /**
     * Render a button if Relais is active.
     *
     * @param object $driver Record driver
     *
     * @return string
     */
    public function renderButtonIfActive($driver = null)
    {
        // Case 1: API enabled:
        if ($this->config->apikey ?? false) {
            return $this->getView()->render('relais/button.phtml');
        }
        // Case 2: Search links enabled:
        if ($this->config->loginUrl ?? false) {
            return '<a href="' . htmlspecialchars($this->getSearchLink($driver))
                . '" target="new">' . $this->getView()->transEsc('relais_search')
                . '</a>';
        }
        // Case 3: Nothing enabled:
        return '';
    }
}
