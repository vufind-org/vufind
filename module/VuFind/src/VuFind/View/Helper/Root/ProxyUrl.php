<?php
/**
 * Proxy URL view helper
 *
 * PHP version 7
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
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\View\Helper\Root;

/**
 * Proxy URL view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class ProxyUrl extends \Laminas\View\Helper\AbstractHelper
{
    /**
     * VuFind configuration
     *
     * @var \Laminas\Config\Config
     */
    protected $config;

    /**
     * Constructor
     *
     * @param \Laminas\Config\Config $config VuFind configuration
     */
    public function __construct($config = null)
    {
        $this->config = $config;
    }

    /**
     * Apply proxy prefix to URL (if configured).
     *
     * @param string $url The raw URL to adjust
     *
     * @return string
     */
    public function __invoke($url)
    {
        $usePrefix = !isset($this->config->EZproxy->prefixLinks)
            || $this->config->EZproxy->prefixLinks;
        return ($usePrefix && isset($this->config->EZproxy->host))
            ? $this->config->EZproxy->host . '/login?qurl=' . urlencode($url)
            : $url;
    }
}
