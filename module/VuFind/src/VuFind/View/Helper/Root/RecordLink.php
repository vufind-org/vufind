<?php
/**
 * Record link view helper (DEPRECATED -- use RecordLinker instead)
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
 * Record link view helper (DEPRECATED -- use RecordLinker instead)
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class RecordLink extends \Laminas\View\Helper\AbstractHelper
{
    /**
     * Given an array representing a related record (which may be a bib ID or OCLC
     * number), this helper renders a URL linking to that record.
     *
     * @param array  $link   Link information from record model
     * @param bool   $escape Should we escape the rendered URL?
     * @param string $source Source ID for backend being used to retrieve records
     *
     * @return string       URL derived from link information
     */
    public function related($link, $escape = true, $source = DEFAULT_SEARCH_BACKEND)
    {
        // Call parent helper, omitting $escape param:
        $value = $this->__call(__FUNCTION__, [$link, $source]);
        $escapeHelper = $this->getView()->plugin('escapeHtml');
        return $escape ? $escapeHelper($value) : $value;
    }

    /**
     * Magic method to proxy recordLinker functionality.
     *
     * @param string $method Method being called
     * @param array  $args   Method arguments
     *
     * @return mixed
     */
    public function __call($method, $args)
    {
        return $this->getView()->plugin('recordLinker')->$method(...$args);
    }

    /**
     * Alias for getBreadcrumbHtml(), for backward compatibility with
     * VuFind 7.x and earlier versions.
     *
     * @param \VuFind\RecordDriver\AbstractBase $driver Record to link to.
     *
     * @return string
     */
    public function getBreadcrumb($driver)
    {
        return $this->__call('getBreadcrumbHtml', [$driver]);
    }

    /**
     * Alias for getRequestUrl(), to maintain backward compatibility with
     * VuFind 2.2 and earlier versions.
     *
     * @param string|array $url           URL to process
     * @param bool         $includeAnchor Should we include an anchor?
     *
     * @return string
     */
    public function getHoldUrl($url, $includeAnchor = true)
    {
        return $this->__call('getRequestUrl', func_get_args());
    }

    /**
     * Given a string or array of parts, build a request (e.g. hold) URL.
     *
     * @param string|array $url           URL to process
     * @param bool         $includeAnchor Should we include an anchor?
     *
     * @return string
     */
    public function getRequestUrl($url, $includeAnchor = true)
    {
        $finalUrl = $this->__call(__FUNCTION__, func_get_args());
        // Make sure everything is properly HTML encoded:
        $escaper = $this->getView()->plugin('escapehtml');
        return $escaper($finalUrl);
    }

    /**
     * Given a record driver, generate a URL to fetch all child records for it.
     *
     * @param \VuFind\RecordDriver\AbstractBase $driver Host Record.
     *
     * @return string
     */
    public function getChildRecordSearchUrl($driver)
    {
        $url = $this->__call(__FUNCTION__, func_get_args());
        // Make sure everything is properly HTML encoded:
        $escaper = $this->getView()->plugin('escapehtml');
        return $escaper($url);
    }

    /**
     * Return search URL for all versions
     *
     * @param \VuFind\RecordDriver\AbstractBase $driver Record driver
     *
     * @return string
     */
    public function getVersionsSearchUrl($driver)
    {
        $url = $this->__call(__FUNCTION__, func_get_args());
        // Make sure everything is properly HTML encoded:
        $escaper = $this->getView()->plugin('escapehtml');
        return $escaper($url);
    }
}
