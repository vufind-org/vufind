<?php
/**
 * Record link view helper
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
 * Record link view helper
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
     * Record router
     *
     * @var \VuFind\Record\Router
     */
    protected $router;

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
        $urlHelper = $this->getView()->plugin('url');
        $baseUrl = $urlHelper($this->getSearchActionForSource($source));
        switch ($link['type']) {
        case 'bib':
            $url = $baseUrl
                . '?lookfor=' . urlencode($link['value'])
                . '&type=id&jumpto=1';
            break;
        case 'dlc':
            $url = $baseUrl
                . '?lookfor=' . urlencode('"' . $link['value'] . '"')
                . '&type=lccn&jumpto=1';
            break;
        case 'isn':
            $url = $baseUrl
                . '?join=AND&bool0[]=AND&lookfor0[]=%22'
                . urlencode($link['value'])
                . '%22&type0[]=isn&bool1[]=NOT&lookfor1[]=%22'
                . urlencode($link['exclude'])
                . '%22&type1[]=id&sort=title&view=list';
            break;
        case 'oclc':
            $url = $baseUrl
                . '?lookfor=' . urlencode($link['value'])
                . '&type=oclc_num&jumpto=1';
            break;
        case 'title':
            $url = $baseUrl
                . '?lookfor=' . urlencode($link['value'])
                . '&type=title';
            break;
        default:
            throw new \Exception('Unexpected link type: ' . $link['type']);
        }

        $escapeHelper = $this->getView()->plugin('escapeHtml');
        return $escape ? $escapeHelper($url) : $url;
    }

    /**
     * Given a record driver, get a URL for that record.
     *
     * @param \VuFind\RecordDriver\AbstractBase|string $driver Record driver
     * representing record to link to, or source|id pipe-delimited string
     * @param string                                   $action Record
     * action to access
     *
     * @return string
     */
    public function getActionUrl($driver, $action)
    {
        // Build the URL:
        $urlHelper = $this->getView()->plugin('url');
        $details = $this->router->getActionRouteDetails($driver, $action);
        return $urlHelper($details['route'], $details['params'] ?: []);
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
        return $this->getRequestUrl($url, $includeAnchor);
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
            $finalUrl
                = $this->getActionUrl("{$source}|" . $url['record'], $url['action']);
            if (isset($url['query'])) {
                $finalUrl .= '?' . $url['query'];
            }
            if (isset($url['anchor']) && $includeAnchor) {
                $finalUrl .= $url['anchor'];
            }
        } else {
            // If URL is already a string but we don't want anchors, strip
            // the anchor now:
            if (!$includeAnchor) {
                list($finalUrl) = explode('#', $url);
            } else {
                $finalUrl = $url;
            }
        }
        // Make sure everything is properly HTML encoded:
        $escaper = $this->getView()->plugin('escapehtml');
        return $escaper($finalUrl);
    }

    /**
     * Given a record driver, get a URL for that record.
     *
     * @param \VuFind\RecordDriver\AbstractBase|string $driver Record driver
     * representing record to link to, or source|id pipe-delimited string
     * @param string                                   $tab    Optional record
     * tab to access
     * @param array                                    $query  Optional query params
     *
     * @return string
     */
    public function getTabUrl($driver, $tab = null, $query = [])
    {
        // Build the URL:
        $urlHelper = $this->getView()->plugin('url');
        $details = $this->router->getTabRouteDetails($driver, $tab, $query);
        return $urlHelper(
            $details['route'], $details['params'], $details['options'] ?? []
        );
    }

    /**
     * Get the default URL for a record.
     *
     * @param \VuFind\RecordDriver\AbstractBase|string $driver Record driver
     * representing record to link to, or source|id pipe-delimited string
     *
     * @return string
     */
    public function getUrl($driver)
    {
        // Display default tab by default:
        return $this->getTabUrl($driver);
    }

    /**
     * Given a record driver, generate HTML to link to the record from breadcrumbs.
     *
     * @param \VuFind\RecordDriver\AbstractBase $driver Record to link to.
     *
     * @return string
     */
    public function getBreadcrumb($driver)
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
     * @param \VuFind\RecordDriver\AbstractBase $driver Host Record.
     *
     * @return string
     */
    public function getChildRecordSearchUrl($driver)
    {
        $urlHelper = $this->getView()->plugin('url');
        $route = $this->getSearchActionForSource($driver->getSourceIdentifier());
        $url = $urlHelper($route)
            . '?lookfor='
            . urlencode(addcslashes($driver->getUniqueID(), '"'))
            . '&type=ParentID';
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
        $route = $this->getVersionsActionForSource($driver->getSourceIdentifier());
        if (false === $route) {
            return '';
        }

        $urlParams = [
            'id' => $driver->getUniqueID(),
            'keys' => $driver->tryMethod('getWorkKeys', [], [])
        ];

        $urlHelper = $this->getView()->plugin('url');
        $url = $urlHelper($route, [], ['query' => $urlParams]);
        // Make sure everything is properly HTML encoded:
        $escaper = $this->getView()->plugin('escapehtml');
        return $escaper($url);
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
        return $optionsHelper->__invoke($source)->getSearchAction();
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
        return $optionsHelper->__invoke($source)->getVersionsAction();
    }
}
