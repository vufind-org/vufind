<?php

/**
 * Record linker view helper
 *
 * PHP version 8
 *
 * Copyright (C) Villanova University 2010.
 * Copyright (C) The National Library of Finland 2023.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */

namespace VuFind\View\Helper\Root;

use VuFind\RecordDriver\AbstractBase as AbstractRecord;

use function is_array;
use function is_string;

/**
 * Record linker view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class RecordLinker extends \Laminas\View\Helper\AbstractHelper
{
    /**
     * Record router
     *
     * @var \VuFind\Record\Router
     */
    protected $router;

    /**
     * Search results (optional)
     *
     * @var \VuFind\Search\Base\Results
     */
    protected $results = null;

    /**
     * Cached record URLs
     *
     * @var array
     */
    protected $cachedDriverUrls = [];

    /**
     * Constructor
     *
     * @param \VuFind\Record\Router $router Record router
     */
    public function __construct(\VuFind\Record\Router $router)
    {
        $this->router = $router;
    }

    /**
     * Store an optional results object and return this object so that the
     * appropriate link can be rendered.
     *
     * @param ?\VuFind\Search\Base\Results $results Results object.
     *
     * @return RecordLinker
     */
    public function __invoke($results = null)
    {
        $this->results = $results;
        return $this;
    }

    /**
     * Given an array representing a related record (which may be a bib ID or OCLC
     * number), this helper renders a URL linking to that record.
     *
     * @param array  $link   Link information from record model
     * @param string $source Source ID for backend being used to retrieve records
     *
     * @return string       URL derived from link information
     */
    public function related($link, $source = DEFAULT_SEARCH_BACKEND)
    {
        $urlHelper = $this->getView()->plugin('url');
        $baseUrl = $urlHelper($this->getSearchActionForSource($source));
        switch ($link['type']) {
            case 'bib':
                return $baseUrl
                    . '?lookfor=' . urlencode($link['value'])
                    . '&type=id&jumpto=1';
            case 'dlc':
                return $baseUrl
                    . '?lookfor=' . urlencode('"' . $link['value'] . '"')
                    . '&type=lccn&jumpto=1';
            case 'isn':
                return $baseUrl
                    . '?join=AND&bool0[]=AND&lookfor0[]=%22'
                    . urlencode($link['value'])
                    . '%22&type0[]=isn&bool1[]=NOT&lookfor1[]=%22'
                    . urlencode($link['exclude'])
                    . '%22&type1[]=id&sort=title&view=list';
            case 'oclc':
                return $baseUrl
                    . '?lookfor=' . urlencode($link['value'])
                    . '&type=oclc_num&jumpto=1';
            case 'title':
                return $baseUrl
                    . '?lookfor=' . urlencode($link['value'])
                    . '&type=title';
        }
        throw new \Exception('Unexpected link type: ' . $link['type']);
    }

    /**
     * Given a record driver, get a URL for that record.
     *
     * @param AbstractRecord|string $driver  Record driver representing record
     * to link to, or source|id pipe-delimited string
     * @param string                $action  Record action to access
     * @param array                 $query   Optional query parameters
     * @param string                $anchor  Optional anchor
     * @param array                 $options Record URL parameter options (optional)
     *
     * @return string
     */
    public function getActionUrl(
        $driver,
        $action,
        $query = [],
        $anchor = '',
        $options = []
    ) {
        // Build the URL:
        $urlHelper = $this->getView()->plugin('url');
        $details = $this->router->getActionRouteDetails($driver, $action);
        return $urlHelper(
            $details['route'],
            $details['params'] ?: [],
            [
                'query' => $this->getRecordUrlParams($options) + $query,
                'fragment' => $anchor ? ltrim($anchor, '#') : '',
                'normalize_path' => false, // required to keep slashes encoded
            ]
        );
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
        if (is_array($url)) {
            // Assemble URL string from array parts:
            $source = $url['source'] ?? DEFAULT_SEARCH_BACKEND;
            parse_str($url['query'] ?? '', $query);
            $finalUrl = $this->getActionUrl(
                "{$source}|" . $url['record'],
                $url['action'],
                $query,
                $includeAnchor ? ($url['anchor'] ?? '') : ''
            );
        } else {
            // If URL is already a string but we don't want anchors, strip
            // the anchor now:
            if (!$includeAnchor) {
                [$finalUrl] = explode('#', $url);
            } else {
                $finalUrl = $url;
            }
        }
        return $finalUrl;
    }

    /**
     * Given a record driver, get a URL for that record.
     *
     * @param AbstractRecord|string $driver  Record driver representing record to
     * link to, or source|id pipe-delimited string
     * @param ?string               $tab     Optional record tab to access
     * @param array                 $query   Optional query params
     * @param array                 $options Any additional options:
     * - excludeSearchId (default: false)
     *
     * @return string
     */
    public function getTabUrl($driver, $tab = null, $query = [], $options = [])
    {
        $driverId = is_string($driver)
            ? $driver
            : ($driver->getSourceIdentifier() . '|' . $driver->getUniqueID());
        $cacheKey = md5(
            $driverId . '|' . ($tab ?? '-') . '|' . var_export($query, true)
            . var_export($options, true)
        );
        if (!isset($this->cachedDriverUrls[$cacheKey])) {
            // Build the URL:
            $urlHelper = $this->getView()->plugin('url');
            $details = $this->router->getTabRouteDetails($driver, $tab, $query);
            $this->cachedDriverUrls[$cacheKey] = $urlHelper(
                $details['route'],
                $details['params'],
                array_merge_recursive(
                    $details['options'] ?? [],
                    ['query' => $this->getRecordUrlParams($options)]
                )
            );
        }
        return $this->cachedDriverUrls[$cacheKey];
    }

    /**
     * Get the default URL for a record.
     *
     * @param AbstractRecord|string $driver  Record driver representing record to
     * link to, or source|id pipe-delimited string
     * @param array                 $options Any additional options:
     * - excludeSearchId (default: false)
     *
     * @return string
     */
    public function getUrl($driver, $options = [])
    {
        return $this->getTabUrl($driver, null, [], $options);
    }

    /**
     * Given a record driver, generate HTML to link to the record from breadcrumbs.
     *
     * @param AbstractRecord $driver Record to link to.
     *
     * @return string
     */
    public function getBreadcrumbHtml($driver)
    {
        $truncateHelper = $this->getView()->plugin('truncate');
        $escapeHelper = $this->getView()->plugin('escapeHtml');
        return '<a href="' . $this->getUrl($driver) . '">' .
            $escapeHelper($truncateHelper($driver->getBreadcrumb(), 30))
            . '</a>';
    }

    /**
     * Given a record driver, generate a URL to fetch all child records for it.
     *
     * @param AbstractRecord $driver Host Record.
     *
     * @return string
     */
    public function getChildRecordSearchUrl($driver)
    {
        $urlHelper = $this->getView()->plugin('url');
        $route = $this->getSearchActionForSource($driver->getSourceIdentifier());
        return $urlHelper($route)
            . '?lookfor='
            . urlencode(addcslashes($driver->getUniqueID(), '"'))
            . '&type=ParentID';
    }

    /**
     * Return search URL for all versions
     *
     * @param AbstractRecord $driver Record driver
     *
     * @return string
     */
    public function getVersionsSearchUrl($driver)
    {
        $route = $this->getVersionsActionForSource($driver->getSourceIdentifier());
        if (false === $route) {
            return '';
        }

        $urlParams = [
            'id' => $driver->getUniqueID(),
            'search' => 'versions',
        ];

        $urlHelper = $this->getView()->plugin('url');
        return $urlHelper($route, [], ['query' => $urlParams]);
    }

    /**
     * Given a record source ID, return the route name for searching its backend.
     *
     * @param string $source Record source identifier.
     *
     * @return string
     */
    protected function getSearchActionForSource($source)
    {
        $optionsHelper = $this->getView()->plugin('searchOptions');
        return $optionsHelper($source)->getSearchAction();
    }

    /**
     * Given a record source ID, return the route name for version search with its
     * backend.
     *
     * @param string $source Record source identifier.
     *
     * @return string|bool
     */
    protected function getVersionsActionForSource($source)
    {
        $optionsHelper = $this->getView()->plugin('searchOptions');
        return $optionsHelper($source)->getVersionsAction();
    }

    /**
     * Get query parameters for a record URL
     *
     * @param array $options Any additional options:
     * - excludeSearchId (default: false)
     *
     * @return array
     */
    protected function getRecordUrlParams(array $options = []): array
    {
        if (!empty($options['excludeSearchId'])) {
            return [];
        }
        $sid = ($this->results ? $this->results->getSearchId() : null)
            ?? $this->getView()->plugin('searchMemory')->getLastSearchId();
        return $sid ? compact('sid') : [];
    }
}
